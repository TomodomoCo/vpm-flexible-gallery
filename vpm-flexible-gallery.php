<?php
/*
Plugin Name: VPM Flexible Gallery
Plugin URI: http://www.vanpattenmedia.com/
Description: Custom WordPress image galleries with many available filters for layout overrides.
Version: 1.0.1
Author: Van Patten Media Inc.
Author URI: https://www.vanpattenmedia.com/
*/


add_filter( 'post_gallery', 'vpm_custom_gallery_output', 10, 3 );

/**
 * Custom gallery output for better image quality control
 *
 * @param  string   $output
 * @param  array    $attr
 * @param  int|null $instance
 * @return string   $output
 */
function vpm_custom_gallery_output( $output, $attr, $instance = null ) {
	global $post;

	// Set to active post_id if no instance present
	if ( ! isset( $instance ) || is_null( $instance ) ) {
		$instance = $post->ID;
	}

	// Extract the shortcode attributes
	$defaults = array(
		'order'      => 'ASC',
		'orderby'    => 'menu_order ID',
		'id'         => $instance,
		'itemtag'    => 'dl',
		'icontag'    => 'dt',
		'captiontag' => 'dd',
		'columns'    => 2,
		'size'       => 'thumbnail', // defaults to thumbnail size
		'include'    => '',
		'exclude'    => '',
	);

	// Parse the args
	$args = shortcode_atts( $defaults, $attr );

	// Assign from passed args and default args
	$order   = $args['order'];
	$orderby = $args['orderby'];
	$columns = $args['columns'];
	$size    = $args['size'];
	$include = $args['include'];

	// Ensure the instance is an integer
	$instance = intval( $instance );

	// Fetch attachments
	if ( ! empty( $include ) ) {
		$include     = preg_replace( '/[^0-9,]+/', '', $include );
		$attachments = array();

		$raw_attachments = get_posts( array(
			'include'        => $include,
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'order'          => $order,
			'orderby'        => $orderby
		) );

		// Grab attachment IDs
		foreach( $raw_attachments as $key => $val ) {
			$attachments[ $val->ID ] = $raw_attachments[$key];
		}
	}

	// Bail early if no attachments from fetch
	if ( empty( $attachments ) ) {
		return '';
	}

	// Define layout args
	$selector   = "gallery-{$instance}"; // unique selector for CSS
	$size_class = sanitize_html_class( $size ); // for CSS purposes

	// Gallery output
	$inner_html = '';

	// Loop attachments
	$i = 0;
	foreach( $attachments as $id => $attachment ) {

		// Fetch full url, make cropped URL
		$img_full = wp_get_attachment_image_src( $id, 'full' );
		$src_full = apply_filters( 'vpm_gallery_image_src_full', $img_full[0], $id );

		// Fetch specific size
		$img = wp_get_attachment_image_src( $id, $size );
		$src = apply_filters( 'vpm_gallery_image_src', $img_full[0], $id, $size );

		// Optional caption support
		if ( $attachment->post_excerpt ) {
			$caption_html = apply_filters( 'vpm_gallery_image_caption_markup', '<span class="wp-caption caption">' . $attachment->post_excerpt . '</span>' );
		} else {
			$caption_html = false;
		}

		// Output of image item; default WP structure (dl.gallery-item > dt.gallery-icon > a > img)
		$gallery_item_html = apply_filters( 'vpm_gallery_item_markup', '<dl class="gallery-item"><dt class="gallery-icon">%1$s%2$s</dt></dl>' );
		$gallery_img_html  = apply_filters( 'vpm_gallery_img_markup', '<a href="%1$s"><img src="%2$s" class="attachment-thumbnail"></a>' );

		// Build the Image HTML
		$img_html  = sprintf( $gallery_img_html, $src_full, $src );

		// Build the image wrapper HTML
		if ( $caption_html !== false ) {
			$item_html = sprintf( $gallery_item_html, $img_html, $caption_html );
		} else {
			$item_html = sprintf( $gallery_item_html, $img_html, false );
		}

		// Append gallery item HTML
		$inner_html .= $item_html;

		// Add clear break div after every second image via modulo operator
		if ( $columns > 0 && ++$i % $columns == 0 ) {
			$inner_html .= '<br style="clear: both" />';
		}
	}

	// Wrap the output
	$wrapper_html = '<div id="%1$s" class="gallery gallery-id-%2$s gallery-columns-%3$s gallery-size-%4$s">%5$s</div>';
	$output = sprintf( $wrapper_html, $selector, $instance, $columns, $size_class, $inner_html );

	return $output;
}
