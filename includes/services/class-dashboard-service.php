<?php

namespace BuyGoPlus\Services;

/**
 * Dashboard Service - 儀表板統計服務
 *
 * 封裝儀表板統計查詢邏輯，為 API 層提供乾淨的資料介面
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class DashboardService
{
    private $wpdb;
    private $debugService;
    private $table_orders;
    private $table_customers;
    private $table_order_items;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->debugService = DebugService::get_instance();
        $this->table_orders = $wpdb->prefix . 'fct_orders';
        $this->table_customers = $wpdb->prefix . 'fct_customers';
        $this->table_order_items = $wpdb->prefix . 'fct_order_items';
    }
}
