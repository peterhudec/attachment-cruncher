<?php
/*
Plugin Name: Attachment Cruncher
Description: A Swiss Army Knife for transfering media attachment properties to post properties.
Version: 0.3
Author: Peter Hudec
Author URI: http://peterhudec.com
Plugin URI: http://peterhudec.com/programming/2013/05/29/attachment-cruncher-wordpress-plugin
License: GPL2
*/

// https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NNPNMTTULB3AS

class Attachment_Cruncher {
	
	public $plugin_name = 'Attachment Cruncher';
	private $version = 0.3;
	private $prefix = 'attachment_cruncher';
	private $settings_slug = 'attachment-cruncher';
	private $donate_button_id = 'NNPNMTTULB3AS';
	
	private $allow_edit_post_filter = TRUE;
	
	function __construct() {
		// Plugin settings hooks.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta',  array( $this, 'plugin_row_meta' ), 10, 2 );
		register_activation_hook( __FILE__, array( $this, 'defaults' ) );
		add_action('admin_init', array( $this, 'admin_init' ) );
		add_action('admin_menu', array( $this, 'admin_menu' ) );
		
		// Functionality hooks.
		add_action( 'edit_post', array( $this, 'edit_post' ) );
		add_action( 'edit_attachment', array( $this, 'edit_attachment' ) );
		add_filter( 'wp_redirect', array( $this, 'wp_redirect' ), 25, 1 );
		
		$this->options = get_option( $this->prefix );
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Hooks
	/////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Triggered when a post is being saved.
	 */
	public function edit_post( $post_id ) {
		// We are going to update the post from within this filter, so we need to prevent an infinite loop.
		if ( $this->allow_edit_post_filter && isset( $this->options['on_post_update'] ) && $this->options['on_post_update'] ) {
			$this->crunch( $post_id );
		}
	}

	/**
	 * Triggered when an attachment is being uploaded or saved.
	 */
	 public function edit_attachment( $attachment_id ) {
	 	error_log( 'ZZZZZZZZZZZZZZZZZZZZZ' );
	 	if ( $this->allow_edit_post_filter && isset( $this->options['on_attachment_update'] ) && $this->options['on_attachment_update'] ) {
			$attachment = get_post( $attachment_id );
			$post_id = $attachment->post_parent;
			
			if ( ! $post_id ) {
				return;
			}
			
			$this->crunch( $post_id );
		}
	 }
	
	/**
	 * Triggered when an attachment is being attached to a post.
	 * 
	 * Thanks for this hook to http://stackoverflow.com/users/1981996/diggy
	 * who answered this question http://stackoverflow.com/questions/16798615/attach-media-to-post-wordpress-hook
	 */ 
	public function wp_redirect( $location ) {
		
		if( ! is_admin() )
	        return $location;
		
		if ( $this->allow_edit_post_filter && isset( $this->options['on_attach'] ) && $this->options['on_attach'] ) {
			global $pagenow;
		
		    if( 'upload.php' == $pagenow && isset( $_REQUEST['found_post_id'] ) ) {
		        $parent_id = (int) $_REQUEST['found_post_id'];
		
		        if ( ! $parent_id )
		            return $location;
				
				$this->crunch( $parent_id );
				
		        error_log('RRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRR');
				error_log( $parent_id );
				error_log('RRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRRR');
		    }
		}
	
	    return $location;
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Top Level Functions
	/////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Returns IDs of all atachments added to the post content.
	 */
	private function get_inline_attachments( $post_id ) {
		
		$post = get_post( $post_id );
		
		if ( ! $post ) {
			return array();
		}
		
		// Find all attachment IDs.
		preg_match_all('/<img\s+[^>]*wp-image-(\d+)[^>]*\/>/', $post->post_content, $matches);
		
		if ( isset( $matches[1] ) && is_array( $matches[1] ) ) {
			return $matches[1];
		} else {
			return array();
		}
	}
	
	/**
	 * Returns IDs of all atachments attached to the post.
	 */
	public function get_attached_attachments( $post_id ) {
		
		// Collect attachments objects
		$attachment_posts = get_posts( array(
			'post_type' => 'attachment',
			'numberposts' => null,
			'post_status' => null,
			'post_parent' => $post_id
		) );
		
		function attachment_id( $attachment ){
			return $attachment->ID;
		}
		
		// Return only IDs.
		return array_map( 'attachment_id', $attachment_posts );
	}
	
	/**
	 * Transfers values from attachments related to post according to plugin options.
	 * 
	 * @param integer $post_id ID of the post to be crunched.
	 */
	public function crunch( $post_id ) {
		
		$attachment_ids = array();
		
		// Collect inline attachments.
		if ( isset( $this->options['include_inline'] ) && $this->options['include_inline'] ) {
			$attachment_ids = array_merge( $attachment_ids, $this->get_inline_attachments( $post_id ) );
		}
		
		// Collect attachments atached to the post.
		if ( isset( $this->options['include_attached'] ) && $this->options['include_attached'] ) {
			$attachment_ids = array_merge( $attachment_ids, $this->get_attached_attachments( $post_id ) );
		}
		
		$attachment_ids = array_unique( $attachment_ids );
		
		// crunch
		$this->allow_edit_post_filter = FALSE;
		$this->attachments_to_post( $post_id, $attachment_ids );
		$this->allow_edit_post_filter = TRUE;
	}
	
	
	/**
	 * Transfers values from passed attachments to post according to plugin options.
	 * 
	 * @param integer $post_id ID of the post to be crunched.
	 * @param array $attachment_ids Array of attachment IDs to be used as sources.
	 */
	private function attachments_to_post( $post_id, $attachment_ids ) {
		$this->crunch_properties( $post_id, $attachment_ids );
		$this->crunch_metas( $post_id, $attachment_ids );
		$this->crunch_taxonomies( $post_id, $attachment_ids );
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Simple post properties
	/////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Returns value extracted from attachment property specified in $source_array.
	 * 
	 * @param array $source_array Array of the form array('source' => 'source name', 'meta' => 'meta name').
	 * @param integer $attachment_id ID of the attachment.
	 * 
	 * @return string The extracted value.
	 */
	private function get_source_value( $source_array, $attachment_ids ) {
		
		// Dont bother if there is no source or attachments
		if ( ! isset( $source_array['source'] ) || ! $source_array['source'] || ! $attachment_ids ) {
			return;
		}
		
		$multiple = isset( $source_array['multiple'] ) ? $source_array['multiple'] : '';
		$param = isset( $source_array['param'] ) ? $source_array['param'] : '';
		
		// If first, slice the array to its first item.
		if ( $multiple == 'first' ) {
			if( isset( $attachment_ids[0] ) ) {
				$attachment_ids = array( $attachment_ids[0] );
			} else {
				return;	
			}
		}
		
		// If last, slice the array to its last item.
		if ( $multiple == 'last' ) {
			end( $attachment_ids );
			$attachment_ids = array( $attachment_ids[ key( $attachment_ids ) ] );
		}
		
		// Loop through attachment IDs/
		$results = array();
		foreach ( $attachment_ids as $key => $value ) {
			$attachment = get_post( $value );
			
			// Get the values.
			if ( $attachment ) {
				$value = '';
				switch ( $source_array['source'] ) {
					
					case 'title':
						$value = $attachment->post_title;
						break;
						
					case 'caption':
						$value = $attachment->post_excerpt;
						break;
						
					case 'description':
						$value = $attachment->post_content;
						break;
						
					case 'alt':
						$value = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', TRUE );
						break;
						
					case 'meta':
						if ( isset( $source_array['meta'] ) ) {
							$value = get_post_meta( $attachment->ID, $source_array['meta'], TRUE );
						}
						break;
				}
				
				// Return first found value.
				if ( $multiple == 'contains' ) {
					preg_match("/.*$param.*/", $value, $match );
					if ( $match && isset( $match[0] ) ) {
						return $match[0];
					}
				}
				
				array_push( $results, $value );
			}
		}
		
		// Remove empty items and repeating values.
		$results = array_filter( $results );
		$results = array_unique( $results );
		
		// Proces and return results.
		switch ( $multiple ) {
			case 'first':
				return $results[0];
				
			case 'last':
				end( $results );
				return $results[ key( $results ) ];
				
			case 'frequent':
				$c = array_count_values( $results ); 
				return array_search( max( $c ), $c );
				
			case 'concat':
				return implode( $param, $results );
		}
	}
	
	/**
	 * Updates the post properties according to options and passed attachments.
	 * 
	 * @param integer $post_id ID of the post to be crunched.
	 * @param array $attachment_ids Array of attachment IDs to be used as sources.
	 */
	private function crunch_properties( $post_id, $attachment_ids ) {
		
		$post = get_post( $post_id );
		
		// Don't bother if there's no post.		
		if ( ! $post ) {
			return;
		}
		
		if ( isset( $this->options['title'] ) ) {
			$post->post_title = $this->get_source_value( $this->options['title'], $attachment_ids );
		}
		
		if ( isset( $this->options['excerpt'] ) ) {
			$post->post_excerpt = $this->get_source_value( $this->options['excerpt'], $attachment_ids );
		}
		
		if ( isset( $this->options['content'] ) ) {
			$post->post_content = $this->get_source_value( $this->options['content'], $attachment_ids );
		}
		
		wp_update_post( $post );
	}
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Post metas
	/////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Updates the post metas according to options and passed attachments.
	 * 
	 * @param integer $post_id ID of the post to be crunched.
	 * @param array $attachment_ids Array of attachment IDs to be used as source.
	 */
	private function crunch_metas( $post_id, $attachment_ids ) {
		// loop through $this->options['metas']
		if ( isset( $this->options['metas'] ) && is_array( $this->options['metas'] ) ) {
			foreach ( $this->options['metas'] as $meta_key => $source_array ) {
				$this->crunch_meta( $post_id, $meta_key, $source_array, $attachment_ids );
			}
		}
	}
	
	/**
	 * Updates single post meta according to options and passed attachments.
	 * 
	 * @param integer $post_id ID of the post to be crunched.
	 * @param string $meta_key Key of the meta.
	 * @param array $source_array The setting array for the meta key.
	 * @param array $attachment_ids Array of attachment IDs to be used as source.
	 */
	private function crunch_meta( $post_id, $meta_key, $source_array, $attachment_ids ) {
		
		$value = $this->get_source_value( $source_array, $attachment_ids );
		add_post_meta( $post_id, $meta_key, $value, TRUE ) || update_post_meta( $post_id, $meta_key, $value	 );
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Post taxonomies
	/////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Crunches all taxonomies.
	 */
	private function crunch_taxonomies( $post_id, $attachment_ids ) {
		if ( isset( $this->options['taxonomies'] ) ) {
			foreach ( $this->options['taxonomies'] as $key => $value) {
				$this->crunch_taxonomy( $key, $post_id, $attachment_ids );
			}
		}
	}
	
	private function test() {
		//$res = $this->get_terms_for_taxonomy( 'post_tag', get_post(5187) );
		//return gettype($res[0]);
		$this->crunch_taxonomies( 5137, array(5150) );
	}
		
	/**
	 * Crunches the specified taxonomy of a post.
	 * 
	 * @param string $taxonomy Taxonomy name.
	 * @param integer $post_id The ID of post to be crunched.
	 * @param array $attachment_ids Array of attachment IDs to be used as source.
	 */
	private function crunch_taxonomy( $taxonomy, $post_id, $attachment_ids ) {
		
		$post = get_post( $post_id );
		
		if ( taxonomy_exists( $taxonomy ) && $post ) {
			
			$term_ids = array();
			
			foreach ( $attachment_ids as $id ) {
				$attachment_post = get_post( $id );
				$term_ids = array_merge( $term_ids, $this->get_terms_for_taxonomy( $taxonomy, $attachment_post ) );
			}
			
			wp_set_object_terms( $post->ID, $term_ids, $taxonomy );
		}
	}
	
	
	/**
	 * Returns an array of taxonomy terms for a taxonomy.
	 */
	private function get_terms_for_taxonomy( $taxonomy, $attachment_post ) {
		
		$taxonomy_setting = isset( $this->options['taxonomies'][ $taxonomy ] ) ? $this->options['taxonomies'][ $taxonomy ] : null ;
		
		$term_ids = array();
		
		// Loop through properties
		foreach ( $taxonomy_setting as $key => $value ) {
			if ( $key != 'metas' && isset( $value['enable'] ) && $value['enable'] == 'on' ) {
				
				$delimiter = isset( $value['delimiter'] ) ? $value['delimiter'] : '';
				$handle_as = isset( $value['handle_as'] ) ? $value['handle_as'] : 'names';
				$create = isset( $value['create'] ) ? $value['create'] : FALSE;
				
				switch ( $key ) {
					
					case 'title':
						$value = $attachment_post->post_title;
						break;
					
					case 'caption':
						$value = $attachment_post->post_excerpt;
						break;
					
					case 'description':
						$value = $attachment_post->post_content;
						break;
					
					case 'alt':
						$value = get_post_meta( $attachment_post->ID, '_wp_attachment_image_alt', TRUE );
						break;
					
					default:
						$value = '';
						break;
				}
				
				$term_ids = array_merge( $term_ids, $this->get_terms_for_source( $taxonomy, $value, $delimiter, $handle_as, $create ) );
			}
		}
		
		// Loop through metas
		if ( isset( $taxonomy_setting['metas'] ) ) {
			foreach ( $taxonomy_setting['metas'] as $key => $value ) {
				
				$delimiter = isset( $value['delimiter'] ) ? $value['delimiter'] : '';
				$handle_as = isset( $value['handle_as'] ) ? $value['handle_as'] : 'names';
				$create = isset( $value['create'] ) ? $value['create'] : FALSE;
				$value = get_post_meta( $attachment_post->ID, $key, TRUE );
				
				$term_ids = array_merge( $term_ids, $this->get_terms_for_source( $taxonomy, $value, $delimiter, $handle_as, $create ) );
			}
		}
		
		// Remove empty values.
		return array_filter( $term_ids );
	}
	
	
	/**
	 * Returns array of taxonomy term IDs extracted from a taxonomy source row
	 * 
	 * @param string $taxonomy Taxonomy name.
	 * @param string $value Value to be parsed.
	 * @param string $delimiter Delimiter upon which the valus will be split.
	 * @param string $handle_as Either "names", "ids" or "slugs".
	 * @param boolean $create If TRUE, missing terms will be created.
	 * 
	 * @return array Array of taxonomy term object IDs.
	 */
	private function get_terms_for_source( $taxonomy, $value, $delimiter, $handle_as, $create ) {
			
		// Map handle_as names to wp field names.
		$fields = array(
			'names' => 'name',
			'slugs' => 'slug',
			'ids' => 'id',
		);
		
		// Allow creation only by names
		if ( $handle_as != 'names' ) {
			$create = FALSE;
		}
		
		// Resolve field.
		$field = $fields[ $handle_as ];
		
		// Split values.
		$values = explode( $delimiter, $value );
		
		// Get terms.
		$term_ids = array();
		foreach ( $values as $k => $v ) {
			array_push( $term_ids, $this->get_or_create_term( $field, trim( $v ), $taxonomy, $create ) );
		}
		
		return $term_ids;
	}
	
	
	/**
	 * Returns an ID of taxonomy term object.
	 * 
	 * @param string $field Either 'name', 'slug' or 'id'.
	 * @param string|integer $value Search for this term value
	 * @param bool $create If TRUE and the term doesn't exist, it will be created.
	 * 
	 * @return integer Taxonomy term object ID.
	 */
	private function get_or_create_term( $field, $value, $taxonomy, $create ) {
		
		// Get term.
		$term = get_term_by( $field, $value, $taxonomy );
		
		// Create term.
		if ( !$term && $create ) {
			
			if ( taxonomy_exists( $taxonomy ) ) {
				$new_term_info = wp_insert_term( $value, $taxonomy );
							
				if ( is_array( $new_term_info ) && isset( $new_term_info['term_id'] ) ) {
					$new_term_id = $new_term_info['term_id'];
					$term = get_term_by( 'id', $new_term_id, $taxonomy );
				}
			}
		}
		
		return isset( $term->term_id ) ? (integer) $term->term_id : null ;
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Plugin settings
	/////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Adds action links to the plugin
	 * 
	 * @return updated plugin links
	 */
	public function plugin_action_links( $links, $file ) {
		
	    static $this_plugin;
	    if ( ! $this_plugin ) {
	        $this_plugin = plugin_basename( __FILE__ );
	    }
		
	    if ( $file == $this_plugin ) {
	    	$url = esc_url( admin_url( "plugins.php?page={$this->settings_slug}" ) );
	        $settings_link = "<a href=\"$url\">Settings</a>";
	        array_unshift( $links, $settings_link );
	    }
		
	    return $links;
	}
	
	/**
	 * Adds action links to the plugin row
	 * 
	 * @return updated plugin links
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
			$url = esc_url( admin_url( "plugins.php?page={$this->settings_slug}" ) );
	        $links[] = "<a href=\"$url\">Settings</a>";
			$links[] = "<a href=\"$this->donate_url\">Donate</a>";
		}
		return $links;
	}
	
	/**
	 * Plugin options callback
	 */
	public function admin_menu() {
		$page = add_plugins_page(
			$this->plugin_name,
			$this->plugin_name,
			'manage_options',
			"{$this->settings_slug}",
			array( $this, 'options_cb' )
		);
		
	    add_action( 'admin_print_scripts-' . $page, array( $this, 'js' ) );
	    add_action( 'admin_print_styles-' . $page, array( $this, 'css' ) );
	}
	
	function js() { wp_enqueue_script( "{$this->prefix}_script" ); }
	function css() { wp_enqueue_style( "{$this->prefix}_style" ); }
	
	/**
	 * Default plugin options
	 */
	public function defaults() {
		
		// add_option
		// update_option
		// delete_option( $this->prefix );
		add_option( $this->prefix, array(
			'version' => $this->version,
			'inline_attachments' => null,
			'post_attachments' => null,
			'title' => array(
				'source' => '',
				'meta' => '',
			),
			'excerpt' => array(
				'source' => '',
				'meta' => '',
			),
			'metas' => array(),
			'taxonomies' => array(),
		) );
	}
	
	/**
	 * Options page callback
	 */
	public function options_cb() { ?>
		<div id="<?php echo $this->settings_slug; ?>" class="wrap <?php echo $this->settings_slug; ?>">
			<h2><?php echo $this->plugin_name; ?> Options</h2>
			<?php settings_errors(); ?>
			<h2 class="nav-tab-wrapper">
				<?php
					if ( isset( $_GET['tab'] ) ) {
						$active_tab = $_GET['tab'];
					} else {
						$active_tab = 'settings';
					}
					
					function active_tab( $value, $at  ) {
						if ( $at == $value ) {
							echo 'nav-tab-active';
						}
					}
				?>
				<a href="?page=<?php echo $this->settings_slug; ?>&tab=settings" class="nav-tab <?php active_tab( 'settings', $active_tab ); ?>">Settings</a>
				<a href="?page=<?php echo $this->settings_slug; ?>&tab=about" class="nav-tab <?php active_tab( 'about', $active_tab ); ?>">About</a>
			</h2>
			
			<?php if ( $active_tab == 'settings' ): ?>
				
				<input type="hidden" id="allow-submit" value="1" />
				
				<?php // Templates for JavaScript ?>
				<div class="post-meta-template" style="display:none">
					<?php $this->property_template( '', TRUE ) ?>
				</div>
				<div class="add-meta-template" style="display:none">
					<?php $this->taxonomy_meta_template() ?>
				</div>
				
				<form id="ac-form" action="options.php" method="post">
					<?php
						settings_fields( $this->prefix ); // renders hidden input fields
						do_settings_sections( "{$this->prefix}-section-0" );
						submit_button();
					?>
				</form>
			<?php elseif ( $active_tab == 'about' ): ?>
				<?php do_settings_sections( "{$this->prefix}-section-1" ); ?>
			<?php endif ?>
		</div>
	<?php }
	
	/**
	 * Adds a section to the plugin admin page
	 */
	private function section( $id, $title ) {
		add_settings_section(
			"{$this->prefix}_section_{$id}", // section id
			$title, // title
			array( $this, "section_{$id}" ), // callback
			"{$this->prefix}-section-{$id}" // page
		);
	}
	
	/**
	 * Plugin initialization
	 */
	public function admin_init() {
	    
	    // register stylesheets and scripts for admin
	    wp_register_script( "{$this->prefix}_script", plugins_url( 'script.js', __FILE__ ) );
	    wp_register_style( "{$this->prefix}_style", plugins_url( 'style.css', __FILE__ ) );
	    
	    ///////////////////////////////////
	    // Sections
	    ///////////////////////////////////
	    $this->section( 0, 'Settings:' );
	    $this->section( 1, 'About Attachment Cruncher:' );
	    
	    ///////////////////////////////////
	    // Options
	    ///////////////////////////////////
	    
	    register_setting(
	        $this->prefix,          // option group
	        $this->prefix,                    // option name
	        array( $this, 'sanitizer' )       // sanitizer
	    );  
	}
	
	public function sanitizer( $input ) {
		return $input;
	}
	
	/**
	 * Settings section.
	 */
	public function section_0() { ?>
		<p class="info">
			This plugin allows you to transfer informations from media attachments to
			the post to which they are attached.
		</p>
		<h1>When to Crunch?</h1>
		<p class="info">
			Choose when the plugin will do it's job.
		</p>
		<?php
			$attachment_relations = array(
				'on_post_update' => 'When the post is being saved.',
				'on_attachment_update' => 'When an attachment attached to the post is being added or saved.',
				'on_attach' => 'When an attachment is being attached to a post.'
			);
		?>
		<?php foreach ( $attachment_relations as $key => $title ): ?>
			<input	type="checkbox"
					name="<?php echo "{$this->prefix}[$key]"; ?>"
					<?php checked( 'on', isset( $this->options[$key] ) ? $this->options[$key] : '' ); ?>>
			<span>
				<?php echo $title; ?>
			</span>
			<br />
		<?php endforeach ?>
		
		<h1>Which Attachments to Include?</h1>
		<p class="info">
			Choose which attachments will be taken into account.
		</p>
		<?php
			$attachment_relations = array(
				'include_inline' => 'Inline attachments inside post content.',
				'include_attached' => 'Attachments attached to the post.',
			);
		?>
		<?php foreach ( $attachment_relations as $key => $title ): ?>
			<input	type="checkbox"
					name="<?php echo "{$this->prefix}[$key]"; ?>"
					<?php checked( 'on', isset( $this->options[$key] ) ? $this->options[$key] : '' ); ?>>
			<span>
				<?php echo $title; ?>
			</span>
			<br />
		<?php endforeach ?>
		
		<br /><hr /><h1>Properties</h1>
		<p class="info">
			Choose which attachment property will be used as the value of post properties.
			If you don't want a property to be changed, choose the first blank item.
			You can also choose what to do when there are multiple attachments.
			Some of the options allow you to specify an additional parameter.
		</p>
		<p class="info warning">
			Be careful with this setting,
			the original values of the post properties will be <strong>replaced</strong> with the new values!
		</p>
		<div class="properties">
			<div class="header">
				<span class="column-1">Target</span>
				<span class="column-2"></span>
				<span class="column-3">Source</span>
				<span class="column-4">If there are multiple attachments use:</span>
				<span class="column-5">Parameter</span>
				<span class="column-5">Source meta</span>
			</div>
			<?php foreach ( array('title', 'excerpt', 'content') as $v ): ?>
	            <?php $this->property_template( $v ); ?>
	        <?php endforeach ?>
		</div>
        
        <br /><hr /><h1>Metas</h1>
		<p class="info">
			Choose which attachment property will be used as the value of post meta.
		</p>
        <div class="properties">
        	<div class="header">
				<span class="column-1"></span>
				<span class="column-2">Target meta</span>
				<span class="column-3">Source</span>
				<span class="column-4">If there are multiple attachments use:</span>
				<span class="column-5">Parameter</span>
				<span class="column-5">Source meta</span>
			</div>
			<div class="post-metas">
				<?php if ( isset( $this->options['metas'] ) ): ?>
			        <?php foreach ( $this->options['metas'] as $key => $value): ?>
			            <?php $this->property_template( $key, TRUE ); ?>
			        <?php endforeach ?>
				<?php endif ?>
	        </div>
	       	<div class="button add-post-meta">Add Post Meta</div>
		</div>
        
        <br /><hr /><h1>Taxonomies</h1>
		<p class="info">
			Choose which attachment properties will be used as sorces for the post taxonomy terms.
			
			To use an attachment property as a source, check the checkbox next to the property name.
			Set the delimiter to split the value to terms (a coma by coma-separated values for instance).
			If you omit the delimiter, the whole value will be used as a single term.
			Set how the split terms should be handled.
			Only existing terms will be used, except when <strong>Handle</strong> as is set to <strong>Names</strong> ,
			where you can choose to create terms which were not found in the database. 
			<br /><br />
			You can choose multiple sources for a single taxonomy.
			The plugin will first collect and eventually create all terms extracted from the source
			and finally add all the terms to the post.
		</p>
        <div class="taxonomies">
        	<?php foreach ( get_taxonomies() as $taxonomy ): ?>
				<?php $this->taxonomy_template( $taxonomy ); ?>
			<?php endforeach ?>
        </div>
        
        <p>
        	If you encounter any errors or unexpected behaviour,
        	please send me the error description together with the content of the debug info. 
        </p>
        <input type="checkbox" id="show-debug-info" /> Show debug info
        <pre id="debug-info" class="hidden"><?php print_r( $this->options ) ?></pre>
        
    <?php }
	
	/**
	 * About section.
	 */
	public function section_1() { ?>
		<p>
			Created due to
			<a href="http://wordpress.org/support/topic/keywords-tags" target="_blank">popular demand</a>
			by me <strong>Peter Hudec</strong>.
			You cand find out more about me at
			<a href="http://peterhudec.com" target="_blank">peterhudec.com</a>.
		</p>
		<p>
			The source code of the plugin is hosted on
			<a href="https://github.com/peterhudec/attachment-cruncher">GitHub</a>.
		</p>
		<p>
			This plugin is and allways will be free but if you can't help yourself and want to pay for it anyway, you can do so by clicking the button below <strong>:-)</strong><br />
		</p>
		<form action="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=<?php echo $this->donate_button_id; ?>" method="post">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="hosted_button_id" value="RJYHYJJD2VKAN">
			<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
		</form>
	<?php }
	
	private function property_template( $name, $meta_template = FALSE ) {
		$meta = '';
		$source = '';
		$multiple = '';
		$param = '';
		$hidemeta = TRUE;
		
		if ( $meta_template ) {
			$settings_path = "{$this->prefix}[metas][{post_meta_name}]";
			
			if ( isset( $this->options['metas'][$name]['meta'] ) ) {
				$meta = $this->options['metas'][$name]['meta'];
			}
			
			if ( isset( $this->options['metas'][$name]['source'] ) ) {
				$source = $this->options['metas'][$name]['source'];
				if ( $source == 'meta' ) {
					// Hide meta input if meta not selected.
					$hidemeta = FALSE;
				}
			}
			
			if ( isset( $this->options['metas'][$name]['multiple'] ) ) {
				$multiple = $this->options['metas'][$name]['multiple'];
			}
			
			if ( isset( $this->options['metas'][$name]['param'] ) ) {
				$param = $this->options['metas'][$name]['param'];
			}
			
			
		} else {
			$settings_path = "{$this->prefix}[$name]";
			
			if ( isset( $this->options[$name]['meta'] ) ) {
				$meta = $this->options[$name]['meta'];
			}
			
			if ( isset( $this->options[$name]['source'] ) ) {
				$source = $this->options[$name]['source'];
				if ( $source == 'meta' ) {
					// Hide meta input if meta not selected.
					$hidemeta = FALSE;
				}
			}
			
			if ( isset( $this->options[$name]['multiple'] ) ) {
				$multiple = $this->options[$name]['multiple'];
			}
			
			if ( isset( $this->options[$name]['param'] ) ) {
				$param = $this->options[$name]['param'];
			}
			
		}
		
		?>
		<div class="<?php if ( $meta_template ): ?>post-meta<?php endif ?>">
			<?php if ( $meta_template ): ?>
				<div class="button remove-post-meta column-1">Remove</div>
				<input	type="text"
						class="post-meta-name column-2"
						placeholder="Post meta name"
						value="<?php echo $name; ?>" />
			<?php else: ?>
				<span class="column-1"><?php echo ucfirst( $name ); ?></span>
				<span class="column-2"></span>
			<?php endif ?>
			
			<select	class="source replace column-3"
					name="<?php echo "{$settings_path}[source]"; ?>">
				<?php foreach (array('', 'title', 'caption', 'description', 'alt', 'meta') as $v): ?>
					<option	value="<?php echo $v; ?>"
							<?php echo ( $source == $v) ? 'selected="selected"' : ''; ?>
							>
						<?php echo ucfirst( $v ); ?>
					</option>
				<?php endforeach ?>
			</select>
			
			<span class="settings">
				<select	class="replace multiple column-4"
						name="<?php echo "{$settings_path}[multiple]"; ?>">
					
					<?php $multiples = array(
						'first' => 'Value from first attachment',
						'last' => 'Value from last attachment',
						'concat' => 'Concatenate values with glue',
						'frequent' => 'Most frequent value',
						'contains' => 'First value that contains',
					); ?>
					
					<?php foreach ( $multiples as $key => $value ): ?>
						<option	value="<?php echo $key; ?>"
								<?php echo ( $multiple == $key ) ? 'selected="selected"' : ''; ?>>
							<?php echo $value; ?>
						</option>
					<?php endforeach ?>
				</select>
				
				<input	type="text"
						class="replace param column-5"
						style="visibility:hidden"
						value="<?php echo $param; ?>"
						name="<?php echo "{$settings_path}[param]"; ?>" />
						
				<input	type="text"
						class="replace meta column-6"
						<?php echo $hidemeta ? 'style="display:none"' : ''; ?>
						name="<?php echo "{$settings_path}[meta]"; ?>"
						value="<?php echo $meta; ?>"
						placeholder="meta name" />
			</span>
		</div>
	<?php }
	
	private function handle_as_template( $name, $setting, $setting_path ) {
		
		$selected = isset( $setting['handle_as'] ) ? $setting['handle_as'] : 'names';
		$unique_id = uniqid();
		
		?>
		<?php foreach ( array('Names', 'Slugs', 'IDs') as $key => $value): ?>
			<input	class="replace handle-as"
					type="radio"
					value="<?php echo strtolower( $value ); ?>"
					name="<?php echo $setting_path . '[handle_as]{{{' . $unique_id . '}}}'; ?>"
					<?php checked( strtolower( $value ), $selected ); ?>>
			<span><?php echo $value; ?></span>
		<?php endforeach ?>
		
		<span class="create <?php echo ( $selected == 'names' ) ? '' : 'hidden' ; ?>">
			<input	class="replace"
					type="checkbox"
					name="<?php echo $setting_path . '[create]'; ?>"
					<?php checked('on', isset( $setting['create'] ) ? $setting['create'] : '' ); ?>>
			<span>Create if not found</span>
		</span>
	<?php }
	
	private function taxonomy_meta_template( $taxonomy = '', $meta_name = '' ) {
		$meta = null;
		if ( $taxonomy && $meta_name ) {
			$metas = $this->options['taxonomies'][ $taxonomy ]['metas'];
			if ( isset( $metas[ $meta_name ] ) ) {
				$meta = $metas[ $meta_name ];
			}
		}
		
		$settings_path = "{$this->prefix}[taxonomies][{taxonomy_name}][metas][{meta_name}]";
		
		?>
		<div class="meta-row">
			<span class="column-1">
				<div class="button remove">Remove</div>
				<input	type="text"
						class="replace meta-name"
						name="<?php echo "{$settings_path}[name]"; ?>"
						value="<?php echo $meta_name; ?>"
						placeholder="meta name" />
			</span>
			<span class="column-2">
				<input	type="text"
						class="replace delimiter"
						name="<?php echo "{$settings_path}[delimiter]"; ?>"
						value="<?php echo $meta['delimiter']; ?>"
						placeholder="delimiter" />
			</span>
			<span class="column-3">
				
				<?php $this->handle_as_template( $meta_name, $meta, $settings_path ); ?>
				
			</span>
		</div>
	<?php }
	
	private function taxonomy_template( $taxonomy ) { ?>
		<div class="taxonomy" id="<?php echo $taxonomy ?>">
						
			<h2><?php echo $taxonomy ?></h2>
			
			<div>
				<span class="column-1">Source</span>
				<span class="column-2">Delimiter</span>
				<span class="column-3">Handle as</span>
			</div>
			
			<div>
			<?php foreach ( array('title', 'caption', 'description', 'alt') as $v ): ?>
				<?php
					$settings = array();
					if( isset( $this->options['taxonomies'][ $taxonomy ] ) ) {
						$settings = $this->options['taxonomies'][ $taxonomy ][ $v ];
					}
					
					$settings_path = "{$this->prefix}[taxonomies][{$taxonomy}][$v]";
					$checked = isset( $this->options['taxonomies'][$taxonomy][$v]['enable'] ) ? $this->options['taxonomies'][$taxonomy][$v]['enable'] : '';
				?>
				<div>
					<span class="column-1">
						<input	type="checkbox"
								class="replace enable"
								name="<?php echo "{$settings_path}[enable]"; ?>"
								<?php checked( 'on', $checked ); ?>>
						<?php echo ucfirst( $v ); ?>
					</span>
					<span class="settings" <?php echo $checked ? '' : 'style="visibility:hidden"' ; ?> >
						<span class="column-2">
							<input	type="text" class="replace delimiter"
									name="<?php echo "{$settings_path}[delimiter]"; ?>"
									value="<?php echo isset( $this->options['taxonomies'][$taxonomy][$v]['delimiter'] ) ? $this->options['taxonomies'][$taxonomy][$v]['delimiter'] : ''; ?>"
									placeholder="delimiter" />
						</span>
						<span class="column-3">
							
							<?php $this->handle_as_template( $v, $settings, $settings_path ); ?>
							
						</span>
					</span>
				</div>
			<?php endforeach ?>
			</div>
			
			<div class="button add-taxonomy-meta">
				Add meta source
			</div>
			
			<div class="metas">
			<?php $metas = isset( $this->options['taxonomies'][$taxonomy]['metas'] ) ? $this->options['taxonomies'][$taxonomy]['metas'] : array(); ?>
			<?php foreach ($metas as $meta): ?>
				<?php $this->taxonomy_meta_template( $taxonomy, $meta['name'] ) ?>
			<?php endforeach ?>
			</div>
		</div>
		
	<?php }
	
}

// instantiate the plugin
$attachment_cruncher = new Attachment_Cruncher();

?>