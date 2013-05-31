=== Attachment Cruncher ===
Contributors: PeterHudec
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NNPNMTTULB3AS
Tags: media, attachment, attachments, keywords, tags, taxonomy, taxonomies, post, posts, processing
Requires at least: 2.7
Tested up to: 3.5.1
Stable tag: 0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A Swiss Army Knife for transfering media attachment properties to post properties.

== Description ==

This plugin was created as a response to
[pupular demand on the Image Metadata Cruncher support forum](http://wordpress.org/support/topic/keywords-tags),
where people were asking for a solution to convert **image metadata keywords** created with
Adobe Ligtroom, Photo Mechanic, Fotostation or whatever tool
to **post tags** or **categories**.

**Attachment Cruncher** can transfer these **attachment** properties:

* title
* caption
* description
* alt
* meta fields

To these **post** properties:

* title
* excerpt
* content
* meta fields
* categories, tags and any other taxonomy

You can choose whether it should take into account:

* Media attached to the post.
* Media inserted to the post content.

You can choose when the transfer should happen:

* When the post is being saved.
* When an attachment attached to the post is being added or saved.
* When an attachment is being attached to a post.

You can use the plugin in **conjunction with other plugins**.
If you for example want to convert IPTC keywords of an image to tags of the post to which the image is attached,
use first [Image Metadata Cruncher](http://wordpress.org/plugins/image-metadata-cruncher/)
to extract the keywords from the image to
a custom attachment meta field (or any other attachment property) and then use
**Attachment Cruncher** to convert the keywords stored in the custom meta to tags or
categories of the post to which the image is attached.

You can also call the plugin public methods from your theme's code:
	
	$attachment_cruncher->crunch( $post_ID );


== Installation ==

Copy the **attachment-cruncher** folder into the plugins directory and activate.

== Screenshots ==

1. Plugin Settings

== Changelog ==

= 0.3 =
* Works now also with PHP 5.2.

= 0.2 =
* Removed default post meta "a" and "b".

= 0.1 =
* Initial version


