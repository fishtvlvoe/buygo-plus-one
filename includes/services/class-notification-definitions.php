<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NotificationDefinitions - 通知模板定義與格式化工具
 *
 * 負責：
 * - 所有預設模板定義 (definitions)
 * - 狀態文字翻譯 (payment / procurement / order)
 * - 格式化工具方法 (product_list / estimated_delivery / shipping_method)
 *
 * 從 NotificationTemplates 拆分而來，保持向後相容。
 */
class NotificationDefinitions {

    /**
     * 所有預設通知模板定義
     *
     * @return array<string, array>
     */
    public static function definitions() {
        return [
            // 客戶（買家）通知
            'order_created' => [
                'line' => [
                    'message' => "✅ 訂單已建立\n\n訂單編號：#{order_id}\n訂單金額：{currency_symbol} {total}\n\n感謝您的訂購！\n我們會盡快為您處理。"
                ]
            ],

            'order_cancelled' => [
                'line' => [
                    'message' => "❌ 您的訂單有異動/取消。\n\n訂單編號：{order_id}\n說明：{note}"
                ]
            ],
            'plusone_order_confirmation' => [
                'line' => [
                    'message' => "已收到您的訂單！\n商品：{product_name}\n數量：{quantity}\n金額：{currency_symbol} {total}"
                ]
            ],

            // 賣家通知
            'seller_order_created' => [
                'line' => [
                    'message' => "🛒 您有新的訂單！\n\n訂單編號：{order_id}\n買家：{buyer_name}\n金額：{currency_symbol} {order_total}\n\n請盡快處理訂單。\n{order_url}"
                ]
            ],
            'seller_order_cancelled' => [
                'line' => [
                    'message' => "❌ 訂單已取消\n\n訂單編號：{order_id}\n買家：{buyer_name}\n取消原因：{note}"
                ]
            ],

            // 訂單出貨通知（Phase 31: 發送給買家）
            'order_shipped' => [
                'line' => [
                    'message' => "📦 您的訂單已出貨！\n\n訂單編號：#{order_id}\n\n請留意收件，如有問題請聯繫客服。"
                ]
            ],

            // 出貨通知（Phase 33: 發送給買家）
            'shipment_shipped' => [
                'line' => [
                    'message' => "您的訂單已出貨囉！\n\n商品清單：\n{product_list}\n\n物流方式：{shipping_method}\n預計送達：{estimated_delivery}\n\n感謝您的購買！如有問題請聯繫客服。"
                ]
            ],

            // 商品上架通知（Phase 30: 發送給賣家和小幫手）
            'product_created' => [
                'line' => [
                    'message' => "📦 商品上架成功！\n\n商品名稱：{product_name}\n\n查看商品：\n{product_url}"
                ]
            ],

            // 小幫手上架通知（通知非上架者：賣家上架時通知小幫手，小幫手上架時通知賣家）
            'helper_product_created' => [
                'line' => [
                    'message' => "📦 有新商品上架！\n\n商品名稱：{product_name}\n價格：{currency_symbol} {price}{original_price_section}\n數量：{quantity} 個{category_section}{arrival_date_section}{preorder_date_section}\n\n直接下單連結：\n{product_url}"
                ]
            ],

            // 系統通知
            'system_line_follow' => [
                'line' => [
                    'message' => "歡迎使用 BuyGo 商品上架 🎉\n\n【快速開始】\n1️⃣ 發送商品圖片\n2️⃣ 發送商品資訊\n\n【格式範例】\n商品名稱\n價格：350\n數量：20\n\n💡 輸入 /help 查看完整說明"
                ]
            ],
            'flex_image_upload_menu' => [
                'type' => 'flex',
                'line' => [
                    'flex_template' => [
                        'logo_url' => 'https://pub-5ec21b01ebe8403c850311d4ddf55acd.r2.dev/2025/12/line-buygo-logo.png',
                        'title' => '圖片已收到！',
                        'description' => '請選擇您要使用的上架格式：',
                        'buttons' => [
                            ['label' => '單一商品模板', 'action' => '/one'],
                            ['label' => '多樣商品模板', 'action' => '/many'],
                            ['label' => '需要幫助', 'action' => '/help']
                        ]
                    ]
                ]
            ],
            'system_image_upload_failed' => [
                'line' => [
                    'message' => '圖片上傳失敗，請稍後再試。'
                ]
            ],
            'system_product_published' => [
                'line' => [
                    'message' => "商品名稱：{product_name}\n價格：{currency_symbol} {price}{original_price_section}\n數量：{quantity} 個{category_section}{arrival_date_section}{preorder_date_section}\n\n直接下單連結：\n{product_url}"
                ]
            ],
            'system_product_publish_failed' => [
                'line' => [
                    'message' => '❌ 商品上架失敗：{error_message}'
                ]
            ],
            'system_product_data_incomplete' => [
                'line' => [
                    'message' => "商品資料不完整，缺少：{missing_fields}\n\n請使用以下格式：\n商品名稱\n價格：350\n數量：20"
                ]
            ],
            'system_keyword_reply' => [
                'line' => [
                    'message' => '關鍵字回覆訊息'
                ]
            ],
            // 系統權限訊息
            'system_permission_denied' => [
                'line' => [
                    'message' => "😊 {display_name}，您好！\n\n您目前還沒有商品上傳權限。\n\n要成為 BuyGo 賣家，請先購買「BuyGo 賣家資格」虛擬商品。\n\n購買後，您將立即獲得：\n✅ 商品上傳權限\n✅ 商品管理後台\n✅ 訂單管理功能"
                ]
            ],
            // 賣家恭喜通知（LINE）
            'system_seller_grant_line' => [
                'line' => [
                    'message' => "🎉 恭喜 {display_name} 成為 BuyGo 賣家！\n\n您已獲得以下權限：\n✅ BuyGo 管理員角色\n✅ 商品配額：{product_limit} 個\n\n您現在可以開始上架商品了！\n\n📲 後台管理：\n{dashboard_url}\n\n💡 提示：在 LINE 輸入 /id 可查詢您的身份"
                ]
            ],
            // 賣家恭喜通知（Email）
            'system_seller_grant_email' => [
                'line' => [
                    'message' => "親愛的 {display_name}，\n\n恭喜您成為 BuyGo 賣家！\n\n您已獲得以下權限：\n• BuyGo 管理員角色\n• 商品配額：{product_limit} 個\n\n開始使用：\n• 後台管理：{dashboard_url}\n\n綁定 LINE 後，您可以：\n• 直接在 LINE 上架商品\n• 使用 /id 指令查詢身份\n• 接收訂單和出貨通知\n\n祝您生意興隆！\nBuyGo 團隊"
                ]
            ],
            // 命令模板
            'system_command_one_template' => [
                'line' => [
                    'message' => "📋 複製以下格式發送：\n\n商品名稱\n價格：\n數量："
                ]
            ],
            'system_command_many_template' => [
                'line' => [
                    'message' => "📋 複製以下格式發送 (多樣)：\n\n商品名稱\n價格：\n數量：\n款式1：\n款式2："
                ]
            ],

            // 訂單查詢回覆（/訂單 指令；後台可編輯）
            'order_query' => [
                'line' => [
                    'message' => "您目前有 {order_count} 筆進行中訂單\n\n{order_details}\n\n合計：{currency_symbol}{total}\n如有問題請聯絡客服"
                ]
            ],

            // 上架幫手加入通知（發送給賣家）
            'lister_joined' => [
                'line' => [
                    'message' => "📬 新上架幫手加入！\n\n{display_name} 已透過邀請連結加入您的上架團隊。\n現在他可以透過 LINE 幫您上架商品了。"
                ]
            ],

            // 幫手通知（發送給幫手本人）
            'helper_joined_welcome' => [
                'line' => [
                    'message' => "🎉 歡迎加入！\n\n你已成為 {seller_name} 的小幫手。\n現在你可以協助管理商品和訂單了。"
                ]
            ],
            'helper_removed_notice' => [
                'line' => [
                    'message' => "📋 角色變更通知\n\n你已不再是 {seller_name} 的小幫手。\n如有疑問請聯繫賣家。"
                ]
            ],
            'helper_role_changed' => [
                'line' => [
                    'message' => "📋 權限變更通知\n\n你在 {seller_name} 的角色已從「{old_role}」變更為「{new_role}」。"
                ]
            ],
            'lister_joined_welcome' => [
                'line' => [
                    'message' => "🎉 歡迎加入！\n\n你已成為 {seller_name} 的上架幫手。\n現在你可以透過 LINE 傳送商品圖片來幫忙上架了。"
                ]
            ],
            'lister_removed_notice' => [
                'line' => [
                    'message' => "📋 角色變更通知\n\n你已不再是 {seller_name} 的上架幫手。\n如有疑問請聯繫賣家。"
                ]
            ]
        ];
    }

