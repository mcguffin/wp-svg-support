<?php

/*
Plugin Name: WP SVG Support
Plugin URI: http://wordpress.org/
Description: Enter description here.
Author: Jörn Lund
Version: 1.0.0
Author URI: 
License: GPL3

Text Domain: svg_support
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
		add_action( 'plugins_loaded' , array( &$this , 'load_textdomain' ) );
		add_action( 'init' , array( &$this , 'init' ) );
		add_action( 'wp_enqueue_scripts' , array( &$this , 'enqueue_assets' ) );
		register_activation_hook( __FILE__ , array( __CLASS__ , 'activate' ) );
		register_deactivation_hook( __FILE__ , array( __CLASS__ , 'deactivate' ) );
		register_uninstall_hook( __FILE__ , array( __CLASS__ , 'uninstall' ) );
	}

	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'svg_support' , false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	/**
	 * Init hook.
	 * 
	 *  - Register assets
	 */
	function init() {
	}
	/**
	 *  Enqueue Frontend Scripts
	 */
	function enqueue_assets() {	
		wp_enqueue_script( 'svg_support' );
		wp_enqueue_style( 'svg_support' );
	}




	/**
	 *	Fired on plugin activation
	 */
	public static function activate() {
	
	
	}

	/**
	 *	Fired on plugin deactivation
	 */
	public static function deactivate() {
	}
	/**
	 *
	 */
	public static function uninstall(){




	}

}
SvgSupport::get_instance();

endif;

