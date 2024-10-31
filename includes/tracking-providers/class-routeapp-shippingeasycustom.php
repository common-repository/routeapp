<?php


class Routeapp_ShippingEasyCustom extends Routeapp_WooCommerce_Common_Tracking_Provider implements Routeapp_WooCommerce_Tracking_Provider {
    public function __construct() {
    }

    public function is_active() {
        return in_array( 'woocommerce-shippingeasy/woocommerce-shippingeasy.php', (array) get_option( 'active_plugins', array() ));
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    public function get_shipping_provider_name($order_id)
    {
        $couriers_array = [];
        $order_notes = wc_get_order_notes([
            'order_id' => $order_id,
            'type' => 'system',
        ]);
        if (count($order_notes)>0) {
            foreach ($order_notes as $order_note) {
                $track_data  = $this->parse_individual_order_note($order_note->content);
                if (isset($track_data['tracking_number']) && isset($track_data['courier_id'])) {
                    $couriers_array[$track_data['tracking_number']] = $track_data['courier_id'];
                }
            }
        }
        return $couriers_array;
    }

    public function get_shipping_info($order_id)
    {
        $shipping_info = [];
        $order_notes = wc_get_order_notes([
            'order_id' => $order_id,
            'type' => 'system',
        ]);
        if (count($order_notes)>0) {
            $product_ids = $this->get_order_products($order_id);
            foreach ($order_notes as $order_note) {
                $track_data  = $this->parse_individual_order_note($order_note->content);
                if (isset($track_data['tracking_number']) && isset($track_data['courier_id'])) {
                    $shipping_info[] = array(
                        'source_order_id' => $order_id,
                        'source_product_ids' => $product_ids,
                        'courier_id' => $track_data['courier_id'],
                        'tracking_number' => $track_data['tracking_number']
                    );
                }
            }
        }
        return $shipping_info;
    }

    public function update($order_id, $api_client)
    {
        return;
    }

    public function parse_order_notes($order_id, $route_app)
    {
        $order_notes = wc_get_order_notes([
            'order_id' => $order_id,
            'type' => 'system',
        ]);
        $existing_tracking_numbers = (array) explode( self::SEPARATOR_PIPE,
            $this->get_meta_data($order_id, 'routeapp_shipment_tracking_number'));
        if (!$order_notes && !$existing_tracking_numbers) return;

        $order = wc_get_order( $order_id );
        $product_ids = $this->get_order_products($order_id);

        if (count($order_notes)>0) {
            $tracking_numbers_array = array();
            foreach ($order_notes as $order_note) {
                $track_data  = $this->parse_individual_order_note($order_note->content);
                if (isset($track_data['tracking_number']) && isset($track_data['courier_id'])) {
                    $tracking_number = $track_data['tracking_number'];
                    $tracking_numbers_array[] = $tracking_number;
                    if (in_array($tracking_number, $existing_tracking_numbers)) {
                        continue;
                    }

                    $shipmentResponse = $route_app->routeapp_api_client->get_shipment($tracking_number, $order_id);
                    if (is_wp_error($shipmentResponse) || (isset($shipmentResponse['response']['code']) && $shipmentResponse['response']['code'] == 200)) {
                        continue;
                    }
                    $params = array(
                        'source_order_id' => $order_id,
                        'source_product_ids' => $product_ids,
                        'courier_id' => $track_data['courier_id']
                    );
                    $extraData = array();
                    $response = $route_app->routeapp_api_client->create_shipment($tracking_number, $params);
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
            }
            if ($existing_tracking_numbers[0]!='' && !empty($tracking_numbers_array)) {
                foreach ($existing_tracking_numbers as $existing_tracking_number) {
                    if (!in_array($existing_tracking_number, $tracking_numbers_array)) {
                        $route_app->routeapp_cancel_tracking_order( $order_id, $existing_tracking_number, $product_ids );
                    }
                }
            }
            $tracking_numbers = implode(self::SEPARATOR_PIPE, $tracking_numbers_array);
            if ($tracking_numbers!='') {
                $this->add_custom_post_meta($order_id, $tracking_numbers);
            }
        }else {
            /* If user removed the order note from Woo */
            if ($existing_tracking_numbers[0]!='') {
                foreach ($existing_tracking_numbers as $existing_tracking_number) {
                    $route_app->routeapp_cancel_tracking_order( $order_id, $existing_tracking_number, $product_ids );
                }
            }
        }
    }

    private function parse_individual_order_note($note) {
        $tracking_data = [];
        if (strpos($note, 'Tracking Number')) {
            $lines = explode('<br/>', $note);
            foreach ($lines as $line) {
                $line = explode(':', $line);
                switch (trim($line[0])) {
                    case 'Shipping Tracking Number':
                        $tracking_data['tracking_number']= trim($line[1]);
                        break;
                    case 'Carrier Key':
                        $tracking_data['courier_id']= trim($line[1]);
                        break;
                }
            }
            if (isset($tracking_data['tracking_number']) && isset($tracking_data['courier_id'])) {
                return $tracking_data;
            }
        }
        return false;
    }
}

