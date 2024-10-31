<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://route.com/
 * @since      1.0.4
 *
 * @package    Routeapp
 * @subpackage Routeapp/admin
 */

class Routeapp_Notice {

   public function __construct( ) {
      add_action( 'admin_notices', array( $this, 'routeapp_attention_required' ) );
      add_action('network_admin_notices', array( $this, 'routeapp_attention_required' ) );
   }

   /**
	 * Add admin notice messages to Woocommerce
	 * @since  1.0.4
	 * @param  string $message Message that would be output
    * @param  string $notification_type (success, warning, danger, info)
    * @param  void
	 */
   public function add_notice($message, $notification_type) {
      echo sprintf('<div class="notice %s is-dismissible"><p><span class="dashicons-before dashicons-arkcommerce" style="vertical-align:middle;"> </span> %s</p></div>', $this->get_notice_class($notification_type), $message);
   }

   /**
	 * Get notice class name
	 * @since  1.0.4
    * @param  string $notification_type (success, warning, danger, info)
    * @param  string $className 
	 */
   public function get_notice_class($notification_type = 'success') {
      return "notice-{$notification_type}";
   }

    /**
     * Import all admin notices message
     * @since  1.0.4
     * @param  void
     */
    public function routeapp_attention_required()
    {
        $this->billing_attention_required();
        $this->user_attention_required();
        $this->merchant_attention_required();
        $this->redirect_attention_required();
    }

    /**
     * Import admin notice verification to Billing Info
     * @since  1.0.4
     * @param  void
     */
    public function billing_attention_required()
    {
        $billingResponse = Routeapp_API_Client::getInstance()->get_billing();

        if (!is_wp_error($billingResponse)) {
            $body = json_decode($billingResponse['body']);

            if (isset($body) && is_countable($body)) {
               if (count($body) === 0) {
                  $this->add_notice("Route: You haven't yet completed the Billing Info step. Click <a href='http://dashboard.route.com/admin/preferences' target='_blank'>here</a> to complete.", "warning");
               }
            }
        }
    }

    /**
     * Import admin notice if user creation has failed
     * @since  1.0.4
     * @param  void
     */
    public function user_attention_required()
    {
        $routeapp_public = $this->get_route_public_instance();
        if ($routeapp_public->has_user_creation_conflicted()) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/routeapp-admin-user-conflict.php';
        }
        if ($routeapp_public->has_user_creation_failed()) {
            $this->add_notice("We couldn't create your account, please contact our support or  <a href=\"/wp-json/route/recreate_user\"> try again </a></span>.", "warning");
        }
    }

    /**
     * @return WP_User
     */
    private function get_current_user()
    {
        return wp_get_current_user();
    }

    /**
     * @return string
     */
    private function get_current_email() 
    {
       return get_bloginfo('admin_email');
    }

    /**
     * Import admin notice if merchant creation has failed
     * @since  1.0.4
     * @param  void
     */
    public function merchant_attention_required()
    {
        $routeapp_public = $this->get_route_public_instance();
        if ($routeapp_public->has_merchant_creation_failed()) {
            $this->add_notice("Route merchant creation has failed. Please click <a href=\"/wp-json/route/recreate_merchant\"> here </a> to retry</span>.", "warning");
        }
    }

    /**
     * Import admin notice verification to Billing Info
     * @since  1.0.4
     * @param  void
     */
    public function redirect_attention_required()
    {
        $routeapp_public = $this->get_route_public_instance();
        if ($routeapp_public->successful_registration() && !$routeapp_public->was_redirected()) {
            $this->add_notice("You're almost there! We're redirecting you to Route website to finalize your account setup... <span id=\"route_counter\" data-redirect-src=\"". Route_Setup::get_route_redirect() ."\">5</span>", "success");
            $routeapp_public->set_as_redirected();
        }
        if ($routeapp_public->has_merchant_creation_conflicted()) {
            $this->add_notice("Your merchant account already exists. We're redirecting you to Route website to finalize your account setup...<span id=\"route_counter\" data-redirect-src=\"". Route_Setup::get_route_redirect() ."\">5</span>.", "warning");
        }
    }

    private function get_route_public_instance()
    {
        global $routeapp_public;
        return $routeapp_public;
    }

}
