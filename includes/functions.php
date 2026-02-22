<?php
/**
 * BuyGo Plus One - 全域輔助函式
 *
 * 提供跨模組使用的全域函式。
 * 注意：此檔案不使用 namespace，由 buygo-plus-one.php 直接 require（不走 autoloader）。
 *
 * @package BuyGoPlus
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('buygo_is_pro')) {
    /**
     * 檢查是否為 Pro 版
     *
     * 目前永遠回傳 true（授權伺服器未來實作）。
     * 委派至 FeatureManagementService::is_pro()。
     *
     * @return bool
     */
    function buygo_is_pro(): bool
    {
        return \BuyGoPlus\Services\FeatureManagementService::is_pro();
    }
}
