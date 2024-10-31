<?php

/**
 * Handle webhook upserts.
 *
 *
 * @package    Routeapp
 * @subpackage Routeapp/admin
 * @author     Route App <support@route.com>
 */
class Routeapp_Webhooks {

    const API_GATEWAY_ENDPOINT = 'https://distributed-api.route.com';
    const API_GATEWAY_ENDPOINT_STAGING = 'https://distributed-api-stage.route.com';

    const webhooksList = [
        // order.updated will trigger both order update and order create events
        'RouteApp Order Upsert' => [
            'name' => 'RouteApp Order Upsert',
            'status' => 'active',
            'topic' => 'order.updated',
            'delivery_url' => '/webhooks/',
            'update' => false,
        ],
        'RouteApp Order Deleted' => [
            'name' => 'RouteApp Order Deleted',
            'status' => 'active',
            'topic' => 'order.deleted',
            'delivery_url' => '/webhooks/',
            'update' => false,
        ],
    ];
    private $_webhooksList;

    public function __construct() {
        $this->buildWebhookList();
    }

    private function buildWebhookList()
    {
        $webhooksList = self::webhooksList;
        foreach ($webhooksList as $webhookName => $values) {
            $webhooksList[$webhookName]['delivery_url'] = $this->generate_delivery_url($values['delivery_url']);
        }
        $this->_webhooksList = $webhooksList;
    }

    private function generate_delivery_url($url) {
        $custom_env = getenv('ROUTEAPP_ENVIRONMENT_ENDPOINT');
        if (is_null($custom_env) || !$custom_env) {
            $custom_env = isset($_SERVER['ROUTEAPP_ENVIRONMENT_ENDPOINT']) ? $_SERVER['ROUTEAPP_ENVIRONMENT_ENDPOINT'] : '';
        }

        $delivery_url = $custom_env == 'stage' ? self::API_GATEWAY_ENDPOINT_STAGING : self::API_GATEWAY_ENDPOINT;
        $delivery_url.= $url . get_option('routeapp_merchant_id');
        return $delivery_url;
    }

    private function generate_default_secret() {
        $merchant_id = get_option('routeapp_merchant_id');
        $secret_token = get_option('routeapp_secret_token');
        $string = $merchant_id . '-' . $secret_token;
        return hash('sha256', $string);
    }

    public function set_secret() {
        $secret = $this->generate_default_secret();
        update_option( '_routeapp_webhooks_secret', $secret);
        return $secret;
    }

    public function get_secret() {
        $secret = get_option('_routeapp_webhooks_secret', false);
        if (!$secret) {
            $secret = $this->set_secret();
        }
        return $secret;
    }

    private function _is_routedata_complete()
    {
        $merchant_id = get_option('routeapp_merchant_id', false);
        $secret_token = get_option('routeapp_secret_token', false);
        return $merchant_id && $secret_token;
    }

    public function upsert_webhooks() {
        //rebuild webhooks list to avoid issues with merchant_id on construct
        $this->buildWebhookList();

        if ( class_exists( 'WC_Data_Store' ) && $this->_is_routedata_complete() ) {
            //check webhook secret on database
            $secret = $this->get_secret();

            $data_store = \WC_Data_Store::load( 'webhook' );
            $webhooksIds   = $data_store->search_webhooks();
            if (is_array($webhooksIds)) {
                $webhooks = array_map( 'wc_get_webhook', $webhooksIds );
                //loop all existing webhooks
                foreach ($webhooks as $webhook) {
                    if (array_key_exists($webhook->get_name(), $this->_webhooksList)) {
                        $this->_webhooksList[$webhook->get_name()]['id'] = $webhook->get_id();
                        //check configuration
                        if (
                            $this->_webhooksList[$webhook->get_name()]['status'] != $webhook->get_status() ||
                            $this->_webhooksList[$webhook->get_name()]['topic'] != $webhook->get_topic() ||
                            $this->_webhooksList[$webhook->get_name()]['delivery_url'] != $webhook->get_delivery_url() ||
                            $secret != $webhook->get_secret()
                        ) {
                            // difference found, mark for update
                            $this->_webhooksList[$webhook->get_name()]['update'] = true;
                        }
                    }
                }
            }
            //loop through the ones we need to add
            foreach ($this->_webhooksList as $name => $values) {
                if (isset($values['id'])) {
                    if ($values['update']) {
                        //remove actual webhook and re-create with the info we have
                        $this->_remove_webhook($values['id']);
                        $this->_create_webhook($values);
                    }
                } else {
                    //create webhook
                    $this->_create_webhook($values);
                }
            }

            update_option( '_routeapp_webhooks_created', 1);
        }
    }

    private function _create_webhook($webhookData) {
        $webhook = new \WC_Webhook();
        $webhook->set_name($webhookData['name']);
        $webhook->set_user_id(get_current_user_id()); // User ID used while generating the webhook payload.
        $webhook->set_topic( $webhookData['topic'] ); // Event used to trigger a webhook.
        $webhook->set_secret( $this->get_secret() ); // Secret to validate webhook when received.
        $webhook->set_delivery_url( $webhookData['delivery_url'] ); // URL where webhook should be sent.
        $webhook->set_status( $webhookData['status'] ); // Webhook status.
        $webhook->save();
    }

    private function _remove_webhook($hookId) {
        $wh = new \WC_Webhook();
        $wh->set_id($hookId);
        $wh->delete();
    }
}
