<?php
namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 私有建構函數（Singleton）
    }
    
    public function init() {
        // 初始化外掛
        $this->load_dependencies();
        $this->register_hooks();
    }
    
    private function load_dependencies() {
        // 載入其他類別
        // require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-routes.php';
    }
    
    private function register_hooks() {
        // 註冊 WordPress hooks
    }
}
