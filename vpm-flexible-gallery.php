<?php
/*
Plugin Name: VPM Flexible Gallery
Plugin URI: http://www.vanpattenmedia.com/
Description: Custom WordPress image galleries with many available filters for layout overrides.
Version: 1.0
Author: Van Patten Media Inc.
Author URI: https://www.vanpattenmedia.com/
*/

class VpmFlexibleGallery {

	function __construct() {
		add_filter( 'media_view_settings', array( $this, 'vpm_set_default_gallery_thumbnail_size' ) );
		add_filter( 'post_gallery', array( $this, 'vpm_custom_gallery_output' ), 10, 2 );

		add_filter( 'vpm_gallery_image_src', array( $this, 'vpm_override_gallery_image_src' ), 10, 2 );
	}

	/**
	 * Assigns the thumbnail image size to be used for gallery thumbnails by default
	 *
	 * @param array $settings
	 * @return array $settings
	 */
	function vpm_set_default_gallery_thumbnail_size( $settings ) {
		$settings['galleryDefaults']['size']    = 'thumbnail';
		$settings['galleryDefaults']['columns'] = 3;

		return $settings;
	}

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
		extract( shortcode_atts( array(
			'order'      => 'ASC',
			'orderby'    => 'menu_order ID',
			'id'         => $instance,
			'itemtag'    => 'dl',
			'icontag'    => 'dt',
			'captiontag' => 'dd',
			'columns'    => apply_filters( 'vpm_gallery_column_count', 2 ),
			'size'       => isset( $attr['size'] ) ? $attr['size'] : 'thumbnail', // defaults to thumbnail size
			'include'    => '',
			'exclude'    => ''
		), $attr) );

		// Ensure the instance is an integer
		$instance = intval( $instance );

		// Fetch attachments
		if ( ! empty( $include ) ) {
			$include         = preg_replace( '/[^0-9,]+/', '', $include );
			$attachments     = array();

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
		$columns    = isset( $attr['columns'] ) ? intval( $attr['columns'] ) : 3; // default to 3 columns if not set
		$item_width = $columns > 0 ? floor( 100 / $columns ) : 100;
		$size_class = sanitize_html_class( isset( $attr['size'] ) ? $attr['size'] : 'thumbnail' ); // for CSS purposes

		// Gallery output
		$inner_html = '';

		// Loop attachments
		$i = 0;
		foreach( $attachments as $id => $attachment ) {

			// Fetch full url, make cropped URL
			$data = apply_filters( 'vpm_gallery_image_src', array( $this, 'vpm_override_gallery_image_src' ), $id );

			// Output of image item; default WP structure (dl.gallery-item > dt.gallery-icon > a > img)
			$gallery_item_html = apply_filters( 'vpm_gallery_item_markup', '<dl class="gallery-item"><dt class="gallery-icon">%1$s</dt></dl>' );
			$gallery_img_html  = apply_filters( 'vpm_gallery_img_markup', '<a href="%1$s"><img width="%3$s" height="%4$s" src="%2$s" class="attachment-thumbnail size-thumbnail" /></a>' );

			$img_html   = sprintf( $gallery_img_html, $data['full_size_url'], $data['cropped_url'], $data['w'], $data['h'] );
			$item_html  = sprintf( $gallery_item_html, $img_html );

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

	/**
	 * Allows an image URL/ID to be overriden
	 *
	 * @param int 		$id
	 * @return array 	$data
	 */
	function vpm_override_gallery_image_src( $id ) {
		$img  = wp_get_attachment_image_src( $id, 'full' );
		$args = array(
			'q'    => 60,
			'crop' => 'faces',
			'fit'  => 'crop',
			'w'    => 500,
			'h'    => 500,
		);

		$cropped = add_query_arg(
			apply_filters( 'vpm_gallery_thumbnail_cropping', $args ),
			$img[0]
		);

		$data = array(
			'cropped_url'	=> $cropped,
			'full_size_url' => $img[0],
			'width' 		=> $args['w'],
			'height' 		=> $args['h'],
		);

		return $data;
	}
}

new VpmFlexibleGallery;