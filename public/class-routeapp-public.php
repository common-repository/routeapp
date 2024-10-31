<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://route.com/
 * @since      1.0.0
 *
 * @package    Routeapp
 * @subpackage Routeapp/public
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * The public-facing functionality of routeapp.
 *
 *
 * @package    Routeapp
 * @subpackage Routeapp/public
 * @author     Route App <support@route.com>
 */

class Routeapp_Public {

    const HTTP_SUCCESS_CODE = 200;
    const HTTP_BAD_REQUEST_CODE = 400;
    const HTTP_CREATED_CODE = 201;
    const HTTP_CONFLICT_CODE = 409;
    const HTTP_NOT_FOUND = 404;
    const HTTP_INTERNAL_SERVER_ERROR_CODE = 500;
    const DEFAULT_MAX_USD_SUBTOTAL_ALLOWED = 5000;
    const DEFAULT_MIN_USD_SUBTOTAL_ALLOWED = 0;
    const ROUTE_LABEL = 'Route Shipping Protection';
    const DEFAULT_CHECKOUT_WEBHOOK =  'woocommerce_checkout_before_order_review';
    const ROUTE_PROTECTION_SKU = 'ROUTEINS';
    const ALLOWED_QUOTE_TYPE = 'paid_by_customer';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Routeapp API instance
     * @since    1.0.0
     * @access   private
     * @var 	string 		$routeapp_api 	Routeapp API Client
     */
    public $routeapp_api_client;


    /**
     * Route Shipment Tracking Module
     */
    private $routeapp_shipment_tracking;

    /**
     * Last Request
     */
    private $routeapp_last_request;

    /**
     * Route Logger
     */
    private $routeapp_logger;

    /**
     * Route Admin Fee
     */
    private $routeapp_admin_fee;

