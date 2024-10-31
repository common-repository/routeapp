<?php
/**
 * Class Routeapp_Order_Recover file.
 *
 * @package Routeapp\Admin
 */

class Routeapp_Order_Recover {

    public function __construct() {
        // Ensure the AJAX actions are registered
        add_action('wp_ajax_routeapp_save_orders', array($this, 'save'));
        add_action('wp_ajax_routeapp_process_orders_batch', array($this, 'process_orders_batch'));

    }

    /**
     * Save function to handle the processing of orders based on specified date range and batch size.
     * This function processes orders in batches to avoid timeouts and server overload.
     * It checks if the necessary POST parameters are set, fetches the orders in batches,
     * processes each batch, and updates the order count accordingly.
     */
    public function save() {
        if ($this->isPostRequestValid()) {
            $recoverFrom = $_POST['routeapp_order_recover_from'];
            $recoverTo = $_POST['routeapp_order_recover_to'];

            // Get the total number of orders in the specified date range
            $orderCount = $this->getOrderCount($recoverFrom, $recoverTo);

            // Determine batch size based on the number of orders
            $batchSize = $this->determineBatchSize($orderCount);

            // Determine wait time based on batch size
            $waitTime = $this->determineWaitTimeParameter($batchSize);

            // Send back the initial response with the calculated values
            wp_send_json_success(array(
              'orderCount' => $orderCount,
              'batchSize' => $batchSize,
              'recoverFrom' => $recoverFrom,
              'recoverTo' => $recoverTo,
              'waitTime' => $waitTime,
            ));
        }
        else {
            wp_send_json_error(array(
              'message' => __('Error', 'routeapp'),
            ));
        }
        wp_die();
    }

    // New function to handle batch processing via AJAX
    public function process_orders_batch() {

        if ($this->isPostRequestValid()) {
            $recoverFrom = $_POST['routeapp_order_recover_from'];
            $recoverTo = $_POST['routeapp_order_recover_to'];
            $batchSize = $_POST['batchSize'];
            $offset = $_POST['offset'];

            $orders = $this->getOrdersBatch($batchSize, $offset, $recoverTo, $recoverFrom);

            if (!empty($orders)) {
                $this->processOrders($orders);
                wp_send_json_success(array(
                  'processed' => count($orders),
                ));
            } else {
                wp_send_json_error(array(
                  'message' => __('No more orders to process.', 'routeapp'),
                ));
            }
        }
        else {
            wp_send_json_error(array(
              'message' => __('Error', 'routeapp'),
            ));
        }
        wp_die();
    }

    /**
     * Validates the POST request to ensure required parameters are set.
     *
     * @return bool Returns true if the POST request contains 'routeapp_order_recover_from' and 'routeapp_order_recover_to'.
     */
    private function isPostRequestValid() {
        return isset($_POST) && isset($_POST['routeapp_order_recover_from']) && isset($_POST['routeapp_order_recover_to']);
    }


    /**
     * Fetches a batch of orders based on the specified batch size and offset.
     *
     * @param int $batchSize The number of orders to fetch in one batch.
     * @param int $offset The starting point for fetching orders.
     * @return array The fetched orders based on the specified arguments.
     */
    private function getOrdersBatch($batchSize, $offset, $recoverTo, $recoverFrom) {
        $args = [
          'limit' => $batchSize,
          'offset' => $offset,
          'date_created' => '>=' . $recoverFrom,
          'date_created' => '<=' . $recoverTo,
        ];

        return wc_get_orders($args);
    }

    /**
     * Processes the fetched orders by either updating order post meta or performing a massive order save operation.
     *
     * @param array $orders The fetched orders to be processed.
     */
    private function processOrders($orders) {
        if (isset($_POST['routeapp_order_recover_reconcile_backend'])) {
            $this->updateOrderPostMeta($orders);
        } else {
            $this->massiveOrderSave($orders);
        }
    }
    
    /**
     * Trigger save for all the selected orders, which will trigger the webhook for order.update
     *
     * @param $orders
     * @return void
     */
    public function massiveOrderSave($orders) {
        foreach ($orders as $order) {
            $order->save();
        }
    }

    /**
     * Update order post_meta with route order data
     *
     * @param $orders
     * @return void
     */
    public function updateOrderPostMeta($orders) {
        foreach ($orders as $order) {
            if (!get_post_meta( $order->get_id(), '_routeapp_order_id')) {
                //check if order exists on Route side
                $getOrderResponse = Routeapp_API_Client::getInstance()->get_order($order->get_id());
                if (is_wp_error($getOrderResponse) || $getOrderResponse['response']['code'] != 200) {
                    continue;
                }
                Routeapp_Cron_Schedules::updateOrderPostMeta($order, $getOrderResponse);
            }
        }
    }

    /**
     * Get the count of orders based on the specified date range.
     *
     * @param string $from Starting date for the orders.
     * @param string $to Ending date for the orders.
     * @return int The count of orders.
     */
    public function getOrderCount($from, $to) {
        global $wpdb;

        $query = "
                SELECT COUNT(1)
                FROM {$wpdb->posts} AS posts
                WHERE posts.post_type = 'shop_order_placehold'
                AND posts.post_date >= %s
                AND posts.post_date <= %s
            ";

        $prepared_query = $wpdb->prepare($query, $from, $to);
        $order_count = $wpdb->get_var($prepared_query);

        return $order_count;
    }

    /**
     * Determine batch size based on the number of orders.
     *
     * @param int $orderCount The number of orders.
     * @return int The batch size.
     */
    private function determineBatchSize($orderCount) {
        switch (true) {
            case ($orderCount <= 1000):
                return 100;
            case ($orderCount <= 5000):
                return 50;
            case ($orderCount <= 10000):
                return 25;
            default:
                return 10; // Fallback batch size for more than 100,000 orders
        }
    }

    /**
     * Determine wait time based on the batch size.
     *
     * @param int $batchSize The batch size.
     * @return int The wait time.
     */
    private function determineWaitTimeParameter($batchSize) {
        switch ($batchSize) {
            case 100:
                return 10;
            case 50:
                return 5;
            default:
                return 2; // Fallback wait time
        }
    }
}
