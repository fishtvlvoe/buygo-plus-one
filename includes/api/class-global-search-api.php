<?php
namespace BuyGoPlus\Api;

if (!defined('ABSPATH')) {
    exit;
}

class GlobalSearch_API {
    public function register_routes() {
        register_rest_route('buygo-plus-one/v1', '/global-search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => 'BuyGoPlus\Api\API::check_permission',
            'args' => [
                'query' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return strlen($param) >= 1 && strlen($param) <= 100;
                    }
                ],
                'type' => [
                    'default' => 'all',
                    'enum' => ['all', 'product', 'order', 'customer', 'shipment'],
                ],
                'status' => [
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_from' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_to' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return $param >= 1 && $param <= 100;
                    }
                ],
            ],
        ]);
    }

    public function search($request) {
        $query = $request->get_param('query');
        $type = $request->get_param('type');
        $status = $request->get_param('status');
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');

        if (empty($query)) {
            return new \WP_Error('missing_query', 'Search query is required', ['status' => 400]);
        }

        try {
            // 委派給 SearchService
            $searchService = new \BuyGoPlus\Services\SearchService();
            $results = $searchService->search([
                'query' => $query,
                'type' => $type,
                'status' => $status,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'page' => $page,
                'per_page' => $per_page
            ]);

            return [
                'success' => true,
                'data' => $results['items'],
                'total' => $results['total'],
                'query' => $query,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($results['total'] / $per_page),
            ];

        } catch (\Exception $e) {
            return new \WP_Error('search_error', $e->getMessage(), ['status' => 500]);
        }
    }

}