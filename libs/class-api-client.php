<?php

/*
 * https://docs.adsterratools.com/public/v3/publishers-api/
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Adsterra_Dashboard_API_Client {

    const BASE_HOST = 'https://api3.adsterratools.com/publisher';

    private $token;

    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * Returns the "items" of the response, or a WP_Error carrying the message sent by the API.
     */
    private function doGet($endpoint, array $query = []) {
        $url = self::BASE_HOST . $endpoint;

        if ($query) {
            // The API expects group_by[]=date, not the group_by[0]=date built by http_build_query()
            $url .= '?' . preg_replace('/%5B\d+%5D=/', '%5B%5D=', http_build_query($query));
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $this->token,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && isset($body['items']) && is_array($body['items'])) {
            return $body['items'];
        }

        if (!empty($body['message']) && is_string($body['message'])) {
            /* translators: 1: HTTP status code, 2: error message returned by Adsterra */
            $message = sprintf(__('Adsterra API error %1$d: %2$s', 'wp-adsterra-dashboard'), $code, $body['message']);
        } else {
            /* translators: %d: HTTP status code */
            $message = sprintf(__('Adsterra API error %d.', 'wp-adsterra-dashboard'), $code);
        }

        return new WP_Error('adsterra_api_error', $message, ['status' => $code]);
    }

    public function getDomains() {
        return $this->doGet('/domains.json');
    }

    public function getPlacements() {
        return $this->doGet('/placements.json');
    }

    /**
     * Daily stats per placement in one call, optionally limited to one domain.
     */
    public function getStats($domain_id, $start_date, $finish_date) {
        $query = [
            'start_date' => $start_date,
            'finish_date' => $finish_date,
            'group_by' => ['date', 'placement'],
        ];

        if ($domain_id !== 'all') {
            $query['domain'] = (int) $domain_id;
        }

        return $this->doGet('/stats.json', $query);
    }
}
