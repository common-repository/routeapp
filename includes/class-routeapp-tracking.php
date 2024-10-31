<?php

class Routeapp_Shipment_Tracking {

    private $supported_plugins;
    
    public function __construct() {
        $this->supported_plugins = apply_filters('routeapp_supported_plugin_handlers', array(
            new Routeapp_WooCommerce_Shipment_Tracking(),
            new Routeapp_Blazing_Shipment_Tracking(),
            new Routeapp_Mimo_Shipment_Tracking(),
            new Routeapp_Aftership_Shipment_Tracking(),
            new Routeapp_YithWoocommerce_Tracking_Order(),
            new Routeapp_Astracker_Shipment_Tracking(),
            new Routeapp_WooCommerce_Shipment_Tracking_Pro(),
            new Routeapp_USPS_Shipping_Order(),
            new Routeapp_Wooshippinginfo_Tracking(),
            new Routeapp_Woo_Orders_Tracking(),
            new Routeapp_Jetpack(),
            new Routeapp_ShippingEasy(),
            new Routeapp_ShipStation(),
            new Routeapp_ShippingEasyCustom(),
            new Routeapp_Shipworks()
        ));
    }

    public function parse_order_notes($order_id, $route_app) {
        foreach ($this->supported_plugins as $supported_plugin) {
            if ($supported_plugin->is_active()) {
                $supported_plugin->parse_order_notes($order_id, $route_app);
            }
        }
    }

    public function update($order_id, $routeapp) {
        foreach ($this->supported_plugins as $supported_plugin) {
            if ($supported_plugin->is_active()) {
                $supported_plugin->update($order_id, $routeapp);
            }
        }
    }

    public function get_shipping_provider_name($order_id) {
        foreach ($this->supported_plugins as $supported_plugin) {
            if ($supported_plugin->is_active()) {
                $courier_data = $supported_plugin->get_shipping_provider_name($order_id);
                if ($courier_data) return $courier_data;
            }
        }
    }

    public function cancel($order_id, $tracking_number, $product_ids, $routeapp) {
        foreach ($this->supported_plugins as $supported_plugin) {
            if ($supported_plugin->is_active()) {
                return $supported_plugin->cancel($order_id, $tracking_number, $product_ids, $routeapp);
            }
        }
    }

    public function get_shipping_info($order_id) {
        foreach ($this->supported_plugins as $supported_plugin) {
            if ($supported_plugin->is_active()) {
                $trackings = $supported_plugin->get_shipping_info($order_id);
                if ($trackings) return $trackings;
            }
        }
        return false;
    }

}
