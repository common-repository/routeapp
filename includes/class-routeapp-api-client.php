<?php
/**
 * WooCommerce Routeapp API Client Class
 *
 * @link       https://route.com/
 * @since      1.0.0
 *
 * @package    Routeapp
 * @subpackage Routeapp/includes
 */

class Routeapp_API_Client
{
    /**
     * API base endpoint v1
     */
    const API_ENDPOINT_V1 = 'https://api.route.com/v1/';

    /**
     * API stage base endpoint v1
     */
    const API_STAGE_ENDPOINT_V1 = 'https://api-stage.route.com/v1/';

    /**
     * API base endpoint v2
     */
    const API_ENDPOINT_V2 = 'https://api.route.com/v2/';

    /**
     * API stage base endpoint v2
     */
    const API_STAGE_ENDPOINT_V2 = 'https://api-stage.route.com/v2/';

    /**
     * Merchant ID Option name
     */
    const ROUTEAPP_MERCHANT_ID = 'routeapp_merchant_id';

    /**
     * The v1 API URL
     * @var string
     */
    private $_api_url;

    /**
     * The v2 API URL
     * @var string
     */
    private $_api_url_v2;

    /**
     * The WooCommerce Merchant Key
     * @var string
     */
    private $_public_token;

    /**
     * The WooCommerce Merchant Key
     * @var string
     */
    private $_secret_token;


    /**
     * Merchant
     */
    private $_merchant;

    /**
     * Data used for API Call
     * @var array
     */
    protected $_extraData = [];

    /**
     * Cache API calls
     */
    private $_cachedApiCallsSessionKey = 'route_get_quote';

    private static $instances = [];

