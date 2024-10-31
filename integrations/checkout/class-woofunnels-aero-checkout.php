<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Routeapp_Integration_With_Aero_Checkout {
    public function __construct() {
        if ($this->is_active()) {
            add_action( 'woocommerce_init', [ $this, 'actions' ] );
        }
    }

    public function actions() {
        add_filter( 'wfacp_advanced_fields', function($field) {
            $field['route_widget_field'] = [
                'type'          => 'hidden',
                'default'       => true,
                'label'         => 'Route Widget',
                'validate'      => [],
                'id'            => 'route_widget_field',
                'required'      => false,
                'wrapper_class' => [],
                'class'         => [ 'route-widget-field' ],
            ];
            return $field;
        } );

        add_filter( 'woocommerce_form_field_args', function( $args, $key ) {
            if ( $key == 'route_widget_field' ) {
                echo do_shortcode('[route]');
            }
            return $args;
        }, 10, 2 );
    }

    public function is_active() {
        $active_plugins = (array) get_option( 'active_plugins', array() );

        if ( is_multisite() ) {
            $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
        }

        return in_array( 'woofunnels-aero-checkout/woofunnels-aero-checkout.php', $active_plugins ) || array_key_exists( 'woofunnels-aero-checkout/woofunnels-aero-checkout.php', $active_plugins );
    }
}

Routeapp_Plugin_Integrations::register( new Routeapp_Integration_With_Aero_Checkout(), 'woofunnels-aero-checkout' );
