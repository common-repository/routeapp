<?php

class Routeapp_WooCommerce_Shipment_Tracking_Pro extends Routeapp_WooCommerce_Common_Tracking_Provider implements Routeapp_WooCommerce_Tracking_Provider {

    private $routeapp_api_client;

    public function __construct() {

    }

    public function is_active() {
        return in_array( 'ph-woocommerce-shipment-tracking/woocommerce-shipment-tracking.php', (array) get_option( 'active_plugins', array() ));
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    public function get_shipping_provider_name($order_id)
    {
        return $this->get_meta_data( $order_id, 'ph_shipment_tracking_shipping_service');
    }

    public function get_shipping_info($order_id)
    {
        $shipping_info = [];
        $raw_tracking_number = $this->get_meta_data( $order_id, 'ph_shipment_tracking_ids' );
        if (!$raw_tracking_number) return false;
        $tracking_numbers = explode(self::SEPARATOR_COMMA, $raw_tracking_number);
        $courier_id = $this->get_meta_data( $order_id, 'ph_shipment_tracking_shipping_service' );
        $product_ids = $this->get_order_products($order_id);
        foreach ($tracking_numbers as $tracking_number){
            $shipping_info[] = array(
                'source_order_id' => $order_id,
                'source_product_ids' => $product_ids,
                'courier_id' => $courier_id,
                'tracking_number' => $tracking_number
            );
        }
        return $shipping_info;
    }

    public function update($order_id, $routeapp) {
        $raw_tracking_number = $this->get_meta_data( $order_id, 'ph_shipment_tracking_ids' );
        if (!$raw_tracking_number) return;

        $product_ids = $this->get_order_products($order_id);

        $courier_id = isset($_GET['shipping_service']) ?
            $_GET['shipping_service'] :
            $this->get_meta_data( $order_id, 'ph_shipment_tracking_shipping_service' );
        $tracking_numbers = explode(self::SEPARATOR_COMMA, $raw_tracking_number);

        $params = array(
            'source_order_id' => $order_id,
            'source_product_ids' => $product_ids,
            'courier_id' => $courier_id
        );

        $existing_tracking_numbers = (array) explode(self::SEPARATOR_PIPE,
            $this->get_meta_data($order_id, 'routeapp_shipment_tracking_number'));

        if (count($tracking_numbers)>0) {
            $tracking_numbers_array = array();
            foreach ($tracking_numbers as $tracking_number){
                $tracking_numbers_array[] = $tracking_number;
                if (in_array($tracking_number, $existing_tracking_numbers)) {
                    continue;
                }
                $shipmentResponse = $routeapp->routeapp_api_client->get_shipment($tracking_number, $order_id);

                if (is_wp_error($shipmentResponse) || (isset($shipmentResponse['response']['code']) && $shipmentResponse['response']['code'] == 200)) {
                    continue;
                }

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
            }

            if ($existing_tracking_numbers[0]!='' && !empty($tracking_numbers_array)) {
                foreach ($existing_tracking_numbers as $existing_tracking_number) {
                    if (!in_array($existing_tracking_number, $tracking_numbers_array)) {
                        $routeapp->routeapp_cancel_tracking_order( $order_id, $existing_tracking_number, $product_ids );
                    }
                }
            }

            //save tracking values with pipe separator instead comma for compatibility with rest of plugins
            $raw_tracking_number = str_replace(self::SEPARATOR_COMMA, self::SEPARATOR_PIPE, $raw_tracking_number);
            if($raw_tracking_number!=''){
                $this->add_custom_post_meta($order_id, $raw_tracking_number, $courier_id);
            }
        }else {
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
