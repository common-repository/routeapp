<?php
/**
 * A Route Magento Extension that adds secure shipping
 * insurance to your orders
 *
 * Php version 7.0^
 *
 * @category
 * @package   Route_Route
 * @author    Route Development Team <dev@routeapp.io>
 * @copyright 2019 Route App Inc. Copyright (c) https://www.routeapp.io/
 * @license   https://www.routeapp.io/merchant-terms-of-use  Proprietary License
 * @link      https://magento.routeapp.io/magento2/index.html
 */


class Route_Setup
{

    const WOOCOMMERCE = "woocommerce";

    const REGISTRATION_FIXED = '0';
    const REGISTRATION_STEP_USER_LOGIN_SUCCESS = '1-200';
    const FAILED_REGISTRATION_STEP_USER = "1";
    const FAILED_REGISTRATION_STEP_USER_DUPLICATED = '1-409';
    const FAILED_REGISTRATION_STEP_USER_LOGIN_FAILED = '1-401';
    const FAILED_REGISTRATION_STEP_USER_ACTIVATION = '2';
    const FAILED_REGISTRATION_STEP_MERCHANT = '3';
    const FAILED_REGISTRATION_STEP_MERCHANT_DUPLICATED = '3-409';

    const FAILED_REGISTRATION = 'routeapp_failed_registration';
    const USER_TOKEN_OPTION = 'routeapp_user_token';
    const USER_ID_OPTION = 'routeapp_user_id';
    const SECRET_TOKEN_OPTION = 'routeapp_secret_token';
    const PUBLIC_TOKEN_OPTION = 'routeapp_public_token';
    const USER_ID = 'routeapp_user_id';
    const REGISTRATION_STEP = 'routeapp_route_registration_step';
    const DASHBOARD = 'https://dashboard.route.com/login?redirect=onboarding';
    const DASHBOARD_STAGE = 'https://dashboard-stage.route.com/login?redirect=onboarding';
    const REDIRECTED = '2';
    const MODULE_INSTALLED = '1';
    const ACTIVATION_LINK = 'activation_link';
    const SETUP_CHECK_WIDGET_INSTALLATION_DATE = 'route_setup_check_widget_installation_date';
    const SETUP_CHECK_WIDGET_API_CALL = 'route_setup_check_widget_api_call';
    const SETUP_CHECK_WIDGET_PHP = 'route_setup_check_widget_php';
    const SETUP_CHECK_WIDGET_JS = 'route_setup_check_widget_js';
    const ACTIVE = 'Active';

    public static function init()
    {
        if (!self::is_fresh_new_installation()) {
            self::update_merchant_status();
            return;
        }

        self::create_user();
    }

    public static function is_fresh_new_installation()
    {
        return !self::has_valid_merchant_tokens() &&
            !self::registration_step();
    }

    /**
     * Prepare user data, call user create request
     * and handle response
     *
     * @return bool
     */
    public static function create_user()
    {
        $routeapp_public = self::get_route_public_instance();
        $response = $routeapp_public->routeapp_create_user(self::get_user_data());

        try{
            if ($routeapp_public->routeapp_last_request_has_failed()) {
                self::set_registration_failed_as(
                    $routeapp_public->routeapp_last_request_has_conflicted()  ?
                        self::FAILED_REGISTRATION_STEP_USER_DUPLICATED:
                        self::FAILED_REGISTRATION_STEP_USER
                );
                throw new Exception($routeapp_public->routeapp_last_request_has_conflicted()  ?
                    'FAILED_REGISTRATION_STEP_USER_DUPLICATED':
                    'FAILED_REGISTRATION_STEP_USER');
            }
        }catch (Exception $exception) {
            $extraData = array(
                'params' => self::get_user_data(),
                'method' => 'POST',
                'endpoint' => 'users'
            );
            $routeapp_public->routeapp_log($exception,  $extraData);
            return false;
        }

        if (self::register_user($response)) {
            self::set_registration_failed_as(self::REGISTRATION_FIXED);
            self::set_as_installed();

            return true;
        }
    }

