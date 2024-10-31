<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://route.com/
 * @since      1.0.0
 *
 * @package    Routeapp
 * @subpackage Routeapp/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 *
 * @package    Routeapp
 * @subpackage Routeapp/admin
 * @author     Route App <support@route.com>
 */
class Routeapp_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

    private $routeapp_webhooks;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->routeapp_webhooks = new Routeapp_Webhooks();

        add_filter( 'woocommerce_get_settings_pages', array( $this, 'routeapp_add_settings' ), 15 );
        add_filter( 'woocommerce_get_settings_pages', array( $this, 'routeapp_add_order_recover_settings' ), 15 );
		add_filter( 'plugin_action_links', array( $this, 'get_action_links' ), 10, 2 );
		add_filter( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ));

        //check webhooks
        add_action('admin_init', [$this, 'upsert_webhooks']);

	}


    private function _is_routedata_complete()
    {
        $merchant_id = get_option('routeapp_merchant_id', false);
        $secret_token = get_option('routeapp_secret_token', false);
        return $merchant_id && $secret_token;
    }

    public function upsert_webhooks() {
        //only check for webhooks if we have merchant id and secret token on database
        if ($this->_is_routedata_complete()) {
            $upsertWebhooks = get_option('_routeapp_webhooks_created', false);
            //only upsert webhooks if we cannot find flag
            if (!$upsertWebhooks) {
                $this->routeapp_webhooks->upsert_webhooks();
            }
        }
    }

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/routeapp-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/routeapp-admin.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Create settings tab in Woocommerce settings 
	 *
	 * @since    1.0.0
	 */
    public function routeapp_add_settings($settings) {
        require_once plugin_dir_path( __FILE__ ) . 'class-wc-settings-routeapp.php';
        $retArray = [];
        if (is_object($settings)) {
            $retArray[] = $settings;
        } else {
            $retArray = $settings;
        }
        $retArray[] = new WC_Settings_Routeapp();

        return $retArray;
    }

    /**
     * Create order recover settings tab in Woocommerce settings
     *
     */
    public function routeapp_add_order_recover_settings($settings) {
        require_once plugin_dir_path( __FILE__ ) . 'class-wc-settings-routeapp-order-recover.php';
        $retArray = [];
        if (is_object($settings)) {
            $retArray[] = $settings;
        } else {
            $retArray = $settings;
        }
        $retArray[] = new WC_Settings_Routeapp_Order_Recover();
        return $retArray;
    }


    /**
	 * Create extra action links to Plugin settings
	 *
	 * @since    1.0.0
	 */
    public function get_action_links( $links, $file ) {
        $base = explode( '/', plugin_basename( __FILE__ ) );
        $file = explode( '/', $file );

        if( $base[0] === $file[0] ) {
            $extraLinks = array(
                sprintf('<a href="%s">%s</a>', admin_url( 'admin.php?page=wc-settings&tab=routeapp' ), __( 'Settings', 'routeapp' ))
            );
            $links = array_merge($links, $extraLinks);
        }

        return (array) array_reverse($links);
	}
	
	public function display_admin_notices() {

		$screen = get_current_screen();

		if ( $screen->id === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'routeapp' ) {

			$class = 'notice notice-info';
			$message = __( 'Open the <a href="https://dashboard.routeapp.io/" target="_blank">Route Partner Portal</a> to view claim details.', 'routeapp' );

			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );

		}
	}

}
