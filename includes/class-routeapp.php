<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://route.com/
 * @since      1.0.0
 *
 * @package    Routeapp
 * @subpackage Routeapp/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Routeapp
 * @subpackage Routeapp/includes
 * @author     Route App <support@route.com>
 */
class Routeapp {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Routeapp_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'ROUTEAPP_VERSION' ) ) {
			$this->version = ROUTEAPP_VERSION;
		    if ($version = get_option('_routeapp_version')) {
		        if ($version!=$this->version) {
		            update_option( '_routeapp_version', $this->version, 'yes' );
                    if (get_option('_routeapp_last_install_date')) {
                        update_option( '_routeapp_last_install_date', date("Y-m-d"), 'yes' );
                    } else {
                        add_option('_routeapp_last_install_date', date("Y-m-d"));
                    }
                    //clear old scheduled cronjobs
                    $this->clear_old_scheduled_cronjobs();
		        }
		    } else  {
		        add_option('_routeapp_version', $this->version);
                add_option('_routeapp_last_install_date', date("Y-m-d"));
		    }
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'routeapp';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_include_hooks();
		if ( !$this->routeapp_is_woocommerce_active() ) {
			add_action( 'admin_notices', array($this,'routeapp_admin_notice__error') );
		}
	}

    private function clear_old_scheduled_cronjobs() {
        if ( function_exists( 'wp_unschedule_hook' ) ) {
            wp_unschedule_hook('routeapp_check_for_missing_shippings');
            wp_unschedule_hook('routeapp_check_for_reconcile112version');
            wp_unschedule_hook('routeapp_check_for_failed_orders');
            wp_unschedule_hook('routeapp_check_for_weekly_missing_shippings');
        }
    }

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Routeapp_Loader. Orchestrates the hooks of the plugin.
	 * - Routeapp_i18n. Defines internationalization functionality.
	 * - Routeapp_Admin. Defines all hooks for the admin area.
	 * - Routeapp_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * Check dependency of woocommerce
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-dependencies.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-loader.php';

        /**
		 * The class responsible for defining API calls functionality
		 * of the plugin.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-api-client.php';

        /**
		 * The class responsible for defining API calls functionality
		 * of the plugin.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-api-compatibility.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-i18n.php';

        /**
         * The class responsible for defining all admin notice messages
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-routeapp-notice.php';

        /**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-routeapp-admin.php';

        /**
         * The class responsible for handling webhook upserts.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-routeapp-webhooks.php';

        /**
		 * The class responsible for defining all the plugin integrations
		 */
		require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-integrations.php';    

		/**
		 * The class responsible for defining all the supported tracking plugins
		 */
		require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-tracking.php';

        /**
         * The class responsible for sending logs to Sentry
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-logger-sentry.php';
		
		/**
		 * The class responsible for defining the interface for the shipment tracking plugins
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-interface-tracking-providers.php';

        /**
         * The class that has common functions for shipment tracking plugins
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-common-tracking-providers.php';

		/**
		 * The class responsible for integrate the WooCommerce Shipment Tracking plugin
		 */
		require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-woocommerce-shipment-tracking.php';

        /**
         * The class responsible for integrate the Blazing Shipment Tracking plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-blazing-shipment-tracking.php';

        /**
         * The class responsible for integrate the Mimo Shipment Tracking plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-mimo-shipment-tracking.php';

        /**
         * The class responsible for integrate the Woo Orders Tracking plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-woo-orders-tracking.php';

        /**
         * The class responsible for integrate the Aftership Shipment Tracking plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-aftership-shipment-tracking.php';

        /**
         * The class responsible for integrate the YiTH WooCommerce Tracking Order plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-yith-woocommerce-tracking-order.php';

        /**
         * The class responsible for integrate the Woocommerce Advanced Shipment Tracking plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-astracker-shipment-tracking.php';
        
        /**
         * The class responsible for integrate the Shipping Details Tracking plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-wooshippinginfo-tracking.php';

        /**
         * The class responsible for integrate the WooCommerce Shipment Tracking Pro plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-woocommerce-shipment-tracking-pro.php';

        /**
         * The class responsible for integrate the Elex USPS Shipping plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-usps-woocommerce-shipping.php';

        /**
         * The class responsible for integrate the Jetpack plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-jetpack.php';

        /**
         * The class responsible for integrate the ShippingEasy plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-shippingeasy.php';

        /**
         * The class responsible for integrate the ShippingEasy Custom plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-shippingeasycustom.php';

        /**
         * The class responsible for integrate the ShipStation plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-shipstation.php';

        /**
         * The class responsible for integrate the Shipworks plugin
         */
        require plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tracking-providers/class-routeapp-shipworks.php';

        /**
		* The class responsible for defining Woocommerce cron jobs functionality
		* of the plugin.
		*/
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-cron-schedules.php';

		/**
		* The class responsible for defining Woocommerce core functionality
		* of the plugin.
		*/
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-woocommerce.php';

        /**
        * The class responsible for adding or removing Route Fee on Dashboard
        * of the plugin.
        */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-admin-route-fee.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-routeapp-public.php';
    
        /**
         * This class is responsible to create user and merchant account if it's not defined
         */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-routeapp-setup.php';

        /**
         * The class responsible for defining Woocommerce ajax functionality
         * of the plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-routeapp-order-recover.php';

        $this->loader = new Routeapp_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Routeapp_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Routeapp_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$routeapp_admin = new Routeapp_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $routeapp_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $routeapp_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_notices', $routeapp_admin, 'display_admin_notices' );
		
		$classes = array(
			'Routeapp_Notice',
		);
		
		foreach ($classes as $class) {
			 new $class();
		}

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		
		global $routeapp_public;
		$routeapp_public = new Routeapp_Public( $this->get_plugin_name(), $this->get_version() );
	}

	/**
	 * [routeapp_admin_notice__error description]
	 * @return [type] [description]
	 */
	public function routeapp_admin_notice__error() {
		$class = 'notice notice-error';
		$message = __( 'Routeapp is enabled but not effective. It requires WooCommerce in order to work.', 'routeapp' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
	}

	 /**
	 * Register all of the hooks related to the includes facing funcionality
	 * of the plugin.
	 * 
	 * @since 1.0.1
	 */
	public function define_include_hooks() {
		$classes = array(
			'Routeapp_Woocommerce',
         'Routeapp_Cron_Schedules',
         'Routeapp_Plugin_Integrations',
         'Routeapp_Order_Recover'
		);
		
		foreach ($classes as $class) {
			 new $class();
		}
   }

	/**
	 * [routeapp_is_woocommerce_active description]
	 * @return [type] [description]
	 */
	private function routeapp_is_woocommerce_active() {
		return WC_GST_Dependencies::fn_woocommerce_active_check();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Routeapp_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