    /**
     * Prepare user data, call user create request
     * and handle response
     *
     * @return bool
     */
    public static function register_user_login($username, $password)
    {
        $routeapp_public = self::get_route_public_instance();
        $response = $routeapp_public->routeapp_user_login($username, $password);

        try{
            if ($routeapp_public->routeapp_last_request_has_failed()) {
                self::set_registration_failed_as(self::FAILED_REGISTRATION_STEP_USER_LOGIN_FAILED);
                throw new Exception('FAILED_REGISTRATION_STEP_USER_LOGIN_FAILED');
            }
        }catch (Exception $exception) {
            $extraData = array(
                'params' => array('username' => $username, 'password' => 'XXXXX'),
                'method' => 'POST',
                'endpoint' => 'login'
            );
            $routeapp_public->routeapp_log($exception, $extraData);
            return false;
        }

        if (self::register_user($response, true)) {
            self::set_registration_failed_as(self::REGISTRATION_STEP_USER_LOGIN_SUCCESS);

            self::set_as_installed();

            return true;
        }
    }

    /**
     * @param $response
     * @param $is_active
     *
     * @return bool
     */
    public static function register_user($response, $is_active = false)
    {

        self::set_user_key($response->token);
        self::set_user_id($response->id);

        if (!$is_active) {
            self::active_account();
        }

        return self::create_merchant();
    }

    /**
     * Activate User Account
     *
     * @return bool
     */
    public static function active_account()
    {
        $routeapp_public = self::get_route_public_instance();
        try{
            $response = $routeapp_public->routeapp_activate_account(self::get_current_email());

            try{
                if(!$response){
                    self::set_registration_failed_as(self::FAILED_REGISTRATION_STEP_USER_ACTIVATION);
                    throw new Exception('FAILED_REGISTRATION_STEP_USER_ACTIVATION');
                }
            } catch (Exception $exception) {
                $extraData = array(
                    'params' => self::get_current_email(),
                    'method' => 'POST',
                    'endpoint' => 'activate_account'
                );
                $routeapp_public->routeapp_log($exception, $extraData);
                return false;
            }
            self::set_activation_link($response->set_password_url);
        }catch (Exception $e){
            self::set_registration_failed_as(self::FAILED_REGISTRATION_STEP_USER_ACTIVATION);
            $extraData = array(
                'params' => self::get_current_email(),
                'method' => 'POST',
                'endpoint' => 'activate_account'
            );
            $routeapp_public->routeapp_log($e, $extraData);
            return false;
        }

        self::set_registration_failed_as(self::REGISTRATION_FIXED);

        return true;

    }

    public static function has_valid_merchant_tokens(){
        $routeapp_public = self::get_route_public_instance();
        return self::has_merchant_tokens() &&
            !empty($routeapp_public->routeapp_api_client->get_merchant());
    }

    public static function has_merchant_tokens(){
        $routeapp_public = self::get_route_public_instance();
        return
            !empty($routeapp_public->routeapp_get_public_token()) &&
            !empty($routeapp_public->routeapp_get_secret_token());
    }

    public static function get_route_public_instance(){
        global $routeapp_public;
        return $routeapp_public;
    }

    /**
     * @param $store_domain
     * @param $merchant_store_domain
     * @return bool
     */
    public static function is_same_domain($store_domain, $merchant_store_domain)
    {
        if(strpos($store_domain,'www.') === 0 || strpos($merchant_store_domain,'www.') === 0){
            return strpos($store_domain, $merchant_store_domain) !== false || strpos($merchant_store_domain, $store_domain) !== false;
        }

        return $merchant_store_domain === $store_domain;
    }

