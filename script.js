(function($){
	$(function(){
		
		$('#show-debug-info').on('change', function(e){
			if($(this).attr('checked')){
				$('#debug-info').slideDown('slow');
			}else{
				$('#debug-info').slideUp('slow');
			}
		});
		
		/**
		 * Hide meta input if meta not selected.
		 */
		$('.wrap.attachment-cruncher').on('change', 'select.source', function(e){
			var $dropbox = $(this);
			var $meta = $dropbox.parent().find('.meta');
			var $settings = $dropbox.parent().find('.settings');
			
			if ($dropbox.attr('value')){
				$settings.show();
			} else {
				$settings.hide();
			}
			
			if ($dropbox.attr('value') == 'meta'){
				$meta.show();
			} else {
				$meta.hide();
			}
		});
		
		
		/**
		 * Show the source options only if enabled. 
		 */
		$('.wrap.attachment-cruncher').on('change', 'input:checkbox.enable', function(e){
			var checked = $(this).attr('checked');
			
			var $settings = $(this).parent().parent().find('span.settings');
			
			// Used visibility to prevent collapsing of the rows.
			if (checked) {
				$settings.css("visibility","visible");
			} else{
				$settings.css("visibility","hidden");
			};
			
			console.log(value);
		});
		
		
		/**
		 * Show parameter only wen needed. 
		 */
		$('.wrap.attachment-cruncher').on('change', 'select.multiple', function(e){
			var value = $(this).val();
			var $param = $(this).parent().find('.param');
			
			if (value == 'concat') {
				$param.css('visibility', 'visible');
				$param.attr('placeholder', 'glue');
			} else if (value == 'contains') {
				$param.css('visibility', 'visible');
				$param.attr('placeholder', 'search value');
			} else{
				$param.css('visibility', 'hidden');
			};
			
			console.log(value);
		});
		$('.wrap.attachment-cruncher select.multiple').change();
		
		
		/**
		 * Show the "Create if not found checkbox" only if handle as names selected. 
		 */
		$('.wrap.attachment-cruncher').on('change', 'input:radio.handle-as', function(e){
			var value = $(this).val();
			var $create = $(this).parent().find('span.create');
			if (value == 'names') {
				$create.show();
			} else{
				$create.hide();
			};
			
			console.log(value);
		});
			
		/**
		 * Add post meta
		 */
		$('.wrap.attachment-cruncher').on('click', '.add-post-meta', function(e){
			$('.wrap.attachment-cruncher .post-metas').append($('.post-meta-template').html());
		});
				
		/**
		 * Add taxonomy meta
		 */
		$('.wrap.attachment-cruncher').on('click', '.add-taxonomy-meta', function(e){
			e.preventDefault();
			var id = new Date().getTime();
			
			var html = $('.add-meta-template').html();
			
			html = html.replace(/{{{.*}}}/g, '{{{' + id + '}}}');
			
			console.log(this);
			
			$(this).next().append(html);
		});
		
		/**
		 * Add taxonomy
		 */
		$('.wrap.attachment-cruncher .add-taxonomy').on('click', function (e){
			$('.wrap.attachment-cruncher .taxonomies').append($('.add-taxonomy-template').html());
		});
		
		/**
		 * Remove post meta.
		 */
		$('.wrap.attachment-cruncher').on('click', '.remove-post-meta', function(e) {
			$(this).parent().remove();
		});
		
		/**
		 * Remove taxonomy meta.
		 */
		$('.wrap.attachment-cruncher').on('click', '.remove', function(e) {
			$(this).parent().parent().remove();
		});
		
		/**
		 * Remove custom taxonomy
		 */		
		$('.wrap.attachment-cruncher').on('click', '.remove-taxonomy', function(e) {
			$(this).parent().parent().remove();
		});
		
		
		/**
		 * Handle changed meta name.
		 */
		$('#ac-form').on('submit', function(e){
						
			//e.preventDefault();
			
			// Check whether the form is OK.
			if($('#allow-submit').val() == 0){
				e.preventDefault();
				alert('Please fix the red marked errors!');
				return
			}
			
			// Replace {post_meta_name} tags
			$('.wrap.attachment-cruncher .post-metas .post-meta').each(function(index) {
				
				var metaName = $(this).children('.post-meta-name').val();
				
				$(this).find('.replace').each(function(){
					$(this).attr('name', $(this).attr('name').replace('{post_meta_name}', metaName));
					
					$(this).css('background-color', 'red !important');
					
					console.log(this);
				});
				
				if(!metaName){
					$(this).remove();
				}			
			});
			
			
			// Replace {taxonomy_name} tags
			$('.wrap.attachment-cruncher .taxonomies .taxonomy').each(function(index) {
				
				var taxonomyName = $(this).attr('id');
				
				console.log('taxonomy:', taxonomyName);
				
				$(this).find('.replace').each(function(index) {
					var name = $(this).attr('name');
					name = name.replace('{taxonomy_name}', taxonomyName);
					$(this).attr('name', name);
				});
				
				// remove taxonomies without name
				if(!taxonomyName){
					console.log('empty:', taxonomyName);
					$(this).remove();
				}
				
			});
			
			// Loop through taxonomy metas.
			$('.add-taxonomy-meta').each(function(index) {
				
				var settingName = $(this).parent().attr('id');
				
				$(this).parent().find('.replace').each(function(index){
					
					var name = $(this).attr('name');
					name = name.replace('{setting_name}', settingName);
					
					$(this).attr('name', name);
				});
				
				// Loop through meta rows.
				$(this).next().find('.meta-row').each(function(index) {
					
					var metaName = $(this).find('.meta-name').val();
					
					// Loop through all elements whose names need treatment.
					$(this).find('.replace').each(function(index) {
						// replace all {setting_name} and {meta_name} tags
						var nameAttr = $(this).attr('name');
						
						nameAttr = nameAttr.replace(/{{{.*}}}/g, '');
						
						nameAttr = nameAttr.replace('{meta_name}', metaName);
						
						$(this).attr('name', nameAttr);
					});
					
					// remove taxonomy metas without name
					if(!metaName){
						$(this).remove();
					}
					
				});
			});
		});
	});
})(jQuery);
