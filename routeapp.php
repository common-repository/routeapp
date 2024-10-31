<?php
/**
 *
 * @link              https://route.com/
 * @since             1.0.0
 * @package           Routeapp
 *
 * @wordpress-plugin
 * Plugin Name:       Route App
 * Plugin URI:        https://route.com/for-merchants/
 * Description:       Route allows shoppers to insure their orders with one-click during checkout, adding a layer of 3rd party trust while improving the customer shopping experience.
 * Version:           2.2.21
 * Author:            Route
 * Author URI:        https://route.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       routeapp
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Currently plugin version.
 */
define( 'ROUTEAPP_VERSION', '2.2.21' );

/**
 *
 */
function activate_routeapp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-routeapp-activator.php';
	Routeapp_Activator::activate();
}

/**
 *
 */
function deactivate_routeapp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-routeapp-deactivator.php';
	Routeapp_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_routeapp' );
register_deactivation_hook( __FILE__, 'deactivate_routeapp' );

/**
 *
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-routeapp.php';

/**
 * Begins execution of the plugin.
 *
 *
 * @since    1.0.0
 */
function run_routeapp() {
	$routeapp = new Routeapp();
	$routeapp->run();
}
run_routeapp();
