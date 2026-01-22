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
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes()
    {
        register_rest_route('buygo-plus-one/v1', '/debug/log', [
            'methods' => 'POST',
            'callback' => [$this, 'log'],
            'permission_callback' => function() {
                // 僅允許管理員存取 Debug API
                return current_user_can('manage_options') || current_user_can('buygo_admin');
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