    /**
     * Prepare merchant data, call merchant create request
     * and handle response
     *
     * @return bool
     */
    public static function create_merchant()
    {
        $created_domains = [];
        $responses = [];

        foreach (self::get_all_sites() as $site){

            //Avoid duplicated merchant to the same user account
            if (in_array($site->domain, $created_domains)) {
                continue;
            }

            $associated = false;
            $responses = [];

            $created_domains[$site->blog_id] = $site->domain;
            $merchant_data = self::get_merchant_data($site);
            $routeapp_public = self::get_route_public_instance();
            $merchant_creation_response = $routeapp_public->routeapp_create_merchant($merchant_data);

            if($merchant_creation_response){
                $responses[$site->blog_id] = $merchant_creation_response;
            }

            try{
                if ($routeapp_public->routeapp_last_request_has_failed()) {


                    $merchants_by_user = $routeapp_public->routeapp_get_merchants();

                    if(!empty($merchants_by_user) && is_array($merchants_by_user)){
                        foreach ($merchants_by_user as $merchant) {
                            if(self::is_same_domain($merchant->store_domain, $site->domain)){
                                $responses[$site->blog_id] = $merchant;
                                $associated = true;
                            }
                        }
                    }

                    if (empty($responses)) {
                        self::set_registration_failed_as(
                            $routeapp_public->routeapp_last_request_has_conflicted()  ?
                                self::FAILED_REGISTRATION_STEP_MERCHANT_DUPLICATED:
                                self::FAILED_REGISTRATION_STEP_MERCHANT
                        );
                        throw new Exception($routeapp_public->routeapp_last_request_has_conflicted()  ?
                            'FAILED_REGISTRATION_STEP_MERCHANT_DUPLICATED':
                            'FAILED_REGISTRATION_STEP_MERCHANT');
                    }
                }
            } catch (Exception $exception) {
                $routeapp_public->routeapp_log($exception);
                return false;
            }



            //TODO Currently when we try to create merchant that already exists it's returning 200 http code success
            //TODO So we need try to update it with current platform
            if (!$associated && $routeapp_public->routeapp_last_request_has_success()) {

                try{
                    if (!self::merchant_can_be_updated($responses[$site->blog_id])) {
                        self::set_registration_failed_as(self::FAILED_REGISTRATION_STEP_MERCHANT);
                        throw new Exception('FAILED_REGISTRATION_STEP_MERCHANT');
                    }
                } catch (Exception $exception) {
                    $routeapp_public->routeapp_log($exception);
                    return false;
                }

                //TODO This is happening when we try to update merchant that user doesn't own
                //TODO So we need try to recreate it with additional slash at the store's domain end
                $merchant_data['store_domain'] = $merchant_data['store_domain'] . '/';
                $responses[$site->blog_id]  = $routeapp_public->routeapp_create_merchant($merchant_data);

                try{
                    if ($routeapp_public->routeapp_last_request_has_failed()) {
                        self::set_registration_failed_as(
                            $routeapp_public->routeapp_last_request_has_conflicted() ?
                                self::FAILED_REGISTRATION_STEP_MERCHANT_DUPLICATED :
                                self::FAILED_REGISTRATION_STEP_MERCHANT);
                        throw new Exception($routeapp_public->routeapp_last_request_has_conflicted() ?
                            'FAILED_REGISTRATION_STEP_MERCHANT_DUPLICATED' :
                            'FAILED_REGISTRATION_STEP_MERCHANT');
                    }
                } catch (Exception $exception) {
                    $routeapp_public->routeapp_log($exception);
                    return false;
                }
            }

        }

        foreach ($responses as $blog_id => $response){
            self::set_public_key($response->public_api_key, $blog_id);
            self::set_secret_key($response->prod_api_secret, $blog_id);
        }

        return true;
    }

    /**
     * @param $response
     * @return bool
     */
    public static function merchant_can_be_updated($response)
    {
        return (isset($response->platform_id) && strtolower($response->platform_id) == 'email') ||
            (isset($response->status) && strtolower($response->status) != 'active');
    }

    /**
     * @param $data
     * @return int Total of orders in last month
     */
    public static function get_calculate_dealsize() 
    {
      $created_at_min = date( 'Y-m-d', strtotime( '-1 month' ) );

      $ordersCollection = wc_get_orders(
         array(
            'limit' => -1,
            'date_after' => $created_at_min,
            'return' => 'ids',
         )
      );
      
      return strval( count( $ordersCollection ) );
    }

