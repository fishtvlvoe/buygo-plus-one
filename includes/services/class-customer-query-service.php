<?php

namespace BuyGoPlus\Services;

/**
 * CustomerQueryService — 客戶查詢服務
 *
 * 負責封裝所有客戶相關的資料庫查詢邏輯，
 * 原本散落在 CustomersAPI 的 $wpdb 直接查詢將陸續遷移至此。
 */
class CustomerQueryService
{
    /**
     * 取得客戶列表（分頁）
     *
     * @param array $params   查詢參數，支援 page、per_page、search
     * @param int   $user_id  當前使用者 ID
     * @param bool  $is_admin 是否為平台管理員（管理員可看所有客戶）
     * @return array{customers: array, total: int}
     */
    public function getListCustomers(array $params, int $user_id, bool $is_admin): array
    {
        return ['customers' => [], 'total' => 0];
    }

    /**
     * 取得單一客戶詳情
     *
     * @param int $customer_id 客戶 ID
     * @return array{id: int, email: string, full_name: string, ...}|null
     *         找不到時回傳 null
     */
    public function getCustomerDetail(int $customer_id): ?array
    {
        return null;
    }
}