    /**
     * 格式化商品清單為通知訊息格式
     *
     * @param array $items 商品項目陣列，每個元素包含 product_name 和 quantity
     * @return string 格式化後的商品清單字串
     */
    public static function format_product_list(array $items): string {
        if (empty($items)) {
            return '（無商品資訊）';
        }

        $lines = [];
        foreach ($items as $item) {
            $name = esc_html($item['product_name'] ?? '未知商品');
            $qty = intval($item['quantity'] ?? 1);
            $lines[] = "- {$name} x {$qty}";
        }

        return implode("\n", $lines);
    }

    /**
     * 格式化預計送達時間
     *
     * @param string|null $estimated_delivery_at MySQL datetime 格式或 null
     * @return string 格式化後的送達時間（或預設文字）
     */
    public static function format_estimated_delivery(?string $estimated_delivery_at): string {
        if (empty($estimated_delivery_at)) {
            return '配送中';
        }

        // 將 MySQL datetime 轉換為台灣常用格式
        $timestamp = strtotime($estimated_delivery_at);
        if ($timestamp === false) {
            return '配送中';
        }

        return date('Y/m/d', $timestamp);
    }

    /**
     * 格式化物流方式
     *
     * @param string|null $shipping_method 物流方式代碼或名稱
     * @return string 格式化後的物流方式
     */
    public static function format_shipping_method(?string $shipping_method): string {
        if (empty($shipping_method)) {
            return '標準配送';
        }

        // 常見物流方式對照表
        $methods = [
            'standard' => '標準配送',
            'express' => '快速配送',
            'pickup' => '自取',
            'convenience_store' => '超商取貨',
            '7-11' => '7-ELEVEN 取貨',
            'family' => '全家取貨',
            'hilife' => '萊爾富取貨',
            'ok' => 'OK 超商取貨',
        ];

        $method_lower = strtolower($shipping_method);
        return esc_html($methods[$method_lower] ?? $shipping_method);
    }

    /**
     * 取得付款狀態的中文翻譯
     *
     * @param string $status 付款狀態代碼
     * @return string 中文翻譯
     */
    public static function get_payment_status_text($status) {
        $status_map = [
            'unpaid' => '未付款',
            'paid' => '已付款',
            'refunded' => '已退款'
        ];

        return $status_map[$status] ?? $status;
    }

    /**
     * 取得採購狀態的中文翻譯
     *
     * @param string $status 採購狀態代碼
     * @return string 中文翻譯
     */
    public static function get_procurement_status_text($status) {
        $status_map = [
            'pending' => '未處理',
            'processing' => '處理中',
            'active' => '處理中',
            'purchased' => '已採購',
            'completed' => '已到貨',
            'cancelled' => '斷貨'
        ];

        return $status_map[$status] ?? $status;
    }

    /**
     * 取得訂單狀態的中文翻譯
     *
     * @param string $status 訂單狀態代碼
     * @return string 中文翻譯
     */
    public static function get_order_status_text($status) {
        $status_map = [
            'active' => '進行中',
            'completed' => '已完成',
            'cancelled' => '已取消'
        ];

        return $status_map[$status] ?? $status;
    }
}