    /**
     * @param $data
     * @return array
     */
    private static function get_user_data()
    {
        $userData = [];
        $user = self::get_current_user();
        $userData['name'] = $user->user_firstname;
        $userData['password'] = self::generate_temp_pass();
        $userData['platform_id'] = self::WOOCOMMERCE;
        $userData['phone'] = '';
        $userData['primary_email'] = self::get_current_email();
        return $userData;
    }

    /**
     * @return string
     */
    private static function get_current_email()
    {
        return get_bloginfo('admin_email');
    }

    /**
     * @param $data
     * @return array
     */
    private static function get_merchant_data($site)
    {
        $merchantData = [];
        $merchantData['platform_id'] = self::WOOCOMMERCE;
        $merchantData['store_domain'] = $site->domain;
        $merchantData['store_name'] = self::get_blog_name($site);
        $merchantData['deal_size_order_count'] = self::get_calculate_dealsize();
        $merchantData['country'] = self::get_store_country($site);
        $merchantData['currency'] = self::get_currency($site);
        $merchantData['source'] = self::WOOCOMMERCE;
        $merchantData['status'] = self::ACTIVE;
        return $merchantData;
    }

    /**
     * Prepare Merchant Data
     * @param $store
     * @return mixed
     */
    public function prepare_compatibility_data()
    {
        $widgetPhpFlag = $this->get_setup_check_widget(self::SETUP_CHECK_WIDGET_PHP);
        $widgetJsFlag = $this->get_setup_check_widget(self::SETUP_CHECK_WIDGET_JS);
        $creationDate = $this->get_setup_check_widget(self::SETUP_CHECK_WIDGET_INSTALLATION_DATE);

        $successInstallation = $widgetPhpFlag && $widgetJsFlag;
        $data = [];
        $data['php_flag'] = $widgetPhpFlag ? $widgetPhpFlag : 0;
        $data['js_flag'] = $widgetJsFlag ? $widgetJsFlag : 0;
        $data['success_installation'] = $successInstallation;
        $data['install_date'] = $creationDate;
        $data['store_domain'] = parse_url(get_site_url(get_current_blog_id()), PHP_URL_HOST);
        $data['platform_id'] = self::WOOCOMMERCE;
        // If get_plugins() isn't available, require it
        if ( ! function_exists( 'get_plugins' ) )
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        $data['modules'] = get_plugins();
        $data['subject'] = $successInstallation ? 'Success Installation' : 'Installation Issues';
        return $data;
    }

    public function can_send_compatibility_report(){
        $apiCalled = $this->get_setup_check_widget(self::SETUP_CHECK_WIDGET_API_CALL);
        $creationDate = $this->get_setup_check_widget(self::SETUP_CHECK_WIDGET_INSTALLATION_DATE);

        return (time() > $creationDate && !$apiCalled);
    }


    /**
     * @return array|
     */
    public static function get_all_sites()
    {
        if(is_multisite()) {
            return get_sites();
        }
        $site = new stdClass();
        $site->domain =  parse_url(get_site_url(), PHP_URL_HOST);
        $site->blog_id =  1;
        return [$site];
    }

    /**
     * @param $site
     * @return mixed
     */
    private static function get_blog_name($site)
    {
        if (is_multisite())
            return get_blog_option($site->blog_id, 'blogname');
        return get_option('blogname');
    }

    /**
     * @param $site
     * @return mixed
     */
    private static function get_currency($site)
    {
        if(is_multisite())
            return get_blog_option($site->blog_id, 'woocommerce_currency');
        return  get_woocommerce_currency();
    }

    /**
     * @param $site
     * @return mixed|string
     */
    private static function get_store_country($site){
        if(is_multisite()) {
            $country = get_blog_option($site->blog_id, 'woocommerce_default_country');
            if (strpos($country, ':') > 0) {
                $countryState = explode(':', $country);
                return isset($countryState[0]) ? $countryState[0] : $country;
            }
            return $country;
        }
        return get_woocommerce_currency();
    }

    private static function generate_temp_pass()
    {
        return "pass" . substr(hash('sha256', rand()), 0, 10);
    }

