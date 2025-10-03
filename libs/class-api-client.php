<?php

/*
 * https://docs.adsterratools.com/public/v3/publishers-api/
 */

class WPAdsterraDashboardAPIClient {

    const BASE_HOST = 'https://api3.adsterratools.com/publisher';
    const CACHE_EXPIRY = 300; // 5 minutes

    private $domain = '';
    private $token = '';

    public function __construct($token, $domain = null) {
        $this->token = sanitize_text_field($token);
        $this->domain = $domain;
    }

    private function getCacheKey($endpoint, $payload = []) {
        return 'adsterra_api_' . md5($endpoint . serialize($payload) . $this->token);
    }

    private function doGet($endpoint, array $payload = [], $use_cache = true) {
        // Check cache first if enabled
        if ($use_cache) {
            $cache_key = $this->getCacheKey($endpoint, $payload);
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }

        $args = array(
            'body' => $payload,
            'timeout' => 15,
            'redirection' => 3,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->token
            ]
        );

        $ret = wp_remote_get(self::BASE_HOST . $endpoint, $args);

        if (is_wp_error($ret)) {
            error_log('Adsterra API Error: ' . $ret->get_error_message());
            return [
                'code' => 500,
                'message' => 'Connection error: ' . $ret->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($ret);
        if ($response_code !== 200) {
            error_log('Adsterra API HTTP Error: ' . $response_code);
            return [
                'code' => $response_code,
                'message' => 'HTTP Error: ' . $response_code
            ];
        }

        $body = wp_remote_retrieve_body($ret);
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Adsterra API JSON Error: ' . json_last_error_msg());
            return [
                'code' => 500,
                'message' => 'Invalid JSON response'
            ];
        }

        // Cache successful responses
        if ($use_cache && !isset($decoded['code'])) {
            set_transient($this->getCacheKey($endpoint, $payload), $decoded, self::CACHE_EXPIRY);
        }

        // Debug logging for development (remove in production)
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log('Adsterra API Response for ' . $endpoint . ': ' . wp_json_encode($decoded));
        }

        return $decoded;
    }

    public function getDomains() {
        $retDomains = $this->doGet('/domains.json', []);

        $ret = [];
        if (empty($retDomains['items'])) {
            return $retDomains;
        }

        foreach ($retDomains['items'] as $domain) {
            $ret[] = [
                "id" => $domain['id'],
                "title" => $domain['title'],
            ];
        }

        return $ret;
    }

    public function getPlacementsByDomainID($domain_id) {

        $ret = [];

        if (empty($domain_id)) {
            return $ret;
        }

        $retPlacements = $this->doGet('/domain/' . $domain_id . '/placements.json', []);

        if (empty($retPlacements['items'])) {
            return $retPlacements;
        }

        foreach ($retPlacements['items'] as $placement) {

            $title = $placement['title'];

            if ($title != $placement['alias']) {
                $title = $placement['alias'];
            }
            $ret[] = [
                "id" => $placement['id'],
                "title" => $title
            ];
        }

        return $ret;
    }

    public function getStatsByPlacementID($domain_id, $placement_id, $parameters = []) {

        $queryString = http_build_query([
            'domain' => $domain_id,
            'placement' => $placement_id,
            'start_date' => $parameters['start_date'],
            'finish_date' => $parameters['finish_date']
        ]);

        return $this->doGet('/stats.json?' . $queryString);
    }

    public function getStatsByDomainID($domain_id, $parameters = []) {
        // Get all stats for domain grouped by placement
        $queryString = http_build_query([
            'domain' => $domain_id,
            'start_date' => $parameters['start_date'],
            'finish_date' => $parameters['finish_date'],
            'group_by' => 'placement' // Group by placement to get stats per placement
        ]);

        return $this->doGet('/stats.json?' . $queryString);
    }
}
