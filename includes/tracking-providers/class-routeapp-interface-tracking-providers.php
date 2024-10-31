<?php

interface Routeapp_WooCommerce_Tracking_Provider {
    const SEPARATOR_PIPE = "|||";
    const SEPARATOR_COMMA = ",";

    public function is_active();
    public function update($order_id, $api_client);
    public function cancel($order_id, $tracking_number, $product_ids, $routeapp);
    public function get_shipping_provider_name($order_id);
    public function get_shipping_info($order_id);
    public function parse_order_notes($order_id, $route_app);
}
