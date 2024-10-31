<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

class Routeapp_Admin_Route_Fee {

    const ROUTE_CHARGE_POST_META = '_routeapp_route_charge';
    const ROUTE_PROTECTION_POST_META = '_routeapp_route_protection';

    public function __construct()
    {
        if (!is_admin()) {
            return false;
        }
        $this->init();
    }

    public function init()
    {
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'routeapp_custom_admin_button'), 10, 1);
        add_action('woocommerce_new_order_item', array($this, 'recalculate_route_fee'), 10, 1);
        add_action('woocommerce_add_order_tax', array($this, 'recalculate_route_fee'), 10, 1);
        add_action('woocommerce_saved_order_items', array($this, 'recalculate_route_fee'), 10, 1);
        add_action('woocommerce_delete_order_item', array($this, 'recalculate_route_fee'), 10, 1);
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    public function routeapp_custom_admin_button($order)
    {
        if (!$this->can_add_fee($order)) {
            return false;
        }

        if ( is_admin() && in_array($order->get_status(), ['pending', 'on-hold', 'auto-draft']) ) {
            if ($this->has_route_fee_applied($order)) {
                echo '<button type="button" id="remove-route-fee" class="button generate-items">' . __( 'Remove Route Fee') . '</button>';
            } else {
                echo '<button type="button" id="add-route-fee" class="button generate-items">' . __( 'Add Route Fee') . '</button>';
            }
        }
    }

    private function routeapp_handle_admin_fee($order)
    {
        if (!$this->can_add_fee($order)) {
            return false;
        }
        $routeapp_public = self::get_route_public_instance();
        //order doesn't have a hash, we use time to auto-generate one
        $cartRef = time();
        $cartTotal = round($routeapp_public->get_cart_subtotal_with_only_shippable_items($order, 'order'), 2);
        $currency = get_woocommerce_currency();
        $cartItems = $routeapp_public->get_cart_shippable_items($order, 'order');
        $route_insurance_quote = $routeapp_public->routeapp_get_quote_from_api($cartRef, $cartTotal, $currency, $cartItems);

        $route_insurance_amount = false;
        //only add fee if the quote is covered by customer and has a valid amount
        if (isset($route_insurance_quote->premium->amount) &&
            (isset($route_insurance_quote->payment_responsible->type)) && $route_insurance_quote->payment_responsible->type==$routeapp_public->get_allowed_quote_type()) {
            $route_insurance_amount = $route_insurance_quote->premium->amount;
        }
        if ($route_insurance_amount > 0) {
            $this->routeapp_create_fee($order, $route_insurance_amount);
            $order->calculate_totals();
            if ($order->save()) {

                $acceptedOrderStatuses = get_option('routeapp_included_order_statuses') ? get_option('routeapp_included_order_statuses') : [];
                $acceptedCanceledStatuses = get_option('routeapp_cancel_order_statuses') ? get_option('routeapp_cancel_order_statuses') : [];

                if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
                    && OrderUtil::custom_orders_table_usage_is_enabled() ) {
                    // HPOS usage is enabled.
                    $order->update_meta_data(self::ROUTE_CHARGE_POST_META, $route_insurance_amount );
                    $order->update_meta_data(self::ROUTE_PROTECTION_POST_META, true );

                    $order->update_meta_data('_routeapp_included_order_statuses', json_encode($acceptedOrderStatuses));
                    $order->update_meta_data( '_routeapp_cancel_order_statuses', json_encode($acceptedCanceledStatuses));

                    $order->save();
                } else {
                    // Traditional CPT-based orders are in use.
                    update_post_meta( $order->get_id(), self::ROUTE_CHARGE_POST_META, $route_insurance_amount );
                    update_post_meta( $order->get_id(), self::ROUTE_PROTECTION_POST_META, true );

                    update_post_meta( $order->get_id(), '_routeapp_included_order_statuses', json_encode($acceptedOrderStatuses));
                    update_post_meta( $order->get_id(), '_routeapp_cancel_order_statuses', json_encode($acceptedCanceledStatuses));

                }
                return true;
            }
        }else {
            if ($this->has_route_fee_applied($order)) {
                $this->routeapp_remove_route_fee($order);
            }
        }

        return false;
    }

    private function routeapp_create_fee($order, $fee_amount) {
        $routeapp_public = self::get_route_public_instance();
        if (!$this->has_route_fee_applied($order)) {
            $item_fee = new WC_Order_Item_Fee();
            $item_fee->set_name( Routeapp_Public::ROUTE_LABEL );
            $item_fee->set_tax_class( $routeapp_public->routeapp_get_taxable_class() );
            $item_fee->set_tax_status( $routeapp_public->routeapp_get_fee_taxable() ? 'taxable' : 'none' );
            $item_fee->set_amount( $fee_amount );
            $item_fee->set_total( $fee_amount );
            $order->add_item( $item_fee );
        } else {
            $fees = $this->get_route_fees($order);
            foreach($fees as $fee_id => $fee) {
                if ($fee) {
                    $fee->set_amount($fee_amount);
                    $fee->set_total($fee_amount);
                    $fee->save();
                }
            }
        }
    }

    public function routeapp_add_admin_fee()
    {
        check_ajax_referer( 'order-item', 'security' );

        if (!isset($_POST) || !$_POST['order_id']) {
            wp_send_json_error('its missing order id on the request');
            return;
        }

        $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : 0;
        $order = wc_get_order( $order_id );

        $response = $this->routeapp_handle_admin_fee($order);

        if ($response) {
            wp_send_json_success( 'order updated!' );
            return;
        }

        wp_send_json_error('error while adding route fee to the order');
    }

    function routeapp_remove_admin_fee()
    {
        check_ajax_referer( 'order-item', 'security' );

        if (!isset($_POST) || !$_POST['order_id']) {
            wp_send_json_error('its missing order id on the request');
            return;
        }

        $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : 0;
        $order = wc_get_order( $order_id );

        if ($this->has_route_fee_applied($order)) {
            $this->routeapp_remove_route_fee($order);
        }

        wp_send_json_success('route fee removed from the order');
    }

    private function routeapp_remove_route_fee($order)
    {
        $fees = $this->get_route_fees($order);

        foreach($fees as $fee_id => $fee) {
            if ($fee) {
                wc_delete_order_item($fee_id);
            }
        }
        delete_post_meta( $order->get_id(), self::ROUTE_CHARGE_POST_META );
        delete_post_meta( $order->get_id(), self::ROUTE_PROTECTION_POST_META );
    }

    public function recalculate_route_fee($order_id)
    {
        $order = wc_get_order( $order_id );

        if ($this->has_route_fee_applied($order)) {
            $this->routeapp_handle_admin_fee($order);
        }
    }

    private function get_quote_from_api($order_total)
    {
        $response = Routeapp_API_Client::getInstance()->get_quote($order_total, get_woocommerce_currency());

        if (is_wp_error($response)) {
            return false;
        } else {
            if ($response['response']['code'] == 401) {
                return false;
            }
            $price_data = json_decode($response['body']);
            return $price_data;
        }
    }

    private function get_route_fees($order)
    {
        if (!$order) {
            return [];
        }

        $fees = $order->get_fees();
        $route_fees = [];
        foreach($fees as $fee_id => $fee) {
            if($fee['name'] == Routeapp_Public::ROUTE_LABEL) {
                $route_fees[$fee_id] = $fee;
            }
        }
        return $route_fees;
    }

    public function can_add_fee($order)
    {
        return $order && count($order->get_items()) > 0 && $order->get_status() == 'auto-draft';
    }

    private function has_route_fee_applied($order)
    {
        $routeFees = $this->get_route_fees($order);
        if (is_array($routeFees) || $routeFees instanceof Countable) {
            return count($routeFees) > 0;
        }
        return false;
    }
}