    /**
     * Route Setup
     */
    private $route_app_setup_helper;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->routeapp_api_client = new Routeapp_API_Client($this->routeapp_get_public_token(), $this->routeapp_get_secret_token());
        $this->routeapp_shipment_tracking = new Routeapp_Shipment_Tracking();
        $this->routeapp_logger = new Routeapp_Logger_Sentry();
        $this->routeapp_admin_fee = new Routeapp_Admin_Route_Fee();
        $this->route_app_setup_helper = new Route_Setup();
        $this->add_actions_and_filters();
    }

    public function add_actions_and_filters() {
        //CHECKOUT HOOK
        $checkout_hook = $this->routeapp_get_checkout_hook();
        add_action($checkout_hook, array($this, 'checkout_route_insurance'), 1, 0);


        add_action('woocommerce_checkout_before_order_review', array($this, 'checkout_route_insurance_fee'), 20, 1);

        add_action('woocommerce_cart_calculate_fees', array($this, 'checkout_route_insurance_fee'), 20, 1);
        add_action('wp_ajax_woo_get_ajax_data', array($this, 'checkout_route_insurance_set_session'));
        add_action('wp_ajax_nopriv_woo_get_ajax_data', array($this, 'checkout_route_insurance_set_session'));

        add_action('wp_ajax_woo_check_widget', array($this, 'check_route_widget'));
        add_action('wp_ajax_nopriv_woo_check_widget', array($this, 'check_route_widget'));

        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'routeapp_checkout_create_order_line_item' ], 10, 4 );
        add_action( 'woocommerce_ajax_add_order_item_meta', [$this, 'routeapp_ajax_add_order_item_meta'], 10, 2 );
        add_filter( 'woocommerce_hidden_order_itemmeta', [$this,'routeapp_woocommerce_hidden_order_itemmeta'], 10, 1);

        add_action( 'added_post_meta', array( $this, 'routeapp_update_tracking_order_api' ), 20, 3 );
        add_action( 'updated_post_meta', array( $this, 'routeapp_update_tracking_order_api' ), 20, 3 );
        add_action( 'added_order_item_meta', array( $this, 'routeapp_update_tracking_order_item_api' ), 20, 3 );
        add_action( 'updated_order_item_meta', array( $this, 'routeapp_update_tracking_order_item_api' ), 20, 3 );
        add_action('woocommerce_order_status_processing', array($this, 'routeapp_mark_order'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'routeapp_mark_order'), 8, 2);
        add_action('woocommerce_new_order', array($this, 'routeapp_save_quote_to_order'), 10, 1);
        add_action('rest_api_init', array($this, 'routeapp_register_status_route'));
        add_action('rest_api_init', array($this, 'routeapp_register_setup_route'));
        add_action('wp_ajax_get_route_checkout', array($this, 'routeapp_register_checkout_route'));
        add_action('wp_ajax_nopriv_get_route_checkout', array($this, 'routeapp_register_checkout_route'));
        add_action('wp_head', array($this, 'wc_routeapp_ajaxurl'));
        add_action('woocommerce_init', array($this, 'register_shortcode'));
        add_action('woocommerce_rest_prepare_order_note', array($this, 'check_order_note'));
        add_action('wp_insert_comment', array($this, 'check_order_note_by_note_id'));

        add_action('wp_ajax_routeapp_add_admin_fee', array($this, 'routeapp_add_admin_fee'));
        add_action('wp_ajax_nopriv_routeapp_add_admin_fee', array($this, 'routeapp_add_admin_fee'));
        add_action('wp_ajax_routeapp_remove_admin_fee', array($this, 'routeapp_remove_admin_fee'));
        add_action('wp_ajax_nopriv_routeapp_remove_admin_fee', array($this, 'routeapp_remove_admin_fee'));
        add_action('wc_avatax_api_fee_line_data', array($this, 'routeapp_edit_avatax_fee'));
        add_action('woocommerce_shipstation_export_order_xml', array($this, 'routeapp_add_sku_to_route_node') );

        add_action('woocommerce_order_details_after_order_table', array($this, 'order_updates_route_tracking'), 1, 1);
    }

    /**
     * Hide order line_item meta_data from Route App
     *
     * @param $arr
     * @return mixed
     */
    function routeapp_woocommerce_hidden_order_itemmeta($arr) {
        $arr[] = '_routeapp_source_product_id';
        $arr[] = '_routeapp_image_url';
        $arr[] = '_routeapp_virtual';
        $arr[] = '_routeapp_weight_value';
        $arr[] = '_routeapp_weight_unit';
        $arr[] = '_routeapp_origin_location';
        return $arr;
    }

    /**
     * Fill metadata
     *
     * @param $item WC_Order_Item
     * @param $product WC_Product
     * @param $entity_type enum('order_item','item')
     * @return void
     */
    public static function fillMetaData($item, $product, $entity_type='order_item') {

        $sourceProductId = $product->get_id();
        $productImageUrl = (wp_get_attachment_url($product->get_image_id())) ? wp_get_attachment_url($product->get_image_id()) : null;
        $weightValue = $product->get_weight();

        $originLocation = json_encode([
            'address' => get_option('woocommerce_store_address'),
            'address2' => get_option('woocommerce_store_address_2'),
            'store_city' => get_option('woocommerce_store_city'),
            'store_postcode' => get_option('woocommerce_store_postcode'),
            'default_country' => get_option('woocommerce_default_country'),
        ]);


        switch($entity_type) {
            case 'order_item':
                //add source_product_id as order line_item meta_data
                self::addItemMeta($item, '_routeapp_source_product_id', $sourceProductId);

                //add image_url as order line_item meta_data
                self::addItemMeta($item, '_routeapp_image_url', $productImageUrl);

                //add weight_value as order line_item meta_data
                self::addItemMeta($item, '_routeapp_weight_value', $weightValue);

                //add weight_unit as order line_item meta_data
                self::addItemMeta($item, '_routeapp_weight_unit', get_option('woocommerce_weight_unit'));

                //check if product is virtual
                if ($product->is_virtual()) {
                    //add virtual as order line_item meta_data
                    self::addItemMeta($item, '_routeapp_virtual', 1);
                }
                //add origin location as order line_item meta_data
                self::addItemMeta($item, '_routeapp_origin_location', $originLocation);
                break;
            case 'item':
                //add source_product_id as order line_item meta_data
                self::addOrderItemMeta($item->get_id(), '_routeapp_source_product_id', $sourceProductId);

                //add image_url as order line_item meta_data
                self::addOrderItemMeta($item->get_id(), '_routeapp_image_url', $productImageUrl);

                //add weight_value as order line_item meta_data
                self::addOrderItemMeta($item->get_id(), '_routeapp_weight_value', $weightValue);

                //add weight_unit as order line_item meta_data
                self::addOrderItemMeta($item->get_id(), '_routeapp_weight_unit', get_option('woocommerce_weight_unit'));

                //check if product is virtual
                if ($product->is_virtual()) {
                    //add virtual as order line_item meta_data
                    self::addOrderItemMeta($item->get_id(), '_routeapp_virtual', 1);
                }
                //add origin location as order line_item meta_data
                self::addOrderItemMeta($item->get_id(), '_routeapp_origin_location', $originLocation);
                break;
        }
    }

    /**
     * Add order line_item meta_data on ajax add to cart
     *
     * @param $item_id String
     * @param $item WC_Order_Item
     * @return false|void
     */
    public static function routeapp_ajax_add_order_item_meta( $item_id, $item) {
        $product = $item->get_product(); // Get the WC_Product object
        if ($product instanceof WC_Product) {
            try {
                self::fillMetaData($item, $product, 'item');
            }catch (Exception $exception) {
                return false;
            }
        }
    }

    /**
     * @param $itemId WC_Order_Item ID
     * @param $key String
     * @param $value String
     * @return bool
     */
    private static function addOrderItemMeta($itemId, $key, $value) {
        try {
            $fieldExists = wc_get_order_item_meta( $itemId, $key, true);
            if (!$fieldExists) {
                wc_add_order_item_meta($itemId, $key, $value);
            }
        }catch (Exception $exception) {
            return false;
        }
        return true;
    }

    /**
     * @param $item WC_Order_Item
     * @param $key String
     * @param $value String
     * @return bool
     */
    private static function addItemMeta($item, $key, $value) {
        try {
            $fieldExists =  $item->get_meta($key, true);
            if (!$fieldExists) {
                $item->add_meta_data( $key, $value );
            }
        }catch (Exception $exception) {
            return false;
        }
        return true;
    }

    /**
     * Add order line_item meta_data on checkout add to cart
     *
     * @param $item WC_Order_Item
     * @param $cart_item_key String
     * @param $values Object
     * @param $order WC_Order
     * @return false|void
     */
    public function routeapp_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
        if ( $order instanceof WC_Order ) {
            $product = $item->get_product(); // Get the WC_Product object
            if ($product instanceof WC_Product) {
                try {
                    self::fillMetaData($item, $product, 'order_item');
                }catch (Exception $exception) {
                    return false;
                }
            }
        }
    }

    public function routeapp_add_admin_fee() {
        $this->routeapp_admin_fee->routeapp_add_admin_fee();
    }

    public function routeapp_remove_admin_fee() {
        $this->routeapp_admin_fee->routeapp_remove_admin_fee();
    }

    public function register_shortcode() {
        add_shortcode( 'route', array( $this, 'route_widget_shortcode' ) );
    }

    public function get_allowed_quote_type() {
        return self::ALLOWED_QUOTE_TYPE;
    }

    public function check_route_widget() {
        if (!isset($_POST) || !$_POST['shipping_method']) {
            wp_send_json_error('its missing shipping method on the request');
            return;
        }
        $shipping_method = isset($_POST['shipping_method']) ? $_POST['shipping_method'] : '';
        $response = [];
        $response['show_widget'] =  $this->routeapp_is_shipping_method_allowed($shipping_method);
        wp_send_json_success( $response );
        return;
    }

    /**
     * set routeapp session
     *
     * @since    1.0.0
     */
    public function checkout_route_insurance_set_session() {
        if ( isset($_POST['checkbox']) && $_POST['checkbox'] === 'true' ){
            WC()->session->set('checkbox_checked', true );
        } else {
            WC()->session->set( 'checkbox_checked', false );
        }
    }

    /**
     * Add route insurance fee in checkout and cart
     * @since    1.0.0
     * @param  Object $cart
     *
     */
    public function checkout_route_insurance_fee( $cart ) {
        if ( (is_admin() && ! defined( 'DOING_AJAX' )) ) return;

        $cart = empty($cart) ? WC()->cart : $cart;
        $checkbox = WC()->session->get( 'checkbox_checked' );

        $cartRef = $cart->get_cart_hash();
        $cartTotal = round($this->get_cart_subtotal_with_only_shippable_items($cart), 2);
        $currency = get_woocommerce_currency();
        $cartItems = $this->get_cart_shippable_items($cart);
        $route_insurance_quote = $this->routeapp_get_quote_from_api($cartRef, $cartTotal, $currency, $cartItems);

        $route_insurance_amount = false;
        //only add fee if the quote is covered by customer and has a valid amount
        if (isset($route_insurance_quote->premium->amount) &&
            (isset($route_insurance_quote->payment_responsible->type)) && $route_insurance_quote->payment_responsible->type==self::ALLOWED_QUOTE_TYPE) {
            $route_insurance_amount = $route_insurance_quote->premium->amount;
        }
        if (is_null($checkbox) && isset($route_insurance_quote->payment_responsible->ToggleState)) {
            $checkbox = $route_insurance_quote->payment_responsible->ToggleState;
            WC()->session->set('checkbox_checked', $checkbox);
        }

        Route_Setup::set_setup_check_widget(Route_Setup::SETUP_CHECK_WIDGET_PHP);
        if ( (is_null($checkbox) && is_checkout() || ($checkbox === TRUE || $checkbox === 1)) && $route_insurance_amount > 0 && $this->routeapp_is_shipping_method_allowed()) {
            $cart->add_fee( $this->routeapp_get_insurance_label(),
                $route_insurance_amount,
                $this->routeapp_get_fee_taxable(),
                $this->routeapp_get_taxable_class());
        }
    }

    /**
     * Create checkout
     * @since    1.0.0
     *
     */
    public function checkout_route_insurance() {
        echo do_shortcode('[route]');
    }

    public function order_updates_route_tracking($order) {
        if ($this->routeapp_route_show_order_updates()) {
            echo $this->route_show_thankyoupage_asset($order);
        }
    }

    public function route_show_thankyoupage_asset($order) {
        $output = '';

        $site = parse_url(get_site_url(get_current_blog_id()), PHP_URL_HOST);
        $response = Routeapp_API_Client::getInstance()->asset_settings($site);

        if (is_wp_error($response) ||
            (isset($response['response']['code']) && $response['response']['code'] == 404) ||
            is_null($response['body'])) {
            return $output;
        } else {

            $body = json_decode($response['body']);

            if (!is_null($body) &&
                !is_null($body->asset_settings) &&
                !is_null($body->asset_settings->asset_live) &&
                $body->asset_settings->asset_live) {
                $raw_html = $body->asset_settings->asset_content->raw_html;
                $css = $body->asset_settings->asset_content->css_url;
                $js = isset($body->asset_settings->asset_content->js_url) ? $body->asset_settings->asset_content->js_url : '';
                $jsPath = '';
                if (!empty($js)) {
                    $jsPath = '<script type="text/javascript" src="' . $js . '"></script>';
                }
                $output = '<link rel="stylesheet" href="' . $css . '" type="text/css" />' .
                    $jsPath .
                    '<section class=woocommerce-order-updates>' .
                    '<h2 class="woocommerce-order-updates__title">Order Updates</h2>' .
                    '</section>' .
                    '<div><span class="os-order-number" style="display:none;">'.$order->get_id().'</span></div>'.
                    '<div>' .
                    $raw_html .
                    '</div>' .
                    '<div>&nbsp;</div>';

                wp_enqueue_script($this->plugin_name . '-analytics', 'https://cdn.routeapp.io/route-analytics/route-analytics.js', array(), $this->version, false);
                wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/routeapp-analytics.js', array('jquery'), $this->version, false);
            }
        }

        return $output;
    }

    public function route_widget_shortcode()
    {
        if(is_admin() || empty($this->routeapp_get_public_token())) return;

        $custom_env = getenv('ROUTEAPP_ENVIRONMENT_ENDPOINT');
        if (is_null($custom_env) || !$custom_env) {
            $custom_env = isset($_SERVER['ROUTEAPP_ENVIRONMENT_ENDPOINT']) ? $_SERVER['ROUTEAPP_ENVIRONMENT_ENDPOINT'] : '';
        }
        $custom_env = empty($custom_env) ? 'production' : $custom_env;

        wp_enqueue_script($this->plugin_name . '-widget', 'https://protection-widget.route.com/route-protection-widget.js', array(), $this->version, false);

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/routeapp-public-pbc.js', array('jquery'), $this->version, false);

        wp_enqueue_style($this->plugin_name . '-widget', plugin_dir_url(__FILE__) . 'css/routeapp-widget.css', array(), $this->version, false);

        global $wp;
        $parsedUrl = parse_url(home_url( $wp->request));

        switch($custom_env) {
            case 'stage':
                $custom_env = 'Route.Environment.Dev';
                break;
            default:
                $custom_env = 'Route.Environment.Production';
                break;
        }
        $checkbox = WC()->session->get( 'checkbox_checked' );
        $checkbox = $checkbox ? 'Route.Coverage.ActiveByDefault' : 'Route.Coverage.InactiveByDefault';

        $divId = is_checkout() ? 'route-checkout-radio' : 'route-cart-radio';

        $return = '<div id="'.$divId.'">' .
            '<input type="hidden" value="'. get_woocommerce_currency() .'" class="routeapp-currency">'.
            '<input type="hidden" value="'. round($this->get_cart_subtotal_with_only_shippable_items(WC()->cart), 2) .'" class="routeapp-subtotal">'.
            '<input type="hidden" value="' . admin_url('admin-ajax.php') . '" class="routeapp-ajax-url">'.
            '<input type="hidden" value="' . $parsedUrl['host'] . '" class="routeapp-store-domain">'.
            '<input type="hidden" value="' . get_option('blogname') . '" class="routeapp-store-name">'.
            '<input type="hidden" value="' . $this->routeapp_api_client->get_merchant_id() . '" class="routeapp-merchant-id">'.
            '<input type="hidden" value="' . $custom_env . '" class="routeapp-env">'.
            '<input type="hidden" value="' . $checkbox .'" class="routeapp-checkbox-cookie">';

        //check if shipping method is allowed
        $cart = WC()->cart;
        $shipping_method = $cart->calculate_shipping();
        if (isset($shipping_method[0]->method_id)) {
            $shipping_method = $shipping_method[0]->method_id;
            $show_widget =  $this->routeapp_is_shipping_method_allowed($shipping_method);
            if (!$show_widget) {
                $return .= '<input type="hidden" value="true" class="routeapp-invalid-shipping-method">';
            }
        }
        return $return . '<div id="RouteWidget" class="route-div"></div></div>' ;
    }


    public function wc_routeapp_ajaxurl() {
        echo '<script type="text/javascript">' .
            'var wc_routeapp_ajaxurl = "' . admin_url('admin-ajax.php') . '"' .
            '</script>';
    }

    /**
     * get amount from RouteApp API
     *
     * @param $cartRef
     * @param $cartTotal
     * @param $currency
     * @param $cartItems
     * @return false|mixed
     */
    public function routeapp_get_quote_from_api($cartRef, $cartTotal, $currency, $cartItems) {
        if (!$cartRef) return false;
        if (!$cartTotal) return false;
        $currency = !$currency ? get_woocommerce_currency() : $currency;

        $response = $this->routeapp_api_client->get_quote($cartRef, $cartTotal, $currency, $cartItems);

        if (is_wp_error($response)) {
            return false;
        } else {
            if (isset($response['response']['code']) && $response['response']['code'] == 401) {
                return false;
            }
            $price_data = json_decode($response['body']);
            Route_Setup::set_setup_check_widget(Route_Setup::SETUP_CHECK_WIDGET_JS);
            return $price_data;
        }
    }

    /**
     * create order data sending for API
     * @since    1.0.0
     * @param  integer $order_id
     */
    public function create_order_data_api($order_id) {

        $order = wc_get_order( $order_id );

        if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
            && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            // HPOS usage is enabled.
            $quote = $order->get_meta('_routeapp_quote');
        } else {
            // Traditional CPT-based orders are in use.
            $quote = get_post_meta($order->get_id(), '_routeapp_quote', true);
        }

        $insurance_selected = false;
        $insurance_amount = false;

        foreach ($order->get_items('fee') as $fee) {
            if ($fee->get_name() === $this->routeapp_get_insurance_label()) {
                $insurance_selected = true;
                $insurance_amount = $fee->get_amount();
                if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
                    && OrderUtil::custom_orders_table_usage_is_enabled() ) {
                    // HPOS usage is enabled.
                    $order->update_meta_data('_routeapp_route_charge', $fee->get_amount() );
                    $order->save();
                } else {
                    // Traditional CPT-based orders are in use.
                    update_post_meta( $order->get_id(), '_routeapp_route_charge', $fee->get_amount() );
                }
            }
        }
        $quoteId = 'Invalid';
        if ($quote) {
            $quoteDecoded = json_decode($quote);
            $quoteId = $quoteDecoded->id;
        }

        $destination =  array(
            "first_name"		=> $order->get_billing_first_name(),
            "last_name"		    => $order->get_billing_last_name(),
            "street_address1"   => $order->get_shipping_address_1(),
            "street_address2"	=> $order->get_shipping_address_2(),
            "city"			    => $order->get_shipping_city(),
            "province"		    => $order->get_shipping_state(),
            "zip"			    => $order->get_shipping_postcode(),
            "country_code"	    => $order->get_shipping_country(),
        );
        $customerDetails = array(
            "first_name"	=> $order->get_billing_first_name(),
            "last_name"		=> $order->get_billing_last_name(),
            "email"			=> $order->get_billing_email(),
            "phone"			=> $order->get_billing_phone()
        );
        $order_data = $order->get_data();
        $is_shipping_method_allowed = $this->routeapp_is_shipping_method_allowed(false, $order);

        $itemdata = array();

        foreach ($order->get_items() as $item) {
            ## Using WC_Order_Item_Product methods ##
            $product = $item->get_product(); // Get the WC_Product object

            if ($product instanceof WC_Product) {

                $isProductVirtual = (bool)$product->is_virtual();

                $arrItem = array(
                    "delivery_method" => $isProductVirtual ? 'digital' :
                        ($is_shipping_method_allowed ? 'ship_to_home' : 'ship_to_store'),
                    "is_insured" => $isProductVirtual ? false :
                        ($is_shipping_method_allowed ? true : false),
                    "source_product_id" => $product->get_id(),
                    "source_id" => strval($item->get_id()),
                    "sku" => $product->get_sku(),
                    "name" => $product->get_title(),
                    "price" => $product->get_price(),
                    "quantity" => $item->get_quantity(),
                    "image_url" =>  (wp_get_attachment_url($product->get_image_id())) ? wp_get_attachment_url($product->get_image_id()) : null,
                    "upc" => ""// WooCommerce doesn’t provide UPC by default
                );

                array_push($itemdata, $arrItem);
            }

        }

        if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
            && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            // HPOS usage is enabled.
            $order->update_meta_data('_routeapp_route_protection', $insurance_selected );
            $order->update_meta_data('_routeapp_version', ROUTEAPP_VERSION );

            $order->save();
        } else {
            // Traditional CPT-based orders are in use.
            update_post_meta( $order->get_id(), '_routeapp_route_protection', $insurance_selected );
            update_post_meta( $order->get_id(), '_routeapp_version', ROUTEAPP_VERSION );
        }

        $orderData = array(
            'quote_id' => $quoteId,
            'source_order_id' => $order->get_id(),
            'source_order_number' => $order->get_id(),
            'subtotal' => $order->get_subtotal(),
            'order_total' => $order->get_total(),
            'shipping_total' => $order->get_shipping_total(),
            'discounts_total' => $order->get_discount_total(),
            'source_created_on' => date(DATE_ATOM, strtotime($order->get_date_created())),
            'source_updated_on' => date(DATE_ATOM, strtotime($order->get_date_modified())),
            'taxes' => $order_data['total_tax'],
            'insurance_selected' => $insurance_selected,
            'customer_details' => $customerDetails,
            'shipping_details' => $destination,
            'line_items' => $itemdata,
            'currency' => $order->get_currency(),
            'amount_covered' => $this->get_order_subtotal($order),
            'origin' => 'woocommerce-module',
        );
        //if customer covers the order, pass the paid to insure with Route Fee value
        if ($insurance_selected && $insurance_amount) {
            $orderData['paid_to_insure'] = $insurance_amount;
        }

        return $orderData;
    }

    /**
     * Update tracking order data sending for API
     * @since    1.0.0
     * @param  integer $item_id
     * @param  object $order
     * @param  string $post
     */
    public function routeapp_update_tracking_order_api($item_id, $order, $post) {
        if (is_null($order) || get_post_type($order) !== 'shop_order' || $post == '_edit_lock') {
            return false;
        }
        $this->routeapp_shipment_tracking->update($order, $this);
    }

    /**
     * Update tracking order item data sending for API
     * @since    1.0.0
     * @param  integer $order_id
     * @param  object $post
     * @param  boolean $update
     */
    public function routeapp_update_tracking_order_item_api($item_id, $order_item_id, $post) {
        if (is_null($order_item_id) ||
            !is_admin() ||
            $this->_isShippingTrackingCall($post)
        ) {
            return false;
        }
        $this->routeapp_shipment_tracking->update($order_item_id, $this);
    }

    /**
     * Check if the post comes for shipment tracking
     * @param $post
     * @return bool
     */
    private function _isShippingTrackingCall($post) {
        $isNotShippingTracking = $post != '_wc_shipment_tracking_items' &&
            $post != '_bst_tracking_number' &&
            $post != 'mimo_tracking_number' &&
            $post != '_aftership_tracking_number' &&
            $post != 'ywot_tracking_code' &&
            $post != 'ph_shipment_tracking_shipping_service' &&
            $post != 'ph_shipment_tracking_ids' &&
            $post != 'usps_evs_label_details_array' &&
            $post != '_order_trackno' &&
            $post != '_order_trackno1' &&
            $post != '_order_trackno2' &&
            $post != '_order_trackno3' &&
            $post != '_order_trackno4' &&
            $post != '_vi_wot_order_item_tracking_data' &&
            $post != 'wc_connect_labels';

        return apply_filters('routeapp_meta_key_is_not_shipping_related', $isNotShippingTracking, $post);
    }


    public function routeapp_update_merchant_api( $merchant_id, $merchantData ) {
        if ( is_null($merchant_id) ) {
            return false;
        }

        $response = $this->routeapp_api_client->update_merchant( $merchant_id, $merchantData );
        if ( is_wp_error($response) ) {
            return false;
        }

        return true;
    }

    /**
     * Save into order meta_data the quote from the last request made on session
     *
     * @param $order_id
     */
    public function routeapp_save_quote_to_order($order_id )
    {
        if (!$order_id && !WC()->session) {
            return;
        }
        $order = wc_get_order($order_id);
        if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
            && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            // HPOS usage is enabled.
            $quote = $order->get_meta('_routeapp_quote');
        } else {
            // Traditional CPT-based orders are in use.
            $quote = get_post_meta($order->get_id(), '_routeapp_quote', true);
        }
        if (!$quote) {
            $cartRef = $order->get_cart_hash();
            if (!$cartRef) {
                //create uniq ID for backend orders
                $cartRef = uniqid();
            }
            $main_key = $this->routeapp_api_client->get_cache_api_session_key().'-'.$cartRef;
            $key = $main_key.'-latest';
            if (WC()->session) {
                $cached = WC()->session->get($key);
                if (isset($cached) && is_array($cached) && isset($cached['body'])) {

                    $acceptedOrderStatuses = get_option('routeapp_included_order_statuses') ? get_option('routeapp_included_order_statuses') : [];
                    $acceptedCanceledStatuses = get_option('routeapp_cancel_order_statuses') ? get_option('routeapp_cancel_order_statuses') : [];


                    foreach ($order->get_items('fee') as $fee) {
                        if ($fee->get_name() === $this->routeapp_get_insurance_label()) {
                            $insurance_selected = true;
                            $insurance_amount = $fee->get_amount();
                            if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
                              && OrderUtil::custom_orders_table_usage_is_enabled() ) {
                                // HPOS usage is enabled.
                                $order->update_meta_data('_routeapp_route_charge', $insurance_amount );
                                $order->update_meta_data('_routeapp_route_protection', $insurance_selected);
                                $order->save();
                            } else {
                                // Traditional CPT-based orders are in use.
                                update_post_meta( $order->get_id(), '_routeapp_route_charge', $insurance_amount );
                                update_post_meta( $order->get_id(), '_routeapp_route_protection', $insurance_selected );
                            }
                        }
                    }
                    
                    if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
                        && OrderUtil::custom_orders_table_usage_is_enabled() ) {
                        // HPOS usage is enabled.
                        $order->update_meta_data('_routeapp_quote', $cached['body'] );

                        //save to order the configuration selected regarding order statuses
                        $order->update_meta_data('_routeapp_included_order_statuses', json_encode($acceptedOrderStatuses));
                        $order->update_meta_data( '_routeapp_cancel_order_statuses', json_encode($acceptedCanceledStatuses));

                        $order->save();
                    } else {
                        // Traditional CPT-based orders are in use.
                        update_post_meta( $order->get_id(), '_routeapp_quote', $cached['body'] );
                        //save to order the configuration selected regarding order statuses
                        update_post_meta( $order->get_id(), '_routeapp_included_order_statuses', json_encode($acceptedOrderStatuses));
                        update_post_meta( $order->get_id(), '_routeapp_cancel_order_statuses', json_encode($acceptedCanceledStatuses));
                    }

                    //clear cache!
                    WC()->session->__unset($main_key);
                    WC()->session->__unset($key);
                }
            }
        }
    }

    /**
     * Mark order on woocommerce thank you hook
     * @since    1.0.0
     * @param  integer $order_id
     *
     */
    public function routeapp_mark_order($order_id)
    {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);

        //order cancel action will be handled by webhook event
        if ($order->get_status() === 'cancelled') {
            return;
        }

        // Init the setup for readiness report
        $this->_setup_readiness_report_check();

        if (WC()->session) {
            WC()->session->__unset('checkbox_checked');
        }
    }

    /**
     * Check order note (look for shipment tracking data on it)
     * @param $noteId id
     * @return mixed
     */
    public function check_order_note_by_note_id($noteId)
    {
        $comment = get_comment($noteId);
        $order = wc_get_order($comment->comment_post_ID);
        if ($order && $order->get_id()) {
            $this->routeapp_shipment_tracking->parse_order_notes($order->get_id(), $this);
        }
    }

    /**
     * Check order note (look for shipment tracking data on it)
     * @param $response WP_REST_Response
     * @return mixed
     */
    public function check_order_note($response)
    {
        $note = $response->get_data();
        $comment = get_comment($note['id']);
        $order = wc_get_order($comment->comment_post_ID);
        if ($order && $order->get_id()) {
            $this->routeapp_shipment_tracking->parse_order_notes($order->get_id(), $this);
        }
        return $response;
    }
    /**
     * Cancel the tracking order sending for API
     * @since    1.0.0
     * @param  integer $order_id
     * @param  integer $tracking_number
     * @param  array $product_ids
     */

    public function routeapp_cancel_tracking_order( $order_id, $tracking_number, $product_ids ) {
        if ( is_null($order_id) ) {
            return false;
        }
        $this->routeapp_shipment_tracking->cancel($order_id, $tracking_number, $product_ids, $this);
    }

    /**
     * Create user at Route
     * @param $data
     * @return array|bool|mixed|object
     */
    public function routeapp_create_user($data){
        $response = $this->routeapp_api_client->create_user($data);

        $this->routeapp_last_request = $response;

        if ( is_wp_error( $response ) ) {
            return false;
        } else {
            $body = json_decode($response['body']);

            if ($response['response']['code'] != self::HTTP_CREATED_CODE) {
                return false;
            }

            return $body;
        }
    }

    /**
     * Create user at Route
     *
     * @param $username
     * @param $password
     *
     * @return array|bool|mixed|object
     */
    public function routeapp_user_login($username, $password){
        $response = $this->routeapp_api_client->login_user($username, $password);

        $this->routeapp_last_request = $response;

        if ( is_wp_error( $response ) ) {
            return false;
        } else {
            $body = json_decode($response['body']);

            if ($response['response']['code'] != self::HTTP_SUCCESS_CODE) {
                return false;
            }

            return $body;
        }
    }

    /**
     * Creates merchant at Route
     *
     * @param $data
     * @return array|bool|mixed|object
     */
    public function routeapp_create_merchant($data){

        $response = $this->routeapp_api_client->create_merchant($data);
        $this->routeapp_last_request = $response;

        if ( is_wp_error( $response ) ) {
            return false;
        } else {
            $body = json_decode($response['body']);

            if ($response['response']['code'] != self::HTTP_CREATED_CODE && $response['response']['code'] != self::HTTP_SUCCESS_CODE) {
                return false;
            }

            return $body;
        }
    }

    /**
     * Creates merchant at Route
     *
     * @param $data
     * @return array|bool|mixed|object
     */
    public function routeapp_get_merchants(){

        $response = $this->routeapp_api_client->get_merchants();
        $this->routeapp_last_request = $response;

        if ( is_wp_error( $response ) ) {
            return false;
        } else {
            $body = json_decode($response['body']);

            if ($response['response']['code'] != self::HTTP_CREATED_CODE && $response['response']['code'] != self::HTTP_SUCCESS_CODE) {
                return false;
            }

            return $body;
        }
    }

    /**
     * Activate user account
     *
     * @param $email
     *
     * @return array|bool|mixed|object
     */
    public function routeapp_activate_account($email){

        $response = $this->routeapp_api_client->activate_account(['email' => $email]);
        $this->routeapp_last_request = $response;

        if ( is_wp_error( $response ) ) {
            return false;
        } else {
            $body = json_decode($response['body']);

            if ($response['response']['code'] != self::HTTP_CREATED_CODE && $response['response']['code'] != self::HTTP_SUCCESS_CODE) {
                return false;
            }

            return $body;
        }
    }

    public function routeapp_is_shipping_method_allowed($shipping_method=false, $order=false) {

        if ($shipping_method || $order) {
            if ($shipping_method) {
                $shipping_method = explode(':', $shipping_method);
                $shipping_method = $shipping_method[0];
            }
            if ($order) {
                $shipping_method = @array_shift($order->get_shipping_methods());
                if(isset($shipping_method['method_id']))
                {
                    $shipping_method = $shipping_method['method_id'];
                } else {
                    return false;
                }
            }
            $excludedShippingMethods = $this->routeapp_get_excluded_shipping_methods();
            if (is_array($excludedShippingMethods) && in_array($shipping_method, $excludedShippingMethods)) {
                return false;
            }
        } else {
            if (WC()->session->get('shipping_for_package_0')) {
                foreach( WC()->session->get('shipping_for_package_0')['rates'] as $method_id => $rate ){
                    if( WC()->session->get('chosen_shipping_methods')[0] == $method_id ){
                        $shippingMethod = explode(':', $rate->id);
                        $shippingMethod = $shippingMethod[0];
                        $excludedShippingMethods = $this->routeapp_get_excluded_shipping_methods();
                        if (in_array($shippingMethod, $excludedShippingMethods)) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }

    public function routeapp_get_insurance_label() {
        return __(self::ROUTE_LABEL, 'routeapp');
    }

    public function routeapp_is_opt_in() {
        $merchant = $this->routeapp_api_client->get_merchant();
        return isset($merchant->merchant_preferences->opt_in) && $merchant->merchant_preferences->opt_in == true;
    }

    public function routeapp_get_public_token() {
        return get_option('routeapp_public_token');
    }

    public static function routeapp_get_taxable_class() {
        return get_option('routeapp_taxable_class');
    }

    public static function routeapp_get_checkout_hook() {
        return get_option('routeapp_checkout_hook') ? get_option('routeapp_checkout_hook') : self::DEFAULT_CHECKOUT_WEBHOOK;
    }

    public static function routeapp_get_checkout_hook_options() {
        return [
            'woocommerce_before_checkout_form' => 'woocommerce_before_checkout_form',
            'woocommerce_checkout_before_customer_details' => 'woocommerce_checkout_before_customer_details',
            'woocommerce_checkout_billing' => 'woocommerce_checkout_billing',
            'woocommerce_before_checkout_billing_form' => 'woocommerce_before_checkout_billing_form',
            'woocommerce_after_checkout_billing_form' => 'woocommerce_after_checkout_billing_form',
            'woocommerce_before_checkout_registration_form' => 'woocommerce_before_checkout_registration_form',
            'woocommerce_after_checkout_registration_form' => 'woocommerce_after_checkout_registration_form',
            'woocommerce_checkout_shipping' => 'woocommerce_checkout_shipping',
            'woocommerce_before_checkout_shipping_form' => 'woocommerce_before_checkout_shipping_form',
            'woocommerce_after_checkout_shipping_form' => 'woocommerce_after_checkout_shipping_form',
            'woocommerce_before_order_notes' => 'woocommerce_before_order_notes',
            'woocommerce_after_order_notes' => 'woocommerce_after_order_notes',
            'woocommerce_checkout_after_customer_details' => 'woocommerce_checkout_after_customer_details',
            'woocommerce_checkout_before_order_review_heading' => 'woocommerce_checkout_before_order_review_heading',
            'woocommerce_checkout_order_review' => 'woocommerce_checkout_order_review',
            'woocommerce_checkout_before_order_review' => 'woocommerce_checkout_before_order_review',
            'woocommerce_review_order_before_cart_contents' => 'woocommerce_review_order_before_cart_contents',
            'woocommerce_review_order_after_cart_contents' => 'woocommerce_review_order_after_cart_contents',
            'woocommerce_review_order_before_shipping' => 'woocommerce_review_order_before_shipping',
            'woocommerce_review_order_after_shipping' => 'woocommerce_review_order_after_shipping',
            'woocommerce_review_order_before_order_total' => 'woocommerce_review_order_before_order_total',
            'woocommerce_review_order_after_order_total' => 'woocommerce_review_order_after_order_total',
            'woocommerce_review_order_before_payment' => 'woocommerce_review_order_before_payment',
            'woocommerce_review_order_before_submit' => 'woocommerce_review_order_before_submit',
            'woocommerce_review_order_after_submit' => 'woocommerce_review_order_after_submit',
            'woocommerce_review_order_after_payment' => 'woocommerce_review_order_after_payment',
            'woocommerce_checkout_after_order_review' => 'woocommerce_checkout_after_order_review',
            'woocommerce_after_checkout_form' => 'woocommerce_after_checkout_form'
        ];
    }

    public static  function routeapp_get_fee_taxable() {
        if (get_option('routeapp_route_fee_taxable') == 'yes') return true;
        return false;
    }

    public function routeapp_get_excluded_shipping_methods() {
        return get_option('routeapp_excluded_shipping_methods') ?
            get_option('routeapp_excluded_shipping_methods') :
            [];
    }

    public function routeapp_get_secret_token() {
        return get_option('routeapp_secret_token');
    }

    public function routeapp_route_enabled_extra_columns() {
        return get_option('routeapp_route_enable_extra_columns') !== "no";
    }

    public function routeapp_get_fresh_installed(){
        return get_option('routeapp_route_fresh_installed');
    }

    public function routeapp_route_show_order_updates() {
        return get_option('routeapp_route_show_order_updates', 'yes') == 'yes';
    }

    private static function routeapp_get_failed_registration(){
        return get_option('routeapp_failed_registration');
    }

    /**
     * If it's installation has failed at user step creation step
     *
     * @return bool
     */
    public function has_user_creation_failed(){
        return self::routeapp_get_failed_registration() == Route_Setup::FAILED_REGISTRATION_STEP_USER;
    }

    /**
     * If it's installation has failed at user step creation step due to conflict
     *
     * @return bool
     */
    public function has_user_creation_conflicted(){
        return !Route_Setup::has_valid_merchant_tokens() && (self::routeapp_get_failed_registration() == Route_Setup::FAILED_REGISTRATION_STEP_USER_DUPLICATED || self::routeapp_get_failed_registration() == Route_Setup::FAILED_REGISTRATION_STEP_USER_LOGIN_FAILED);
    }

    /**
     * If it's installation has failed at Merchant creation step
     *
     * @return bool
     */
    public function has_merchant_creation_failed(){
        return self::routeapp_get_failed_registration() == Route_Setup::FAILED_REGISTRATION_STEP_MERCHANT  && !Route_Setup::has_valid_merchant_tokens();
    }
    /**
     * If it's installation has failed at Merchant creation step
     *
     * @return bool
     */
    public function has_merchant_creation_conflicted(){
        return self::routeapp_get_failed_registration() == Route_Setup::FAILED_REGISTRATION_STEP_MERCHANT_DUPLICATED && !Route_Setup::has_valid_merchant_tokens();
    }

    /**
     * If it's installation has failed at Merchant creation step
     *
     * @return bool
     */
    public function successful_registration(){
        return self::routeapp_get_failed_registration() === Route_Setup::REGISTRATION_FIXED || self::routeapp_get_failed_registration() === Route_Setup::REGISTRATION_STEP_USER_LOGIN_SUCCESS;
    }

    /**
     * Get registration step at setup flow
     * @return mixed
     */
    private static function registration_step(){
        return get_option(Route_Setup::REGISTRATION_STEP);
    }

    /**
     * Check if at the merchant create flow it was already redirected
     * @return bool
     */
    public static function was_redirected(){
        return self::registration_step() == Route_Setup::REDIRECTED;
    }

    /**
     * Set it as redirected to Route after registration is complete
     * @return void
     */
    public function set_as_redirected(){
        update_option(Route_Setup::REGISTRATION_STEP, Route_Setup::REDIRECTED);
    }

    public function routeapp_register_status_route() {
        register_rest_route( 'route', 'status', array(
                'methods' => 'GET',
                'callback' => function() {
                    $response = array(
                        'version' => ROUTEAPP_VERSION,
                        'public_token' => !empty($this->routeapp_get_public_token()) ?
                            $this->_encodeToken($this->routeapp_get_public_token()) :
                            false,
                        'secret_key' => !empty($this->routeapp_get_secret_token()) ?
                            $this->_encodeToken($this->routeapp_get_secret_token()) :
                            false,
                        'route_insurance_label' => $this->routeapp_get_insurance_label(),
                        'extra_columns_enabled' => $this->routeapp_route_enabled_extra_columns(),
                        'failed_registration' => $this->routeapp_get_failed_registration(),
                        'base_currency' => get_woocommerce_currency(),
                        'date' => array(
                            'date' => date('Y-m-d H:i:s'),
                            'timezone_type' => date('P'),
                            'timezone' => date_default_timezone_get()
                        ),
                        'routeapp_route_fee_taxable' => $this->routeapp_get_fee_taxable(),
                        'routeapp_taxable_class' => $this->routeapp_get_taxable_class(),
                        'routeapp_excluded_shipping_methods' => $this->routeapp_get_excluded_shipping_methods(),
                        'routeapp_route_show_order_updates' => $this->routeapp_route_show_order_updates(),
                        'routeapp_checkout_hook' => $this->routeapp_get_checkout_hook(),
                        'routeapp_have_webhook_secret' => $this->_routeapp_have_webhook_secret()
                    );
                    return rest_ensure_response( $response );
                },
                'permission_callback' => '__return_true'
            )
        );
    }

    private function _routeapp_have_webhook_secret() {
        return get_option('_routeapp_webhooks_secret') ? 'Yes' : 'No';
    }

    private function _encodeToken($token) {
        return substr($token, 0, 5) . '...' . substr($token, -5);
    }

    public function routeapp_register_checkout_route(){

        if (!isset(WC()->session) || !isset($_POST) || !isset($_POST['shipping_method'])) {
            return rest_ensure_response( [] );
        }

        $shipping_method = $_POST['shipping_method'];

        //check first if shipping method is allowed
        if ($shipping_method) {
            $show_widget =  $this->routeapp_is_shipping_method_allowed($shipping_method);
            if (!$show_widget) {
                wp_send_json(['hide-widget' => true]);
                return;
            }
        }

        $cart = WC()->cart;
        $this->checkout_route_insurance_fee($cart);
        $response= [
            'routeapp-subtotal' => round($this->get_cart_subtotal_with_only_shippable_items($cart), 2),
        ];

        wp_send_json( $response );
    }

    /**
     * Add route to recreate User and Merchant Accounts
     * if it has failed at the first try
     */
    public function routeapp_register_setup_route () {
        register_rest_route( 'route', 'recreate_user', array(
            'methods' => 'GET',
            'callback' => [Route_Setup::class, 'retry_user'],
            'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'route', 'recreate_merchant', array(
            'methods' => 'GET',
            'callback' => [Route_Setup::class, 'retry_merchant'],
            'permission_callback' => '__return_true'
        ) );
        register_rest_route( 'route', 'user_login', array(
            'methods' => 'POST',
            'callback' => [Route_Setup::class, 'user_login'],
            'permission_callback' => '__return_true'
        ) );
    }

    public function routeapp_last_request_has_failed(){
        if ( is_wp_error( $this->routeapp_last_request ) || !isset($this->routeapp_last_request)) {
            return true;
        } else {
            if ($this->routeapp_last_request['response']['code'] != self::HTTP_CREATED_CODE && $this->routeapp_last_request['response']['code'] != self::HTTP_SUCCESS_CODE) {
                return true;
            }

            return false;
        }
    }

    public function routeapp_last_request_has_success() {
        if ( is_wp_error( $this->routeapp_last_request ) || !isset($this->routeapp_last_request)) {
            return false;
        }
        return $this->routeapp_last_request['response']['code'] === self::HTTP_SUCCESS_CODE;
    }

    public function routeapp_last_request_has_conflicted(){
        if ( is_wp_error( $this->routeapp_last_request ) || !isset($this->routeapp_last_request)) {
            return false;
        } else {
            if ($this->routeapp_last_request['response']['code'] == self::HTTP_CONFLICT_CODE) {
                return true;
            }

            return false;
        }
    }

    public function routeapp_log($event, $extraData = false) {
        $this->routeapp_logger->sentry_log($event, $extraData);
    }

    private function _setup_readiness_report_check()
    {
        if ($this->route_app_setup_helper->can_send_compatibility_report()) {
            /**
             * API CALL for incompatibility test
             */
            $api = new Routeapp_API_Compatibility();
            $api->send($this->route_app_setup_helper->prepare_compatibility_data());
        }
    }

    public function get_order_subtotal($order)
    {
        $subtotal = 0;

        if (is_a($order, 'WC_Order')) {
            $order_items = $order->get_items();

            foreach ($order_items as $order_item_id => $order_item) {
                if ($this->isProductVirtual($order_item)) {
                    //if the product is not found or virtual, skip it
                    continue;
                }
                $subtotal += $order_item->get_subtotal();
            }
        }
        return $subtotal;
    }

    /**
     * Check if product inside order item is virtual, return true if product is not valid
     * @param $item
     * @return bool
     */
    public function isProductVirtual($item) {
        $productId = isset($item['variation_id']) && !empty($item['variation_id']) ?
            $item['variation_id'] :
            $item['product_id'];

        $product = wc_get_product($productId);
        if ($product) {
            return $product->is_virtual();
        }
        return true;
    }

    /**
     * Process cart item
     *
     * @param $cart_item
     * @param $allowed_bundle_parents
     * @return array
     */
    public function processCartItem($cart_item, $allowed_bundle_parents) {

        $cart_item_subtotal = $cart_item['line_subtotal'];

        //support for WooCommerce Product Bundles
        if (isset($cart_item['bundled_items'])) {
            if ($cart_item['data']->get_data()['price'] > 0) {
                //grab item price from parent bundle if exist, return directly
                $cart_item_subtotal = $cart_item['data']->get_data()['price'] * $cart_item['quantity'];
                $data = [
                    'id' => strval($cart_item['product_id']),
                    'quantity' => intval($cart_item['quantity']),
                    'unit_price' => strval(round($cart_item_subtotal / $cart_item['quantity'], 2))
                ];
                return ['allowed_bundle_parents' => $allowed_bundle_parents, 'data' => $data];
            } else{
                //add bundle parent key to allowed array
                $allowed_bundle_parents[] = $cart_item['key'];
            }
        }

        if ($this->isProductVirtual($cart_item)) {
            //if the product is not found or virtual, skip it
            return ['allowed_bundle_parents' => $allowed_bundle_parents];
        }

        if (isset($cart_item['bundled_by'])) {
            if (!in_array($cart_item['bundled_by'], $allowed_bundle_parents)) {
                //skip bundle item children (price is on parent)
                return ['allowed_bundle_parents' => $allowed_bundle_parents];
            } else {
                //grab subtotal from bundle child
                $cart_item_subtotal = $cart_item['data']->get_data()['price'] * $cart_item['quantity'];
            }
        }

        return  [
            'allowed_bundle_parents' => $allowed_bundle_parents,
            'data' => [
                'id' => strval($cart_item['product_id']),
                'quantity' => intval($cart_item['quantity']),
                'unit_price' => strval(round($cart_item_subtotal / $cart_item['quantity'], 2))
            ]
        ];
    }

    /**
     * Get cart item subtotal
     *
     * @param $cart_item
     * @param $allowed_bundle_parents
     * @return array
     */
    public function getCartItemSubtotal($cart_item, $allowed_bundle_parents) {

        $cart_item_subtotal = $cart_item['line_subtotal'];

        //support for WooCommerce Product Bundles
        if (isset($cart_item['bundled_items'])) {
            if ($cart_item['data']->get_data()['price'] > 0) {
                //grab item price from parent bundle if exist, return directly
                $cart_item_subtotal = $cart_item['data']->get_data()['price'] * $cart_item['quantity'];
                return ['allowed_bundle_parents' => $allowed_bundle_parents, 'subtotal' => $cart_item_subtotal];
            } else{
                //add bundle parent key to allowed array
                $allowed_bundle_parents[] = $cart_item['key'];
            }
        }

        if ($this->isProductVirtual($cart_item)) {
            //if the product is not found or virtual, skip it
            return ['allowed_bundle_parents' => $allowed_bundle_parents];
        }

        if (isset($cart_item['bundled_by'])) {
            if (!in_array($cart_item['bundled_by'], $allowed_bundle_parents)) {
                //skip bundle item children (price is on parent)
                return ['allowed_bundle_parents' => $allowed_bundle_parents];
            } else {
                //grab subtotal from bundle child
                $cart_item_subtotal = $cart_item['data']->get_data()['price'] * $cart_item['quantity'];
            }
        }
        return ['allowed_bundle_parents' => $allowed_bundle_parents, 'subtotal' => $cart_item_subtotal];
    }

    /**
     * get cart shippable items to grab quote on RouteApp
     *
     * @param $cart
     * @param string $object
     * @return array
     */
    public function get_cart_shippable_items($cart, $object='cart')
    {
        $cartItems = [];
        if (!$cart) {
            return $cartItems;
        }
        $allowed_bundle_parents = [];
        if ($object=='cart') {
            foreach ( $cart->get_cart() as $cart_item ) {
                $cartItemData = $this->processCartItem($cart_item, $allowed_bundle_parents);
                $allowed_bundle_parents = $cartItemData['allowed_bundle_parents'];
                if (isset($cartItemData['data'])) {
                    $cartItems[] = $cartItemData['data'];
                }
            }
        } else {
            foreach ( $cart->get_items() as $cart_item ) {
                $cartItemData = $this->processCartItem($cart_item, $allowed_bundle_parents);
                $allowed_bundle_parents = $cartItemData['allowed_bundle_parents'];
                if (isset($cartItemData['data'])) {
                    $cartItems[] = $cartItemData['data'];
                }
            }
        }

        return $cartItems;
    }

    /**
     * get subtotal for shippable items only
     *
     * @param $cart
     * @param string $object
     * @return float|int|mixed
     */
    public function get_cart_subtotal_with_only_shippable_items($cart, $object='cart')
    {
        $subtotal = 0;
        if (!$cart) {
            return $subtotal;
        }
        $allowed_bundle_parents = [];

        if ($object=='cart') {
            foreach ( $cart->get_cart() as $cart_item ) {
                $itemSubtotal = $this->getCartItemSubtotal($cart_item, $allowed_bundle_parents);
                $allowed_bundle_parents = $itemSubtotal['allowed_bundle_parents'];
                if (isset($itemSubtotal['subtotal'])) {
                    $subtotal += $itemSubtotal['subtotal'];
                }
            }
        } else {
            foreach ( $cart->get_items() as $cart_item ) {
                $itemSubtotal = $this->getCartItemSubtotal($cart_item, $allowed_bundle_parents);
                $allowed_bundle_parents = $itemSubtotal['allowed_bundle_parents'];
                if (isset($itemSubtotal['subtotal'])) {
                    $subtotal += $itemSubtotal['subtotal'];
                }
            }
        }
        return $subtotal;
    }

    /**
     * Edit Avatax fee line (send tax class name instead item_id)
     */
    public function routeapp_edit_avatax_fee($fee_line, $fee = null)
    {
        if (isset($fee_line['description']) && $fee_line['description'] == $this->routeapp_get_insurance_label()) {
            $fee_line['itemCode'] = $this->routeapp_get_taxable_class();
        }

        return $fee_line;
    }


    /**
     * This method adds to xml export an SKU tag in order to identify
     * Route Protection to ShipStation integration
     *
     * @param $order_xml
     * @return mixed
     */
    public function routeapp_add_sku_to_route_node($order_xml){

        if ($order_xml instanceof DOMElement){

            $items = $order_xml->getElementsByTagName('Items');

            foreach ($items as $itemNode) {
                foreach ($itemNode->getElementsByTagName('Item') as $item) {
                    foreach ($item->getElementsByTagName('Name') as $name) {
                        if (trim($name->nodeValue) === self::ROUTE_LABEL)
                        {
                            $itemNode->removeChild($item);
                        }
                    }
                }
            }

        }

        return $order_xml;
    }

}

