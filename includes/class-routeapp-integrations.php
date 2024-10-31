<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Routeapp_Plugin_Integrations {

	public static $plugin_integrations = array();

	public static function setup() {
		$compatibilities_folder = [ 'tracking', 'checkout' ];
		foreach ( $compatibilities_folder as $folder ) {
			foreach ( glob( plugin_dir_path( __FILE__ ) . '../integrations/' . $folder . '/*.php' ) as $filename ) {
				require_once( $filename );
			}
		}

	}

	public static function register( $object, $slug ) {
		self::$plugin_integrations[ $slug ] = $object;
	}

	public static function get_compatibility_class( $slug ) {
		return ( isset( self::$plugin_integrations[ $slug ] ) ) ? self::$plugin_integrations[ $slug ] : false;
	}
}

Routeapp_Plugin_Integrations::setup();