<?php

/**
 * Fired during plugin activation
 *
 * @link       https://route.com/
 * @since      1.0.0
 *
 * @package    Routeapp
 * @subpackage Routeapp/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Routeapp
 * @subpackage Routeapp/includes
 * @author     Route App <support@route.com>
 */
class Routeapp_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-setup.php';
        Route_Setup::init();
    }
}
