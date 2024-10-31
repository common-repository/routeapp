<?php

class Routeapp_USPS_Shipping_Order extends Routeapp_WooCommerce_Common_Tracking_Provider implements Routeapp_WooCommerce_Tracking_Provider {

    private $routeapp_api_client;

    public function __construct() {
    }

    public function is_active() {
        return in_array( 'usps-woocommerce-shipping/usps-woocommerce-shipping.php', (array) get_option( 'active_plugins', array() ));
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    public function get_shipping_provider_name($order_id)
    {
        return 'usps';
    }

    public function get_shipping_info($order_id)
    {
        $shipping_info = [];
        $tracking_data =  get_post_meta( $order_id, 'usps_evs_label_details_array', true );
        if (!$tracking_data) return false;
        $product_ids = $this->get_order_products($order_id);
        foreach ( $tracking_data as $track_item ) {
            $shipping_info[] = array(
                'source_order_id' => $order_id,
                'source_product_ids' => $product_ids,
                'courier_id' => $this->get_shipping_provider_name(),
                'tracking_number' => $track_item['ShipmentId']
            );
        }
        return $shipping_info;
    }

    public function update($order_id, $routeapp) {
        $tracking_data = $this->get_meta_data( $order_id, 'usps_evs_label_details_array');
        if (!$tracking_data) return;
        $product_ids = $this->get_order_products($order_id);

        $tracking_numbers = array();
        foreach ($tracking_data as $track_item) {
            $tracking_numbers[] = $track_item['ShipmentId'];
        }

        $existing_tracking_numbers = (array) explode( self::SEPARATOR_PIPE,
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

                $params = array(
                    'source_order_id' => $order_id,
                    'source_product_ids' => $product_ids,
                    'courier_id' => 'usps'
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
            }
            if ($existing_tracking_numbers[0]!='' && !empty($tracking_numbers_array)) {
                foreach ($existing_tracking_numbers as $existing_tracking_number) {
                    if (!in_array($existing_tracking_number, $tracking_numbers_array)) {
                        $routeapp->routeapp_cancel_tracking_order( $order_id, $existing_tracking_number, $product_ids );
                    }
                }
            }

            $tracking_numbers = implode(self::SEPARATOR_PIPE, $tracking_numbers_array);
            if ($tracking_numbers!='') {
                $this->add_custom_post_meta($order_id, $tracking_numbers);
            }

            foreach ($_POST['meta'] as $key => $value) {
                if ($value['key']=='routeapp_shipment_tracking_number') {
                    unset($_POST['meta'][$key]);
                }
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
