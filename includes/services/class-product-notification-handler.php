<?php
/**
 * Product Notification Handler
 *
 * 處理商品上架通知
 *
 * @package BuyGoPlus
 * @since 1.2.0
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ProductNotificationHandler
 *
 * 監聽商品建立事件並發送通知給「非上架者」
 *
 * 邏輯：
 * - 上架者已透過 replyToken 收到確認訊息
 * - 此 Handler 負責通知「另一方」（賣家或小幫手）
 * - 如果賣家上架 → 通知小幫手
 * - 如果小幫手上架 → 通知賣家
 */
class ProductNotificationHandler
{
    /**
     * Debug Service 實例
     *
     * @var DebugService
     */
    private $debug_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->debug_service = DebugService::get_instance();

        // 監聽商品建立事件
        add_action('buygo/product/created', [$this, 'onProductCreated'], 10, 3);
    }

    /**
     * 處理商品建立事件
     *
     * @param int    $product_id   商品 ID
     * @param array  $product_data 商品資料
     * @param string $line_uid     LINE UID（透過 LINE 建立時才有）
     */
    public function onProductCreated($product_id, $product_data, $line_uid)
    {
        // Debug: 確認 action 被觸發
        $webhookLogger = WebhookLogger::get_instance();
        $webhookLogger->log('product_notification_handler_start', [
            'product_id' => $product_id,
            'line_uid' => $line_uid,
        ], $product_data['user_id'] ?? null, $line_uid);

        try {
            // 只處理透過 LINE 建立的商品（有 line_uid）
            if (empty($line_uid)) {
            $this->debug_service->log('ProductNotificationHandler', '跳過通知：非 LINE 建立的商品', [
                'product_id' => $product_id,
            ]);
            return;
        }

        // 取得賣家 ID（商品擁有者）
        $seller_id = $product_data['user_id'] ?? null;
        if (!$seller_id) {
            $this->debug_service->log('ProductNotificationHandler', '跳過通知：無賣家 ID', [
                'product_id' => $product_id,
            ], 'warning');
            return;
        }

        // 取得上架者的 user_id（透過 LINE 上架的人）
        $uploader_id = IdentityService::getUserIdByLineUid($line_uid);
        if (!$uploader_id) {
            $this->debug_service->log('ProductNotificationHandler', '跳過通知：無法識別上架者', [
                'product_id' => $product_id,
                'line_uid' => $line_uid,
            ], 'warning');
            return;
        }

        // 收集需要通知的用戶（排除上架者本人）
        $notify_user_ids = [];

        // 取得小幫手列表
        $helpers = SettingsService::get_helpers($seller_id);
        $helper_ids = array_map(function($helper) {
            return (int) $helper['id'];
        }, $helpers);

        // 判斷上架者身份並決定通知對象
        if ($uploader_id == $seller_id) {
            // 賣家本人上架 → 通知所有小幫手
            $notify_user_ids = $helper_ids;
            $this->debug_service->log('ProductNotificationHandler', '賣家上架，通知小幫手', [
                'product_id' => $product_id,
                'seller_id' => $seller_id,
                'helper_count' => count($helper_ids),
            ]);
        } else {
            // 小幫手上架 → 通知賣家 + 其他小幫手（排除上架者）
            $notify_user_ids = [$seller_id];
            foreach ($helper_ids as $helper_id) {
                if ($helper_id != $uploader_id) {
                    $notify_user_ids[] = $helper_id;
                }
            }
            $this->debug_service->log('ProductNotificationHandler', '小幫手上架，通知賣家和其他小幫手', [
                'product_id' => $product_id,
                'uploader_id' => $uploader_id,
                'seller_id' => $seller_id,
                'notify_count' => count($notify_user_ids),
            ]);
        }

        // 如果沒有需要通知的人，跳過
        if (empty($notify_user_ids)) {
            $this->debug_service->log('ProductNotificationHandler', '跳過通知：無需通知的用戶', [
                'product_id' => $product_id,
            ]);
            return;
        }

        // 準備模板變數
        $template_args = $this->buildTemplateArgs($product_id, $product_data);

        // 從模板系統取得訊息（使用 helper_product_created 模板）
        $template = NotificationTemplates::get('helper_product_created', $template_args);
        $message = $template['line']['text'] ?? $template['line']['message'] ?? '';

        if (empty($message)) {
            $this->debug_service->log('ProductNotificationHandler', '跳過通知：模板訊息為空', [
                'product_id' => $product_id,
            ], 'warning');
            return;
        }

        $this->debug_service->log('ProductNotificationHandler', '發送商品上架通知', [
            'product_id' => $product_id,
            'notify_user_ids' => $notify_user_ids,
            'message_length' => strlen($message),
        ]);

        // 發送通知給所有需要通知的用戶
        $result = $this->sendToUsers($notify_user_ids, $message);

        $this->debug_service->log('ProductNotificationHandler', '通知發送結果', [
            'product_id' => $product_id,
            'success' => $result['success'] ?? 0,
            'failed' => $result['failed'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
        ]);

        } catch (\Exception $e) {
            // 捕獲異常，記錄錯誤但不中斷流程
            $webhookLogger->log('product_notification_handler_error', [
                'product_id' => $product_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $product_data['user_id'] ?? null, $line_uid);
        } catch (\Error $e) {
            // 捕獲 PHP Error（如 TypeError）
            $webhookLogger->log('product_notification_handler_fatal', [
                'product_id' => $product_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $product_data['user_id'] ?? null, $line_uid);
        }
    }

    /**
     * 建立模板變數
     *
     * 將商品資料轉換為模板所需的變數格式
     *
     * @param int   $product_id   商品 ID
     * @param array $product_data 商品資料
     * @return array 模板變數
     */
    private function buildTemplateArgs($product_id, $product_data)
    {
        $product_name = $product_data['name'] ?? '';
        $product_url = home_url("/item/{$product_id}");

        // 幣別符號（支援多種貨幣）
        $currency = $product_data['currency'] ?? 'TWD';
        $currency_map = [
            'JPY' => 'JPY',
            '日幣' => 'JPY',
            'TWD' => 'NT$',
            '台幣' => 'NT$',
            'USD' => 'US$',
            '美金' => 'US$',
            'CNY' => '¥',
            '人民幣' => '¥',
            'EUR' => '€',
            '歐元' => '€',
            'KRW' => '₩',
            '韓幣' => '₩',
        ];
        $currency_symbol = $currency_map[$currency] ?? $currency;

        // 處理價格顯示
        $price_display = '';
        $quantity_display = '';

        if (!empty($product_data['variations']) && is_array($product_data['variations'])) {
            // 多樣式商品
            $prices = [];
            $quantities = [];
            foreach ($product_data['variations'] as $variation) {
                $prices[] = number_format($variation['price'] ?? $product_data['price'] ?? 0);
                $quantities[] = $variation['quantity'] ?? 0;
            }
            $price_display = implode('/', $prices);
            $quantity_display = implode('/', $quantities);
        } else {
            // 單一商品
            $price_display = number_format($product_data['price'] ?? 0);
            $quantity_display = $product_data['quantity'] ?? 0;
        }

        // 原價區塊（含前置換行，只有內容時才輸出）
        $original_price_section = '';
        if (!empty($product_data['original_price']) || !empty($product_data['compare_price'])) {
            if (!empty($product_data['variations']) && is_array($product_data['variations'])) {
                $original_prices = [];
                foreach ($product_data['variations'] as $variation) {
                    if (!empty($variation['compare_price'])) {
                        $original_prices[] = number_format($variation['compare_price']);
                    }
                }
                if (!empty($original_prices)) {
                    $original_price_section = "\n原價：{$currency_symbol} " . implode('/', $original_prices);
                }
            } else {
                $original_price = $product_data['original_price'] ?? $product_data['compare_price'] ?? 0;
                if ($original_price > 0) {
                    $original_price_section = "\n原價：{$currency_symbol} " . number_format($original_price);
                }
            }
        }

        // 分類區塊（含前置換行，只有內容時才輸出）
        $category_section = '';
        if (!empty($product_data['variations']) && is_array($product_data['variations'])) {
            $categories = [];
            foreach ($product_data['variations'] as $variation) {
                if (!empty($variation['name'])) {
                    $categories[] = $variation['name'];
                }
            }
            if (!empty($categories)) {
                $category_section = "\n分類：" . implode('/', $categories);
            }
        } elseif (!empty($product_data['category'])) {
            $category_section = "\n分類：{$product_data['category']}";
        }

        // 到貨日期區塊（含前置換行，只有內容時才輸出）
        $arrival_date_section = '';
        if (!empty($product_data['arrival_date'])) {
            $arrival_date = $product_data['arrival_date'];
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $arrival_date, $matches)) {
                $arrival_date = "{$matches[1]}/{$matches[2]}/{$matches[3]}";
            }
            $arrival_date_section = "\n到貨日期：{$arrival_date}";
        }

        // 預購日期區塊（含前置換行，只有內容時才輸出）
        $preorder_date_section = '';
        if (!empty($product_data['preorder_date'])) {
            $preorder_date = $product_data['preorder_date'];
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $preorder_date, $matches)) {
                $preorder_date = "{$matches[1]}/{$matches[2]}/{$matches[3]}";
            }
            $preorder_date_section = "\n預購日期：{$preorder_date}";
        }

        return [
            'product_name' => $product_name,
            'price' => $price_display,
            'quantity' => $quantity_display,
            'product_url' => $product_url,
            'currency_symbol' => $currency_symbol,
            'original_price_section' => $original_price_section,
            'category_section' => $category_section,
            'arrival_date_section' => $arrival_date_section,
            'preorder_date_section' => $preorder_date_section,
        ];
    }

    /**
     * 發送訊息給多個用戶
     *
     * @param array  $user_ids 用戶 ID 陣列
     * @param string $message  訊息內容
     * @return array ['success' => int, 'failed' => int, 'skipped' => int]
     */
    private function sendToUsers($user_ids, $message)
    {
        $result = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // 檢查 LINE Messaging API 是否可用
        if (!class_exists('\BuygoLineNotify\Services\MessagingService')) {
            $this->debug_service->log('ProductNotificationHandler', 'LINE Messaging API 不可用', [], 'warning');
            $result['skipped'] = count($user_ids);
            return $result;
        }

        foreach ($user_ids as $user_id) {
            // 使用 MessagingService::pushText() 發送訊息
            // 該方法會自動檢查 LINE 綁定狀態並取得 LINE UID
            $send_result = \BuygoLineNotify\Services\MessagingService::pushText((int) $user_id, $message);

            if (is_wp_error($send_result)) {
                $error_code = $send_result->get_error_code();
                if ($error_code === 'user_not_linked' || $error_code === 'line_uid_not_found') {
                    $this->debug_service->log('ProductNotificationHandler', '用戶未綁定 LINE，跳過', [
                        'user_id' => $user_id,
                        'error' => $send_result->get_error_message(),
                    ]);
                    $result['skipped']++;
                } else {
                    $this->debug_service->log('ProductNotificationHandler', '發送失敗', [
                        'user_id' => $user_id,
                        'error' => $send_result->get_error_message(),
                    ], 'error');
                    $result['failed']++;
                }
            } else {
                $result['success']++;
            }
        }

        return $result;
    }
}
