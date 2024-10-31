<?php

class Routeapp_Astracker_Shipment_Tracking extends Routeapp_WooCommerce_Common_Tracking_Provider implements Routeapp_WooCommerce_Tracking_Provider {

    private $routeapp_api_client;

    public function __construct() {

    }

    public function is_active() {
        return in_array( 'woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php', (array) get_option( 'active_plugins', array() ));
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    public function get_shipping_provider_name($order_id)
    {
        $couriers_array = array();
        $tracking_items =  $this->get_meta_data( $order_id, '_wc_shipment_tracking_items' );
        if (!$tracking_items) return false;
        foreach ( $tracking_items as $tracking_item ) {
            $couriers_array[$tracking_item['tracking_number']] = strtolower($tracking_item['tracking_provider']);
        }
        return $couriers_array;
    }

    public function get_shipping_info($order_id)
    {
        $shipping_info = [];
        $tracking_items =  $this->get_meta_data( $order_id, '_wc_shipment_tracking_items' );
        if (!$tracking_items) return false;
        $product_ids = $this->get_order_products($order_id);
        foreach ( $tracking_items as $tracking_item ) {
            $courier_id = !empty( $tracking_item['tracking_provider'] ) ? $tracking_item['tracking_provider'] : $tracking_item['custom_tracking_provider'];
            $courier_id = strtolower( $courier_id );
            $shipping_info[] = array(
                'source_order_id' => $order_id,
                'source_product_ids' => $product_ids,
                'courier_id' => $courier_id,
                'tracking_number' => $tracking_item['tracking_number']
            );
        }
        return $shipping_info;
    }

    public function update($order_id, $routeapp) {

        $tracking_items =  $this->get_meta_data( $order_id, '_wc_shipment_tracking_items' );
        if (!$tracking_items) return;
        $product_ids = $this->get_order_products($order_id);

        $existing_tracking_numbers = explode(self::SEPARATOR_PIPE,
            $this->get_meta_data( $order_id, 'routeapp_shipment_tracking_number' ));

        if ( count($tracking_items) > 0 ) {
            $tracking_numbers_array = array();
            foreach ( $tracking_items as $tracking_item ) {
                $tracking_numbers_array[] = $tracking_item['tracking_number'];
                if (in_array($tracking_item['tracking_number'], $existing_tracking_numbers)) {
                    continue;
                }

                $shipmentResponse = $routeapp->routeapp_api_client->get_shipment($tracking_item['tracking_number'], $order_id);

                if (is_wp_error($shipmentResponse) || (isset($shipmentResponse['response']['code']) && $shipmentResponse['response']['code'] == 200)) {
                    continue;
                }

                $courier_id = !empty( $tracking_item['tracking_provider'] ) ? $tracking_item['tracking_provider'] : $tracking_item['custom_tracking_provider'];
                $courier_id = strtolower( $courier_id );
                $params = array(
                    'source_order_id' => $order_id,
                    'source_product_ids' => $product_ids,
                    'courier_id' => $courier_id
                );

                $extraData = array();
                $response = $routeapp->routeapp_api_client->create_shipment($tracking_item['tracking_number'], $params);
                $extraData['endpoint'] = 'shipments';

                try{
                    if ( is_wp_error( $response ) ) {
                        throw new Exception($response->get_error_message());
                    }
                } catch (Exception $exception) {
                    $routeapp_public = self::get_route_public_instance();
                    $params['tracking_id'] = $tracking_item['tracking_number'];
                    $extraData = array(
                        'params' => $params,
                        'method' => 'POST'
                    );
                    $routeapp_public->routeapp_log($exception, $extraData);
                    return false;
                }
            }
            if ($existing_tracking_numbers[0]!='' && !empty($tracking_numbers_array)) {
                foreach ($existing_tracking_numbers as $existing_tracking_number) {
                    if (!in_array($existing_tracking_number, $tracking_numbers_array)) {
                        $routeapp->routeapp_cancel_tracking_order( $order_id, $existing_tracking_number, $product_ids );
                    }
                }
            }
            $tracking_numbers = implode(self::SEPARATOR_PIPE, $tracking_numbers_array);
            if (!empty($tracking_number)) {
                $this->add_custom_post_meta($order_id, $tracking_numbers);
            }
        } else {
            /* If user removed the Tracking Code and updated to a new one */
            if ($existing_tracking_numbers[0]!='') {
                foreach ($existing_tracking_numbers as $existing_tracking_number) {
                    $routeapp->routeapp_cancel_tracking_order( $order_id, $existing_tracking_number, $product_ids );
                }
            }
        }
    }

    public function parse_order_notes($order_id, $route_app)
    {
        return;
    }
}

