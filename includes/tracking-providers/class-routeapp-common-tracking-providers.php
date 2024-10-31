<?php

use Automattic\WooCommerce\Utilities\OrderUtil;
class Routeapp_WooCommerce_Common_Tracking_Provider {

    public function get_order_products($order_id)
    {
        $order = wc_get_order( $order_id );
        $product_ids = array();

        foreach ( $order->get_items() as $order_item ) {
            ## Using WC_Order_Item_Product methods ##
            $product = $order_item->get_product(); // Get the WC_Product object
            
            if ($product instanceof WC_Product) {
                for ($iterator = 1; $iterator <= $order_item->get_quantity(); $iterator++) {
                    array_push($product_ids, $order_item->get_product()->get_id());
                }
            }
            
        }
        return $product_ids;
    }

    public function remove_custom_post_meta($order_id)
    {
        delete_post_meta( $order_id, 'routeapp_shipment_api_called' );
        delete_post_meta( $order_id, 'routeapp_shipment_tracking_number' );
        delete_post_meta( $order_id, 'routeapp_shipment_tracking_provider' );
        return true;
    }

    public function add_custom_post_meta($order_id, $tracking_number, $courier_id = false)
    {
        self::update_post_meta_value($order_id, 'routeapp_shipment_api_called', 'success');
        self::update_post_meta_value( $order_id, 'routeapp_shipment_tracking_number', $tracking_number );
        if ($courier_id) {
            self::update_post_meta_value($order_id, 'routeapp_shipment_tracking_provider', $courier_id);
        }
        return true;
    }

    /**
     * Update post meta value
     *
     * @param $order_id
     * @param $post_meta
     * @param $value
     */
    public static function update_post_meta_value( $order_id, $post_meta, $value) {

        if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
            && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $order = wc_get_order($order_id);
            $order->update_meta_data($post_meta,  $value );
            $order->save();
        } else {
            // Traditional CPT-based orders are in use.
            update_post_meta( $order_id, $post_meta, $value );
        }
    }

    /**
     * get meta data if custom order table is enabled
     * @param $order_id
     * @param $post_meta
     * @return array|mixed|string
     */
    public static function get_meta_data( $order_id, $post_meta ) {
        if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
            && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $order = wc_get_order($order_id);
            return $order->get_meta($post_meta);
        } else {
            // Traditional CPT-based orders are in use.
            return get_post_meta($order_id, $post_meta, true);
        }
    }

    public function cancel($order_id, $tracking_number, $product_ids, $routeapp) {
        if ( is_null($order_id) || is_null($tracking_number) ) {
            return false;
        }
        if (!empty($tracking_number)) {
            $shipmentResponse = $routeapp->routeapp_api_client->get_shipment($tracking_number, $order_id);
            if (!is_wp_error($shipmentResponse) && isset($shipmentResponse['response']['code']) && $shipmentResponse['response']['code'] == 200) {
                $response = $routeapp->routeapp_api_client->cancel_shipment($tracking_number, array(
                    'source_order_id' => $order_id,
                    'source_product_ids' => $product_ids
                ));
                if ( is_wp_error( $response ) ) {
                    return false;
                } else {
                    //$this->remove_custom_post_meta($order_id);
                    if($response['response']['code'] === 400) {
                        return false;
                    }
                    return true;
                }
            }
        }
        return false;
    }
}
