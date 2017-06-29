<?php
/*
Plugin Name: VPM Flexible Gallery
Plugin URI: http://www.vanpattenmedia.com/
Description: Custom WordPress image galleries with many available filters for layout overrides.
Version: 1.0
Author: Van Patten Media Inc.
Author URI: https://www.vanpattenmedia.com/
*/


add_filter( 'post_gallery', 'vpm_custom_gallery_output', 10, 2 );

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

	// Filter the defaults
	$defaults = apply_filters( 'vpm_gallery_defaults', $defaults );

	// Parse the args
	$args = shortcode_atts( $defaults, $attr );

	// TODO replace extract
	extract( $args );

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
			'order'          => isset( $order ) ? $order : 'ASC',
			'orderby'        => isset( $orderby ) ? $orderby : 'menu_order'
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
	$columns    = $args['columns']; // default to 3 columns if not set
	$item_width = $columns > 0 ? floor( 100 / $columns ) : 100;
	$size_class = sanitize_html_class( $args['size'] ); // for CSS purposes

	// Gallery output
	$inner_html = '';

	// Loop attachments
	$i = 0;
	foreach( $attachments as $id => $attachment ) {

		// Fetch full url, make cropped URL
		$img_full = wp_get_attachment_image_src( $id, 'full' );
		$src_full = apply_filters( 'vpm_gallery_image_src_full', $img_full[0], $id );

		// Fetch specific size
		$img = wp_get_attachment_image_src( $id, isset( $size ) ? $size : 'full' );
		$src = apply_filters( 'vpm_gallery_image_src', $img_full[0], $id, $size );

		// Output of image item; default WP structure (dl.gallery-item > dt.gallery-icon > a > img)
		$gallery_item_html = apply_filters( 'vpm_gallery_item_markup', '<dl class="gallery-item"><dt class="gallery-icon">%1$s</dt></dl>' );
		$gallery_img_html  = apply_filters( 'vpm_gallery_img_markup', '<a href="%1$s"><img src="%2$s" class="attachment-thumbnail"></a>' );

		// Build the HTML
		$img_html  = sprintf( $gallery_img_html, $src_full, $src );
		$item_html = sprintf( $gallery_item_html, $img_html );

		// Append gallery item html
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
