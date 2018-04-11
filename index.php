<?php

/*
Plugin Name: WP SVG Support
Plugin URI: https://github.com/mcguffin/wp-svg-support
Description: Adds SVG upload and editing support to Wordpress.
Author: Jörn Lund
Version: 1.0.0
Author URI: https://github.com/mcguffin/
License: GPL3

Text Domain: wp-svg-support
Domain Path: /languages/
*/

/*  Copyright 2014  Jörn Lund

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if ( ! class_exists( 'SvgSupport' ) ):
class SvgSupport {
	private static $_instance = null;

	/**
	 * Getting a singleton.
	 *
	 * @return object single instance of SvgSupport
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		add_action( 'plugins_loaded' , array( $this, 'load_textdomain' ) );
		add_action( 'init' , array( $this, 'init' ) );
		add_filter( 'wp_image_editors' , array( $this, 'add_svg_editor' ) );
		add_filter( 'mime_types', array( $this, 'allow_svg_mime_type' ) );
		add_filter( 'wp_generate_attachment_metadata' , array( $this, 'svg_generate_metadata' ) , 10 , 2 );
		add_filter( 'wp_get_attachment_metadata' , array( $this, 'svg_get_attachment_metadata' ) , 10 , 2 );
		add_filter( 'file_is_displayable_image' , array( $this, 'svg_is_displayable_image' ) , 10 , 2 );
		add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'calculate_image_srcset_meta' ), 10, 4 );
		add_filter( 'wp_calculate_image_sizes', array( $this, 'calculate_image_sizes' ), 10, 5 );

		// hide unsuported features
		// add_filter( 'admin_body_class' , array( $this, 'admin_body_class' ) );
		// add_action( 'admin_print_scripts' , array( $this, 'admin_print_scripts' ) );

// 		register_activation_hook( __FILE__ , array( __CLASS__ , 'activate' ) );
// 		register_deactivation_hook( __FILE__ , array( __CLASS__ , 'deactivate' ) );
// 		register_uninstall_hook( __FILE__ , array( __CLASS__ , 'uninstall' ) );
	}

	function calculate_image_srcset_meta( $image_meta, $size_array, $image_src, $attachment_id ) {
		if ( 'svg' === pathinfo( $image_src, PATHINFO_EXTENSION ) ) {
			return false;
		}
		return $image_meta;
	}
	function calculate_image_sizes( $sizes, $size, $image_src, $image_meta, $attachment_id ) {
		
		if ( 'svg' === pathinfo( $image_src, PATHINFO_EXTENSION ) ) {
			return false;
		}
		return $sizes;

	}
	/**
	 * Hide unsupported image editor buttons
	 *
	 * @action 'admin_print_scripts'
	 */
	function admin_print_scripts(){
		?><style type="text/css">
			.post-type-attachment.edit-attachment-svg .imgedit-flipv,
			.post-type-attachment.edit-attachment-svg .imgedit-fliph,
			.post-type-attachment.edit-attachment-svg .imgedit-rleft,
			.post-type-attachment.edit-attachment-svg .imgedit-rright {
				display:none;
			}
		</style><?php
	}
	/**
	 * @filter 'admin_body_class'
	 */
	function admin_body_class( $class = '' ) {
		if ( ( $post = get_post() ) && 'image/svg+xml' == $post->post_mime_type )
			$class .= ' edit-attachment-svg';
		return $class;
	}

	/**
	 * @filter 'wp_get_attachment_metadata'
	 */
	function svg_get_attachment_metadata( $data, $post_id ) {
		if ( ! $data ) {
			if ( !$post = get_post( $post_id ) )
				return false;
			// load base class
			_wp_image_editor_choose();
			require_once plugin_dir_path(__FILE__) . '/include/class-wpsvg-image-editor-svg.php';
			$file = get_attached_file( $post_id, true );
			$editor = new WPSVG_Image_Editor_SVG( $file );
			return $editor->get_size();
		}
		return $data;
	}

	function svg_is_displayable_image( $result , $path ) {
		return pathinfo( $path , PATHINFO_EXTENSION ) == 'svg' || $result;
	}

	/**
	 * Adds SVG Editor Class
	 *
	 * @filter 'wp_image_editors'
	 */
	function add_svg_editor( $editors ) {
		require_once plugin_dir_path(__FILE__) . '/include/class-wpsvg-image-editor-svg.php';
		require_once plugin_dir_path(__FILE__) . '/include/simplexml-tools.php';
		array_unshift($editors,'WPSVG_Image_Editor_SVG');
		return $editors;
	}
	/**
	 * Allow SVG uploads
	 *
	 * @filter 'mime_types'
	 */
	function allow_svg_mime_type($mimes) {
// 		if ( current_user_can( 'unfiltered_upload' ) )
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}


	/**
	 * Generate Metadata for SVG Uplaods
	 *
	 * @filter 'wp_generate_attachment_metadata'
	 */
	function svg_generate_metadata( $metadata , $attachment_id ) {
		$post = get_post($attachment_id);
		if ( 'image/svg+xml' == $post->post_mime_type ) {
			// get file source
			$file = get_attached_file( $attachment_id );
			$updir = wp_upload_dir();
			$file_in_updir = str_replace( trailingslashit( $updir['basedir']), '', $file );
			if ( file_exists( $file ) ) {
				$xml = simplexml_load_file($file );
				$xml_attr = $xml[0]->attributes();
				$width  = intval(strval($xml_attr['width']));
				$height = intval(strval($xml_attr['height']));
				if ( $width && $height ) {
					$metadata['width'] 	= $width;
					$metadata['height'] = $height;
					$metadata['file'] = $file_in_updir;

					global $_wp_additional_image_sizes;

					$sizes = array();
					foreach( get_intermediate_image_sizes() as $s ) {
						// as svg files scale seamlessly the file array element should be just our source svg filename.
						$sizes[$s] = array( 'width' => '', 'height' => '', 'crop' => false , 'file' => pathinfo($file_in_updir , PATHINFO_BASENAME ) );

						// BEGIN copy-pasted from wp-admin/includes/image.php function wp_generate_attachment_metadata()
						if ( isset( $_wp_additional_image_sizes[$s]['width'] ) )
							$sizes[$s]['width'] = intval( $_wp_additional_image_sizes[$s]['width'] ); // For theme-added sizes
						else
							$sizes[$s]['width'] = get_option( "{$s}_size_w" ); // For default sizes set in options
						if ( isset( $_wp_additional_image_sizes[$s]['height'] ) )
							$sizes[$s]['height'] = intval( $_wp_additional_image_sizes[$s]['height'] ); // For theme-added sizes
						else
							$sizes[$s]['height'] = get_option( "{$s}_size_h" ); // For default sizes set in options
						if ( isset( $_wp_additional_image_sizes[$s]['crop'] ) )
							$sizes[$s]['crop'] = $_wp_additional_image_sizes[$s]['crop']; // For theme-added sizes
						else
							$sizes[$s]['crop'] = get_option( "{$s}_crop" ); // For default sizes set in options
					}
					// END copy-pasted from wp-admin/includes/image.php function wp_generate_attachment_metadata()
					$metadata['sizes'] = $sizes;
				}
			}
		}
		return $metadata;
	}

	/**
	 * Load text domain
	 *
	 * @action 'plugins_loaded'
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-svg-support' , false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	/**
	 * Init hook.
	 *
	 * @action 'init'
	 */
	function init() {
	}



	/**
	 *	Fired on plugin activation
	 */
	public static function activate() { }

	/**
	 *	Fired on plugin deactivation
	 */
	public static function deactivate() { }

	/**
	 *	Fired on plugin uninstall
	 */
	public static function uninstall(){ }

}
SvgSupport::get_instance();

endif;