    /**
     * Default contructor
     * @param string  $public_token   The consumer key
     * @param string  $secret_token   The consumer key
     */
    public function __construct($public_token = null, $secret_token = null)
    {
        $this->set_public_token($public_token);
        $this->set_secret_token($secret_token);

        $custom_env = getenv('ROUTEAPP_ENVIRONMENT_ENDPOINT');
        if (is_null($custom_env) || !$custom_env) {
            $custom_env = isset($_SERVER['ROUTEAPP_ENVIRONMENT_ENDPOINT']) ? $_SERVER['ROUTEAPP_ENVIRONMENT_ENDPOINT'] : '';
        }
        if ($custom_env == 'stage') {
            $this->_api_url = rtrim($this->_api_url ?? '', '/') . self::API_STAGE_ENDPOINT_V1;
            $this->_api_url_v2 = rtrim($this->_api_url_v2 ?? '', '/') . self::API_STAGE_ENDPOINT_V2;
        } else {
            $this->_api_url = rtrim($this->_api_url ?? '', '/') . self::API_ENDPOINT_V1;
            $this->_api_url_v2 = rtrim($this->_api_url_v2 ?? '', '/') . self::API_ENDPOINT_V2;
        }
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone()
    {}

    public static function getInstance()
    {
        $cls = static::class;
        if (!isset(static::$instances[$cls])) {
            static::$instances[$cls] = new static;
        }
        return static::$instances[$cls];
    }

    /**
     * Set the public token
     * @param string $token
     */
    public function set_public_token($token)
    {
        $this->_public_token = $token;
    }

    /**
     * Set the secret token
     * @param string $token
     */
    public function set_secret_token($token)
    {
        $this->_secret_token = $token;
    }

    /**
     * Get the public token
     * @return string string
     */
    public function get_public_token()
    {
        return !empty($this->_public_token) ? $this->_public_token : get_option('routeapp_public_token');
    }

    public function get_cache_api_session_key()
    {
        return $this->_cachedApiCallsSessionKey;
    }

    /**
     * Get the secret token
     * @return string string
     */
    public function get_secret_token()
    {
        return !empty($this->_secret_token) ? $this->_secret_token : get_option('routeapp_secret_token');
    }

    /**
     * Get the user token
     * @return string string
     */
    public function get_user_token()
    {
        return get_option('routeapp_user_token');
    }
    /**
     * Get the user id
     * @return string string
     */
    public function get_user_id()
    {
        return get_option('routeapp_user_id');
    }

    /**
     * Get current quote price based on subtotal
     * @param $cartRef
     * @param $cartTotal
     * @param $currency
     * @param $cartItems
     * @return array|mixed
     */
    public function get_quote($cartRef, $cartTotal, $currency, $cartItems)
    {
        $currency = !$currency ? get_woocommerce_currency() : $currency;
        $cartTotal = !is_null($cartTotal) && $cartTotal > 0 ? $cartTotal : 0;
        $merchant_id = $this->get_merchant_id();

        //empty subtotal or merchant_id just return zero
        if ($cartTotal==0 || !$merchant_id) return ['body' => json_encode(['premium' => ['amount' => '0']])];

        //check values on cache
        $cached = false;
        $key = $this->get_cache_api_session_key() . '-' . $cartRef;
        if (WC()->session) {
            $cached = WC()->session->get($key);
        }
        if ($cached) {
            if (time() - $cached['createdAt'] > 1800) {
                //if creation date is more than 30 minutes, we unset it
                WC()->session->__unset($key);
            } else {
                return $cached['result'];
            }
        }

        $cached['createdAt'] = time();
        $cached['result'] = $this->_make_private_api_call('quotes', array(
            'merchant_id' => $merchant_id,
            'cart' => [
                'cart_ref' => strval($cartRef),
                'covered' => [
                    'currency' => strval($currency),
                    'amount' => strval($cartTotal)
                ],
                'cart_items' => $cartItems,
            ],
        ), 'POST', 'v2');
        if (WC()->session) {
            WC()->session->set($key, $cached);
            $lastCalledMade = $key. '-latest';
            WC()->session->set($lastCalledMade, $cached['result']);
        }
        return $cached['result'];
    }

    /**
     * Create the order shipment, currently only status update suported by API
     * @param  integer $tracking_id
     * @param  array  $data
     * @return mixed|json string
     */
    public function create_shipment($tracking_id, $data = array())
    {
        if (empty($tracking_id)) return false;
        return $this->_make_private_api_call('shipments', array(
            'tracking_number' => $this->sanitize_value($tracking_id),
            'source_order_id' => $data['source_order_id'],
            'source_product_ids' => $data['source_product_ids'],
            'courier_id' => $this->sanitize_value($data['courier_id']),
        ), 'POST');
    }

    /**
     *
     * Sanitize shipstation tracking numbers. Moved to here from the
     * class-routeapp-shipstation.php script because it is sometimes
     * getting bypassed and orders are coming through with "-(SHIPSTATION)"
     * at the end. Also added a more specific check for both a shipstation
     * prefix and suffix WITH the dash since shipstation has moved the
     * shipstation label to the back AND orders were coming through with
     * either a leading or trailing "-"
     *
     * @param $value
     * @return array|string
     */

    private function sanitize_value($value) {
        $value = str_replace(['-(SHIPSTATION)', '(SHIPSTATION)-', '(Shipstation)'], '', $value);
        $value = str_replace('.', '', $value);
        $value = trim($value);
        return $value;
    }

    /**
     * Get the order shipment
     * @param  integer $tracking_id
     * @param  integer $order_id
     * @param  array  $data
     * @return mixed|json string
     */
    public function get_shipment($tracking_id, $order_id)
    {
        if (empty($tracking_id) || empty($order_id)) return false;
        return $this->_make_private_api_call('shipments/' . $tracking_id . '?source_order_id=' . $order_id);
    }

    /**
     * Update the order shipment, currently only status update suported by API
     * @param  integer $tracking_id
     * @param  integer $order_id
     * @param  array  $data
     * @return mixed|json string
     */
    public function update_shipment($tracking_id, $order_id, $data = array())
    {
        if (empty($tracking_id) || empty($order_id)) return false;
        return $this->_make_private_api_call('shipments/' . $tracking_id . '?source_order_id=' . $order_id, array(
            'source_order_id' => $data['source_order_id'],
            'source_product_ids' => $data['source_product_ids'],
            'courier_id' => $data['courier_id'],
        ), 'POST');
    }

    /**
     * Cancel the order shipment, currently only status update suported by API
     * @param  integer $tracking_id
     * @param  integer $order_id
     * @param  array  $data
     * @return mixed|json string
     */
    public function cancel_shipment($tracking_id, $data = array())
    {
        if (empty($tracking_id)) return false;
        return $this->_make_private_api_call('shipments/' . $tracking_id . '/cancel' . '?source_order_id=' . $data['source_order_id'], array(
            'source_order_id' => $data['source_order_id'],
            'source_product_ids' => $data['source_product_ids'],
        ), 'POST');
    }

    /**
     * Create the order, currently only status update suported by API
     * @param  integer $data
     * @return mixed|json string
     */
    public function create_order($data)
    {
        return $this->_make_private_api_call('orders', $data, 'POST', 'v2');
    }

    /**
     * Get the order
     * @param  integer $source_order_id
     * @return mixed|json string
     */
    public function get_order($source_order_id)
    {
        return $this->_make_private_api_call('orders/' . $source_order_id, 'GET');
    }

    /**
     * Update the order, currently only status update suported by API
     * @param  integer $data
     * @return mixed|json string
     */
    public function update_order($order_id, $data)
    {
        return $this->_make_private_api_call('orders/' . $order_id, $data, 'POST', 'v2');
    }

    /**
     * Cancel the order, currently only status update suported by API
     * @param  integer $order_id
     * @return mixed|json string
     */
    public function cancel_order($order_id)
    {
        return $this->_make_private_api_call('orders/' . $order_id . '/cancel', array(), 'POST');
    }

    /**
     * Get user billing status settings
     * @param  integer $order_id
     * @return mixed|json string
     */
    public function get_billing()
    {
        return $this->_make_private_api_call('billing', array(), 'GET');
    }

    /**
     * Get the Route Merchant ID

     * @return mixed
     */
    public function get_merchant_id()
    {
      return get_option(self::ROUTEAPP_MERCHANT_ID);
    }

    /**
     * Get the Route Merchant ID
     *
     * @param $merchant_id
     * @param $blog_id
     *
     * @return mixed
     */
    public function set_merchant_id($merchant_id, $blog_id = null){
        if (isset($blog_id) && is_multisite()) {
            return update_blog_option($blog_id, self::ROUTEAPP_MERCHANT_ID, $merchant_id);
        }
        return update_option(self::ROUTEAPP_MERCHANT_ID , $merchant_id);
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    /**
     * Get merchant

     * @return mixed
     */
    public function get_merchant()
    {
        if (!empty($this->_merchant)) {
            return $this->_merchant;
        }

        $endpoint = 'merchants';
        $merchantResponse =  $this->get_merchant_id() ? 
          $this->_make_private_api_call($endpoint . '/' . $this->get_merchant_id(), array(), 'GET') : 
          $this->_make_private_api_call($endpoint, array(), 'GET');

        try {
          $response_code = wp_remote_retrieve_response_code($merchantResponse);

          if ( is_wp_error($merchantResponse) || $response_code != 200 ) {
            if ($response_code !== 403) {
              $errorMsg = is_wp_error($merchantResponse) ? $merchantResponse->get_error_message() : $response_code;
              throw new Exception("Route API Error while getting merchant data: " . $errorMsg);
            }

            $merchantResponse = $this->_make_private_api_call($endpoint, array(), 'GET');
          }
        } catch(Exception $exception) {
            $routeapp_public = self::get_route_public_instance();
            $routeapp_public->routeapp_log($exception, $this->_extraData);
            return false;
        }

        if ($merchantResponse) {
            if ($merchantResponse["body"]) {
                $body = json_decode($merchantResponse["body"]);
                $merchant = is_array($body) ? $body[0] : $body;

                if ($merchant) {
                  $this->_merchant = $merchant;

                  if (empty($this->get_merchant_id()) || (isset($merchant->id) && $merchant->id !== $this->get_merchant_id())) {
                      $this->set_merchant_id($merchant->id, get_current_blog_id());
                  }
                  return $this->_merchant;
                }
            }

        }
    }
    
    /**
     * Create user account
     * @param array $data
     * @return mixed|json string
     */
    public function create_user($data) {
        return $this->_make_private_api_call( 'users', $data, 'POST' );
    }

    /**
     * Create user account
     * @param $username
     * @param $password
     * @return mixed|json string
     */
    public function login_user($username, $password) {
        return $this->_make_private_api_call( 'login', [
            "username" => $username,
            "password" => $password
        ], 'POST' );
    }

    /**
     * Create merchant account
     * @param array $data
     * @return mixed|json string
     */
    public function create_merchant($data) {
        return $this->_make_private_api_call_using_user_token( 'merchants', $data, 'POST' );
    }

    /**
     * Get merchant account by user
     * @return mixed|json string
     */
    public function get_merchants() {
        return $this->_make_private_api_call_using_user_token( "users/" . $this->get_user_id() . "/merchants", [], 'GET' );
    }

    /**
     * Get activate account link at Route API
     *
     * @param array $email
     * @return mixed|json string
     */
    public function activate_account($email) {
        return $this->_make_private_api_call( 'activate_account', $email, 'POST' );
    }

    /**
     * Get asset settings
     * @param $apiHost
     * @return mixed|json string
     */
    public function asset_settings($apiHost) {
        return $this->_make_public_api_call("asset-settings/$apiHost", array(), 'GET');
    }

    /**
     * Update the account status at Route API
     *
     * @return mixed|json string
     */
    public function update_merchant_status($status) {
      $endpoint = 'merchants/' . $this->get_merchant_id();
      $params = ['status' => $status];
      return $this->_make_private_api_call( $endpoint, $params, 'POST' );
    }

    /*
     * Make the call to the API
     * @param  string $endpoint
     * @param  array  $params
     * @param  string $method
     * @param  string $version
     * @return mixed|json string
     */
    private function _make_api_call($token, $endpoint, $params = array(), $method = 'GET', $version='v1')
    {
        $url = $version=='v1' ? $this->_api_url : $this->_api_url_v2;
        $url.= $endpoint;

        $extraData = array(
            'params' => $params,
            'method' => $method,
            'endpoint' => $url
        );
        $this->_extraData = $extraData;

        $headers = [
            'Content-Type' => 'application/json',
            'token' => $token,
        ];
        if ($version=='v2') {
            $headers['Protect-Widget-Version'] = 'route-widget-core';
        }
        //platform
        $headers['platform'] = 'woocommerce';

        //woocommerce + wordpress version
        $wooVersion = '';
        if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) &&
            defined('WC_VERSION') ) {
            $wooVersion = WC_VERSION;
        }
        $wordpressVersion = '';
        if (function_exists('get_bloginfo')) {
            $wordpressVersion = get_bloginfo('version');
        }
        $headers['platform_version'] = 'WooCommerce: ' . $wooVersion . ' WordPress: ' . $wordpressVersion;

        //route module version
        $module_version= defined('ROUTEAPP_VERSION') ? ROUTEAPP_VERSION :'';
        $headers['module_version'] = $module_version;

        $args = array(
            'timeout' => 6,
            'method' => $method,
            'headers' => $headers,
            'body' => $method === 'POST' ? json_encode($params) : null
        );

        return wp_remote_request($url, $args);
    }

    private function _make_public_api_call($endpoint, $params = array(), $method = 'GET', $version='v1')
    {
        return $this->_make_api_call($this->get_public_token(), $endpoint, $params, $method, $version);
    }

    protected function _make_private_api_call($endpoint, $params = array(), $method = 'GET', $version='v1')
    {
        return $this->_make_api_call($this->get_secret_token(), $endpoint, $params, $method, $version);
    }

    protected function _make_private_api_call_using_user_token($endpoint, $params = array(), $method = 'GET', $version='v1')
    {
        return $this->_make_api_call($this->get_user_token(), $endpoint, $params, $method, $version);
    }
}
