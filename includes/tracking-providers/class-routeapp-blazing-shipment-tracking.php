<?php

class Routeapp_Blazing_Shipment_Tracking extends Routeapp_WooCommerce_Common_Tracking_Provider implements Routeapp_WooCommerce_Tracking_Provider {

    private $routeapp_api_client;

    public function __construct() {

    }

    public function is_active() {
        return in_array( 'blazing-woocommerce-shipment-tracking/bst-tracking.php', (array) get_option( 'active_plugins', array() ));
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    public function get_shipping_provider_name($order_id)
    {
        return get_post_meta( $order_id, 'routeapp_shipment_tracking_provider', true );
    }

    public function get_shipping_info($order_id)
    {
        $tracking_number = get_post_meta( $order_id, '_bst_tracking_number', true );
        if (!$tracking_number) return false;
        $courier_id = get_post_meta( $order_id, '_bst_tracking_provider', true );
        $product_ids = $this->get_order_products($order_id);
        return array(
            'source_order_id' => $order_id,
            'source_product_ids' => $product_ids,
            'courier_id' => $courier_id,
            'tracking_number' => $tracking_number
        );
    }

    public function update($order_id, $routeapp) {
        $tracking_number = $this->get_meta_data( $order_id, '_bst_tracking_number' );
        if (!$tracking_number) return;

        $tracking_provider = $this->get_meta_data( $order_id, '_bst_tracking_provider' );

        $product_ids = $this->get_order_products($order_id);

        $route_tracking_number = $this->get_meta_data( $order_id, 'routeapp_shipment_tracking_number' );

        if (!empty($route_tracking_number) && $tracking_number !== $route_tracking_number) {
            $routeapp->routeapp_cancel_tracking_order( $order_id, $route_tracking_number, $product_ids );
        }

        $shipmentResponse = $routeapp->routeapp_api_client->get_shipment($tracking_number, $order_id);

        if (is_wp_error($shipmentResponse) || (isset($shipmentResponse['response']['code']) && $shipmentResponse['response']['code'] == 200)) {
            return;
        }

        $courier_id = $tracking_provider;
        $params = array(
            'source_order_id' => $order_id,
            'source_product_ids' => $product_ids,
            'courier_id' => $courier_id
        );

        $extraData = array();
        $response = $routeapp->routeapp_api_client->create_shipment($tracking_number, $params);
        $extraData['endpoint'] = 'shipments';
        try{
            if ( is_wp_error( $response ) ) {
                throw new Exception($response->get_error_message());
            }
        } catch (Exception $exception) {
            $routeapp_public = self::get_route_public_instance();
            $params['tracking_id'] = $tracking_number;
            $extraData = array(
                'params' => $params,
                'method' => 'POST'
            );
            $routeapp_public->routeapp_log($exception, $extraData);
            return false;
        }

        $this->add_custom_post_meta($order_id, $tracking_number, $courier_id);

        return true;
    }

    public function parse_order_notes($order_id, $route_app)
    {
        return;
    }
}
