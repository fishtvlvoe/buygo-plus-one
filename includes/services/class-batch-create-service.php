<?php
/**
 * BatchCreateService - 批量商品建立服務
 *
 * 封裝批量商品建立的核心商業邏輯：
 * - 驗證輸入（空陣列、超過上限）
 * - 配額檢查（賣家 + 小幫手共享配額）
 * - 逐筆驗證必填欄位
 * - 逐筆呼叫 FluentCartService 建立商品
 * - 收集並回傳結果
 *
 * @package BuyGoPlus\Services
 * @since 3.1.0
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

class BatchCreateService
{
    /**
     * 單次批量上限
     */
    const MAX_BATCH_SIZE = 50;

    /**
     * FluentCart 商品建立服務
     *
     * @var FluentCartService
     */
    private $fluentCartService;

    /**
     * 商品配額檢查器
     *
     * @var ProductLimitChecker
     */
    private $productLimitChecker;

    /**
     * Constructor
     *
     * 支援依賴注入，方便測試時傳入 mock 物件。
     *
     * @param FluentCartService|null $fluentCartService
     * @param ProductLimitChecker|null $productLimitChecker
     */
    public function __construct($fluentCartService = null, $productLimitChecker = null)
    {
        $this->fluentCartService = $fluentCartService;
        $this->productLimitChecker = $productLimitChecker;
    }

    /**
     * 批量建立商品
     *
     * @param array $items 商品資料陣列，每筆含 title(必填)、price(必填)、quantity(選填)、description(選填)、currency(選填)
     * @param int $user_id 賣家或小幫手的 WordPress User ID
     * @return array ['success' => bool, 'total' => int, 'created' => int, 'failed' => int, 'results' => [...]]
     */
    public function batchCreate(array $items, int $user_id): array
    {
        // 1. 驗證 $items 非空
        if (empty($items)) {
            return [
                'success' => false,
                'error' => '商品資料不可為空',
            ];
        }

        // 2. 驗證不超過單次上限
        if (count($items) > self::MAX_BATCH_SIZE) {
            return [
                'success' => false,
                'error' => sprintf('單次最多上架 %d 個商品', self::MAX_BATCH_SIZE),
            ];
        }

        // 3. 檢查配額
        $quotaCheck = $this->checkQuota($user_id, count($items));
        if ($quotaCheck !== true) {
            return $quotaCheck;
        }

        // 4. 逐筆處理
        $results = [];
        $created = 0;
        $failed = 0;

        foreach ($items as $index => $item) {
            // 4a. 驗證必填欄位
            $validationError = $this->validateItem($item);
            if ($validationError !== null) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'product_id' => null,
                    'error' => $validationError,
                ];
                $failed++;
                continue;
            }

            // 4b. 呼叫 FluentCartService 建立商品
            $productData = $this->prepareProductData($item, $user_id);
            $productId = $this->getFluentCartService()->create_product($productData);

            if (is_wp_error($productId)) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'product_id' => null,
                    'error' => $productId->get_error_message(),
                ];
                $failed++;
            } else {
                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'product_id' => (int) $productId,
                    'error' => null,
                ];
                $created++;
            }
        }

        // 5. 彙整結果
        $total = count($items);
        $success = $created > 0;

        return [
            'success' => $success,
            'total' => $total,
            'created' => $created,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * 檢查賣家配額
     *
     * @param int $user_id 使用者 ID
     * @param int $count 要建立的商品數量
     * @return true|array true 表示通過，否則回傳錯誤陣列
     */
    private function checkQuota(int $user_id, int $count)
    {
        $checker = $this->getProductLimitChecker();
        $result = $checker->canAddProduct($user_id);

        // 如果 can_add 為 false，直接拒絕
        if (!$result['can_add']) {
            return [
                'success' => false,
                'error' => sprintf(
                    '商品配額不足：目前已有 %d 個商品，上限 %d 個',
                    $result['current'],
                    $result['limit']
                ),
            ];
        }

        // 如果有限制（limit > 0），檢查剩餘配額是否足夠
        $limit = $result['limit'];
        if ($limit > 0) {
            $current = $result['current'];
            $remaining = $limit - $current;

            if ($count > $remaining) {
                return [
                    'success' => false,
                    'error' => sprintf(
                        '商品配額不足：剩餘 %d 個配額，但嘗試建立 %d 個商品',
                        $remaining,
                        $count
                    ),
                ];
            }
        }

        return true;
    }

    /**
     * 驗證單筆商品資料
     *
     * @param array $item 商品資料
     * @return string|null 驗證錯誤訊息，null 表示通過
     */
    private function validateItem(array $item): ?string
    {
        // title 必填且不可為空
        $title = trim($item['title'] ?? '');
        if (empty($title)) {
            return '商品名稱為必填';
        }

        // price 必須 >= 0
        $price = $item['price'] ?? null;
        if ($price === null) {
            return '商品價格為必填';
        }
        if (!is_numeric($price) || (float) $price < 0) {
            return '價格不可為負數';
        }

        return null;
    }

    /**
     * 準備 FluentCartService 所需的商品資料
     *
     * @param array $item 前端傳入的商品資料
     * @param int $user_id 賣家 ID
     * @return array FluentCartService::create_product() 的參數格式
     */
    private function prepareProductData(array $item, int $user_id): array
    {
        return [
            'name' => trim($item['title']),
            'price' => (int) $item['price'],
            'quantity' => (int) ($item['quantity'] ?? 0),
            'description' => $item['description'] ?? '',
            'currency' => $item['currency'] ?? 'TWD',
            'user_id' => $user_id,
        ];
    }

    /**
     * 取得 FluentCartService 實例
     *
     * @return FluentCartService
     */
    private function getFluentCartService(): FluentCartService
    {
        if ($this->fluentCartService === null) {
            $this->fluentCartService = new FluentCartService();
        }
        return $this->fluentCartService;
    }

    /**
     * 取得 ProductLimitChecker 實例
     *
     * @return ProductLimitChecker
     */
    private function getProductLimitChecker(): ProductLimitChecker
    {
        if ($this->productLimitChecker === null) {
            $this->productLimitChecker = new ProductLimitChecker(
                DebugService::get_instance()
            );
        }
        return $this->productLimitChecker;
    }
}
