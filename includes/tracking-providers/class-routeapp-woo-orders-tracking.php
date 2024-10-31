<?php

class Routeapp_Woo_Orders_Tracking extends Routeapp_WooCommerce_Common_Tracking_Provider implements Routeapp_WooCommerce_Tracking_Provider {

    private $routeapp_api_client;

    public function __construct() {

    }

    public function is_active() {
        return in_array( 'woo-orders-tracking/woo-orders-tracking.php', (array) get_option( 'active_plugins', array() ));
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    public function get_shipping_provider_name($order_id)
    {
        $couriers_array = array();
        $order = wc_get_order( $order_id );
        //get all existing trackings for the items inside this order for this plugin
        foreach ( $order->get_items() as $item_id => $item ) {
            $existing_trackings = json_decode(wc_get_order_item_meta($item_id, '_vi_wot_order_item_tracking_data', true));
            if (is_array($existing_trackings)) {
                $existing_tracking = end($existing_trackings);
                if (isset( $existing_tracking->tracking_number)) {
                    $courier_id = strtolower(str_replace(' ', '-', $existing_tracking->carrier_name));
                    $couriers_array[$existing_tracking->tracking_number] = $courier_id;
                }
            }
        }
        return $couriers_array;
        return $this->get_meta_data( $order_id, 'routeapp_shipment_tracking_provider');
    }

    public function get_shipping_info($order_id)
    {
        $shipping_info = [];
        $order = wc_get_order( $order_id );
        //get all existing trackings for the items inside this order for this plugin
        foreach ( $order->get_items() as $item_id => $item ) {
            $existing_trackings = json_decode(wc_get_order_item_meta($item_id, '_vi_wot_order_item_tracking_data', true));
            if (is_array($existing_trackings)) {
                $existing_tracking = end($existing_trackings);
                if (isset( $existing_tracking->tracking_number)) {
                    $courier_id = strtolower(str_replace(' ', '-', $existing_tracking->carrier_name));
                    $shipping_info[] = array(
                        'source_order_id' => $order_id,
                        'source_product_ids' => array($item->get_product_id()),
                        'courier_id' => $courier_id,
                        'tracking_number' => $existing_tracking->tracking_number
                    );
                }
            }
        }
        return $shipping_info;
    }

    public function update($order_item_id, $routeapp) {
      if (!isset($_POST['tracking_code']) || !isset($_POST['carrier_name'])) return;

      if (wc_get_order( $order_item_id )) return;

      $tracking_numbers = array();
      $tracking_number = $_POST['tracking_code'];
      $tracking_numbers[]  = $tracking_number;
      $courier_id = strtolower(str_replace(' ', '-', $_POST['carrier_name']));
      $item = new WC_Order_Item_Product($order_item_id);
      $order = wc_get_order( $item->get_order_id() );

      $bulk_set_tracking_numbers = isset($_POST['action']) && $_POST['action'] == 'wotv_save_track_info_all_item';
      $parent_id = $bulk_set_tracking_numbers ? $order->get_id() : $order_item_id;

      $product_ids = array();
      if ($bulk_set_tracking_numbers) {
          $product_ids = $this->get_order_products($parent_id);
      } else {
        array_push($product_ids, $item->get_product()->get_id());
      }

      $params = array(
        'source_order_id' => $order->get_id(),
        'source_product_ids' => $product_ids,
        'courier_id' => $courier_id,
      );

      //get all existing trackings for the items inside this order for this plugin
      foreach ( $order->get_items() as $item_id => $item ) {
          $existing_trackings = json_decode(wc_get_order_item_meta($item_id, '_vi_wot_order_item_tracking_data', true));
          if (is_array($existing_trackings)) {
              $existing_tracking = end($existing_trackings);
              if (isset( $existing_tracking->tracking_number)) {
                  $tracking_numbers[] = $existing_tracking->tracking_number;
              }
          }
      }

      //get tracking numbers that we have on our plugin
      $existing_tracking_numbers = explode(self::SEPARATOR_PIPE,
            $this->get_meta_data( $order->get_id(), 'routeapp_shipment_tracking_number'));

      if (count($tracking_numbers)>0) {
          $tracking_numbers = array_unique($tracking_numbers);
          $tracking_numbers_array = array();
          foreach ($tracking_numbers as $tracking_number){
              $tracking_numbers_array[] = $tracking_number;
              if (in_array($tracking_number, $existing_tracking_numbers)) {
                  continue;
              }
              $shipmentResponse = $routeapp->routeapp_api_client->get_shipment($tracking_number, $order->get_id());

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
                      $routeapp->routeapp_cancel_tracking_order( $order->get_id(), $existing_tracking_number, $product_ids );
                  }
              }
          }
          $tracking_numbers = implode(self::SEPARATOR_PIPE, $tracking_numbers_array);

          if ($tracking_numbers!='') {
              $this->add_custom_post_meta($order->get_id(), $tracking_numbers);
          }
      }else {
          /* If user removed the Tracking Code and updated to a new one */
          if ($existing_tracking_numbers[0]!='') {
              foreach ($existing_tracking_numbers as $existing_tracking_number) {
                  $routeapp->routeapp_cancel_tracking_order( $order->get_id(), $existing_tracking_number, $product_ids );
              }
          }
      }
    }

    public function parse_order_notes($order_id, $route_app)
    {
        return;
    }
}
