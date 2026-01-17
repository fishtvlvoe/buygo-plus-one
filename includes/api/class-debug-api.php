<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\DebugService;

if (!defined('ABSPATH')) {
    exit;
}

class Debug_API
{
    private $debugService;
    
    public function __construct()
    {
        $this->debugService = new DebugService();
        $this->register_routes();
    }
    
    public function register_routes()
    {
        register_rest_route('buygo-plus-one/v1', '/debug/log', [
            'methods' => 'POST',
            'callback' => [$this, 'log'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }
    
    public function log($request)
    {
        $params = $request->get_json_params();
        
        $module = $params['module'] ?? 'Unknown';
        $message = $params['message'] ?? '';
        $level = $params['level'] ?? 'info';
        $data = $params['data'] ?? [];
        
        $this->debugService->log($module, $message, $data, $level);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Log recorded'
        ], 200);
    }
}
