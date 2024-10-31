<?php

class Routeapp_Logger_Sentry
{
    /**
     * Sentry DSN key
     */
    const SENTRY_KEY= 'b1ddeaac648345389240bf5f2f1e2a19';

    /**
     * Sentry project id
     */
    const SENTRY_PROJECT_ID = '2048214';


    /**
     * Sentry log context lines
     */
    const SENTRY_LOG_CONTEXT_LINES = 10;

    /**
     * The Sentry API URL
     * @var string
     */
    private $_api_url;

    /**
     * Data used for API call
     * @var array
     */
    private $_extraData = [];

    private static $instances = [];
    /**
     * Default contructor
     */
    public function __construct()
    {
        $this->_api_url = 'https://sentry.io/api/' . self::SENTRY_PROJECT_ID . '/store/';
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

    /*
     * Make the call to the API
     * @param  string $endpoint
     * @param  array  $params
     * @param  string $method
     * @return mixed|json string
     */
    private function _make_api_call($params = array())
    {
        $url = $this->_api_url;
        $args = array(
            'timeout' => 5,
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Sentry-Auth' => $this->_get_sentry_auth(),
            ),
            'body' => json_encode($params),
        );
        return wp_remote_request($url, $args);
    }

    private function _get_sentry_auth() {
        return 'Sentry sentry_version=7, sentry_key=' . self::SENTRY_KEY . ', sentry_client=raven-bash/0.1';
    }

    public function sentry_log($event, $extraData = false) {
        $this->_extraData = $extraData;
        $params = $this->_prepare_event($event);
        $this->_make_api_call($params);
    }

    /**
     * Prepare event in array
     *
     * @return array
     */
    private function _prepare_event($event) {
        $eventArray = array();
        $eventArray['platform'] = 'php';
        if (is_object($event)) {
            $eventArray['message'] = (string)$event->getMessage();
            $reportLevel = strtolower(get_class($event));
            $traces = $event->getTrace();
        } else {
            $eventArray['message'] = $event;
            $reportLevel = 'debug';
            $traces = debug_backtrace();
        }
        $eventArray['type'] = $eventArray['message'];
        $eventArray['tags'] = [
            ['module', 'Route Module'],
            ['module.version', ROUTEAPP_VERSION],
            ['url', wc_get_page_permalink( 'shop' )],
            ['php.version', phpversion()],
            ['report.level', $reportLevel],
            ['http.server', $_SERVER['SERVER_SOFTWARE']],
            ['woocommerce.version', $this->_get_woo_version_number()]
        ];

        $eventArray['exception'] = [
            'values' => [
                [
                    'type' => ucfirst($eventArray['type']),
                    'module' => 'Route WooCommerce Integration',
                    'value' => $eventArray['message'],
                    'stacktrace' => [
                        'frames' => $this->_prepare_stack_trace($traces)
                    ]
                ]
            ]
        ];

        $eventArray['extra']['url'] = wc_get_page_permalink( 'shop' );

        if (!empty($this->_extraData)) {
            $eventArray['extra']['extraData'] = $this->_extraData;
        }

        return $eventArray;
    }

    /**
     * Get WooCommerce version number
     * @return mixed|null
     */
    private function _get_woo_version_number() {
        // If get_plugins() isn't available, require it
        if ( ! function_exists( 'get_plugins' ) )
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        // Create the plugins folder and file variables
        $plugin_folder = get_plugins( '/' . 'woocommerce' );
        $plugin_file = 'woocommerce.php';

        // If the plugin version number is set, return it
        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];
        } else {
            // Otherwise return null
            return NULL;
        }
    }

    /**
     * Receive error or exception traces, return formatted array for Sentry
     *
     * @return array
     */
    private function _prepare_stack_trace($traces) {
        $tracesArray = array();
        foreach (array_reverse($traces) as $trace) {
            if ($this->_is_valid_trace($trace)) {
                $traceArray = array();
                $traceArray['filename'] = $trace['file'];
                $traceArray['abs_path'] = $trace['file'];
                $traceArray['lineno'] = (int)$trace['line'];
                $traceArray['function'] = $trace['function'];
                $traceArray['context_line'] = $trace['function'];
                $traceArray['pre_context'][0] = "";
                $traceArray['post_context'][0] = "";

                $sourceCodeExcerpt = $this->_get_source_code_excerpt($traceArray['filename'],
                    $traceArray['lineno'],
                    self::SENTRY_LOG_CONTEXT_LINES);

                if (isset($sourceCodeExcerpt['context_line'])) {
                    $traceArray['context_line'] = $sourceCodeExcerpt['context_line'];
                }

                if (isset($sourceCodeExcerpt['pre_context'])) {
                    $traceArray['pre_context'] = $sourceCodeExcerpt['pre_context'];
                }
                if (isset($sourceCodeExcerpt['post_context'])) {
                    $traceArray['post_context'] = $sourceCodeExcerpt['post_context'];
                }
                $traceArray['in_app'] = false;
                $tracesArray[] = $traceArray;
            }
        }
        return $tracesArray;
    }

    /**
     * Check if is a valid trace
     * @param $trace
     * @return bool
     */
    private function _is_valid_trace($trace) {
        if (isset($trace['file']) && isset($trace['line']) && isset($trace['function'])) {
            if (!empty($trace['file']) && !empty($trace['line']) && !empty($trace['function'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Gets an excerpt of the source code around a given line.
     *
     * @param $path            The file path
     * @param $lineNumber      The line to centre about
     * @param $maxLinesToFetch The maximum number of lines to fetch
     */
    private function _get_source_code_excerpt($path,$lineNumber, $maxLinesToFetch)
    {
        if (@!is_readable($path) || !is_file($path)) {
            return [];
        }

        $frame = [
            'pre_context' => [],
            'context_line' => '',
            'post_context' => [],
        ];

        $target = max(0, ($lineNumber - ($maxLinesToFetch + 1)));
        $currentLineNumber = $target + 1;

        try {
            $file = new \SplFileObject($path);
            $file->seek($target);

            while (!$file->eof()) {
                /** @var string $line */
                $line = $file->current();
                $line = rtrim($line, "\r\n");

                if ($currentLineNumber == $lineNumber) {
                    $frame['context_line'] = $line;
                } elseif ($currentLineNumber < $lineNumber) {
                    $frame['pre_context'][] = $line;
                } elseif ($currentLineNumber > $lineNumber) {
                    $frame['post_context'][] = $line;
                }

                ++$currentLineNumber;

                if ($currentLineNumber > $lineNumber + $maxLinesToFetch) {
                    break;
                }

                $file->next();
            }
        } catch (\Exception $exception) {
            // Do nothing, if any error occurs while trying to get the excerpts
            // it's not a drama
        }

        return $frame;
    }

}
