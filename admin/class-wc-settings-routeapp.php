<?php
/**
 * Class WC_Settings_Routeapp file.
 *
 * @package Routeapp\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Settings_Routeapp' ) ) :
	/**
	 * Settings class
	 *
	 * @since 1.0.0
	 */
	class WC_Settings_Routeapp extends WC_Settings_Page {

        const PRINTIFY_SHIPPING_METHOD = 'printify_shipping';
        const PRINTIFY_SHIPPING_STANDARD = 'printify_shipping_s';
        const PRINTIFY_SHIPPING_EXPRESS = 'printify_shipping_e';

        /**
		 * Setup settings class
		 *
		 * @since  1.0
		 */
		public function __construct() {

			$this->id    = 'routeapp';
			$this->label = __( 'Route', 'routeapp' );
			add_filter( 'woocommerce_settings_tabs_array',        array( $this, 'add_settings_page' ), 20 );
			add_action( 'woocommerce_settings_' . $this->id,      array( $this, 'output' ) );
			add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
		}

        public static function get_route_public_instance(){
            global $routeapp_public;
            return $routeapp_public;
        }

		/**
		 * Get settings array
		 *
		 * @since 1.0.0
		 * @return array Array of settings
		 */
		public function get_settings() {

				/**
				 * Filter Plugin Settings
				 *
				 * @since 1.0.0
				 * @param array $settings Array of the plugin settings
				 */
                $tax_classes  = wc_get_product_tax_class_options();

                $shipping_methods_array = [];
                $shipping_methods = WC()->shipping->get_shipping_methods();
                foreach($shipping_methods as $shipping_method) {
                    if($shipping_method->id === self::PRINTIFY_SHIPPING_METHOD){
                        $shipping_methods_array[self::PRINTIFY_SHIPPING_STANDARD] = $shipping_method->method_title . ' Standard';
                        $shipping_methods_array[self::PRINTIFY_SHIPPING_EXPRESS] = $shipping_method->method_title . ' Express';
                        continue;
                    }
                    $shipping_methods_array[$shipping_method->id] = $shipping_method->method_title;
                }

                $routeapp_public = self::get_route_public_instance();
                $checkout_hooks = $routeapp_public->routeapp_get_checkout_hook_options();

				$settings = apply_filters( 'routeapp_settings', array(
					array(
						'name' => __( 'Route', 'routeapp' ),
						'type' => 'title',
						'desc' => '',
						'id'   => 'routeapp_route_insurance',
                    ),
					array(
						'name' => __( 'Public Token', 'routeapp' ),
						'type' => 'text',
						'desc' => "<p class='description'>Haven’t received your Tokens? <a href='https://dashboard.route.com/create-account' target='_blank'>Click here</a> to create your Route Partner Account.</p>",
                        'id'   => 'routeapp_public_token',
                        'class' => 'route-environment',
					),
					array(
						'name' => __( 'Secret Token', 'routeapp' ),
						'type' => 'text',
						'id'   => 'routeapp_secret_token',
                        'class' => 'route-environment',
					),
                    array(
                        'name' => __( 'Is Route Fee taxable', 'routeapp' ),
                        'type' => 'checkbox',
                        'desc' => __( 'When enabled, Route fee will be taxable', 'routeapp'),
                        'id'   => 'routeapp_route_fee_taxable',
                        'default' => 'no'
                    ),
                    array(
                        'name' => __( 'Tax Class', 'routeapp' ),
                        'type' => 'select',
                        'id'   => 'routeapp_taxable_class',
                        'class' => 'route-environment',
                        'options' => $tax_classes
                    ),
					array(
						'name' => __( 'Enable Route extra columns', 'routeapp' ),
						'type' => 'checkbox',
						'desc' => __( 'When enabled, add Route Charge and Route Protection columns to Orders page', 'routeapp'),
						'id'   => 'routeapp_route_enable_extra_columns',
						'default' => 'yes'
					),
                    array(
                        'name' => __( 'Please select all Shipping Methods that are similar to "In Store Pickup"', 'routeapp' ),
                        'type' => 'multiselect',
                        'id'   => 'routeapp_excluded_shipping_methods',
                        'class' => 'route-environment',
                        'options' => $shipping_methods_array
					),
                    array(
                        'name' => __( 'Please select all the order statuses that Route will use to sync orders', 'routeapp' ),
                        'type' => 'multiselect',
                        'id'   => 'routeapp_included_order_statuses',
                        'class' => 'route-environment',
                        'options' => wc_get_order_statuses()
                    ),
                    array(
                        'name' => __( 'Please select all the order statuses that Route will use to cancel orders', 'routeapp' ),
                        'type' => 'multiselect',
                        'id'   => 'routeapp_cancel_order_statuses',
                        'class' => 'route-environment',
                        'options' => wc_get_order_statuses()
                    ),
					array(
                        'name' => __( 'Include order updates widget', 'routeapp' ),
                        'type' => 'checkbox',
                        'desc' => __( 'When enabled, Route\'s default order updates widget will be included', 'routeapp'),
                        'id'   => 'routeapp_route_show_order_updates',
                        'default' => 'yes'
					),
                    array(
                        'name' => __( 'Choose the place where Route Widget will appear on checkout page', 'routeapp' ),
                        'type' => 'select',
                        'desc' => "<p class='description'>You can edit the place where the route widget will appear. You can check <a href='https://docs.woocommerce.com/wc-apidocs/hook-docs.html' target='_blank'>here</a> for further explanation about how hooks works.</p>",
                        'id'   => 'routeapp_checkout_hook',
                        'class' => 'route-environment',
                        'options' => $checkout_hooks,
                        'default' => 'woocommerce_checkout_before_order_review'
                    ),
					array(
						'type' => 'sectionend',
						'id'   => 'route_insurance'
					),
				) );


			/**
			 * Filter Routeapp Settings
			 *
			 * @since 1.0.0
			 * @param array $settings Array of the plugin settings
			 */
			return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );

		}

		/**
		 * Output the settings
		 *
		 * @since 1.0
		 */
		public function output() {

			$settings = $this->get_settings();
			WC_Admin_Settings::output_fields( $settings );
		}


		/**
	 	 * Save settings
	 	 *
	 	 * @since 1.0
		 */
		public function save() {

			$settings = $this->get_settings();
			WC_Admin_Settings::save_fields( $settings );
		}
	}
endif;
