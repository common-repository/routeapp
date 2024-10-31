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

class Routeapp_API_Compatibility extends Routeapp_API_Client
{

    const API_ENDPOINT = 'partner-integration/plugin-status';
    const HTTP_SUCCESS_CODE = 200;

    /**
     * Send compatibility report
     *
     * @param $data
     * @return mixed
     */
    public function send($data)
    {
        try {

            $response = $this->_make_private_api_call(self::API_ENDPOINT, $data, 'POST');

            if (is_wp_error($response)) {
                throw new Exception("Compatibility endpoint has failed: " . $response->get_error_message());
            }

            if (wp_remote_retrieve_response_code($response) != self::HTTP_SUCCESS_CODE) {
                throw new Exception("Compatibility endpoint returned an expected error code: " . wp_remote_retrieve_response_code($response));
            }

            Route_Setup::set_setup_check_widget(Route_Setup::SETUP_CHECK_WIDGET_API_CALL);

        } catch (Exception $exception) {
            $routeapp_public = self::get_route_public_instance();
            $routeapp_public->routeapp_log($exception, $this->_extraData);
        }

    }

    protected function isStorable()
    {
        return false;
    }

    protected function enqueueOperation()
    {
        return false;
    }
}