    private static function set_registration_failed_as($step){
        update_option(self::FAILED_REGISTRATION , $step);
    }

    private static function get_registration_failed_as(){
        return get_option(self::FAILED_REGISTRATION);
    }

    private static function set_secret_key($token, $blog_id = null){
        if (isset($blog_id) && is_multisite()) {
            return update_blog_option($blog_id, self::SECRET_TOKEN_OPTION, $token);
        }
        return update_option(self::SECRET_TOKEN_OPTION , $token);
    }

    private static function set_user_key($token, $blog_id = null){
        if (isset($blog_id) && is_multisite()) {
            return update_blog_option($blog_id, self::USER_TOKEN_OPTION, $token);
        }
        return update_option(self::USER_TOKEN_OPTION , $token);
    }

    private static function set_user_id($userId, $blog_id = null){
        if (isset($blog_id) && is_multisite()) {
            return update_blog_option($blog_id, self::USER_ID_OPTION, $userId);
        }
        return update_option(self::USER_ID_OPTION , $userId);
    }

    private static function set_public_key($token, $blog_id = null){
        if (isset($blog_id) && is_multisite()) {
            return update_blog_option($blog_id, self::PUBLIC_TOKEN_OPTION, $token);
        }
        return update_option(self::PUBLIC_TOKEN_OPTION , $token);
    }

    private static function get_activation_link(){
        return get_option(self::ACTIVATION_LINK);
    }

    private static function set_as_installed(){
        update_option(self::REGISTRATION_STEP, self::MODULE_INSTALLED);
        self::set_setup_check_widget(self::SETUP_CHECK_WIDGET_INSTALLATION_DATE, time());
    }

    private static function set_activation_link($activation_link){
        update_option(self::ACTIVATION_LINK, $activation_link);
    }

    private static function registration_step(){
        return get_option(self::REGISTRATION_STEP);
    }

    public static function is_installed(){
        return self::registration_step() == self::MODULE_INSTALLED;
    }

    public static function has_user_login_succeed(){
        return self::get_registration_failed_as() == self::REGISTRATION_STEP_USER_LOGIN_SUCCESS;
    }

    /**
     * Set setup check widget
     *
     * @param $config
     * @param bool $value
     */
    public static function set_setup_check_widget($config, $value = false)
    {
        if (!get_option($config)) {
            $value = $value ? $value : 1;
            update_option($config, $value);
        }
    }

    /**
     * Get setup check widget config
     *
     * @param $config
     * @return mixed
     */
    public function get_setup_check_widget($config)
    {
        return get_option($config);
    }

    /**
     * @return WP_User
     */
    private static function get_current_user()
    {
        return wp_get_current_user();
    }


    public static function retry_user(){
        self::create_user();
        wp_redirect( self_admin_url( "plugins.php" ) );
        die();
    }

    public static function user_login() {
        self::register_user_login($_POST['username'],$_POST['password']);
        wp_redirect( self_admin_url( "plugins.php" ) );
        die();
    }

    public static function retry_merchant(){
        self::create_merchant();
        wp_redirect( self_admin_url( "plugins.php" ) );
        die();
    }

    public static function get_route_redirect(){
        if(self::has_user_login_succeed()){
            $custom_env = getenv('ROUTEAPP_ENVIRONMENT_ENDPOINT');
            if (is_null($custom_env) || !$custom_env) {
                $custom_env = isset($_SERVER['ROUTEAPP_ENVIRONMENT_ENDPOINT']) ? $_SERVER['ROUTEAPP_ENVIRONMENT_ENDPOINT'] : '';
            }
            if ($custom_env == 'stage') {
                return self::DASHBOARD_STAGE;
            }
            return self::DASHBOARD ;
        }
        return self::get_activation_link();
    }

    private static function update_merchant_status(){
      $routeapp_public = self::get_route_public_instance();
      $merchant = $routeapp_public->routeapp_api_client->get_merchant();
      if (empty($merchant)) {
        return;
      }
      
      if (property_exists($merchant, 'status')) {
        $routeapp_public->routeapp_api_client->update_merchant_status('Active');
      }
    }
}
