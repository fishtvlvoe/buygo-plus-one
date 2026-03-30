<?php
/**
 * FluentCart 會員中心整合
 *
 * 在 FluentCart 會員頁面注入自訂分頁：
 * - 訂單進度：顯示進行中訂單的分配/出貨狀態
 * - LINE 綁定：顯示帳號綁定狀態與綁定碼
 *
 * @package BuyGoPlus\Integrations
 * @since 0.4.2
 */

namespace BuyGoPlus\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class FluentCartCustomerPortal {

    /**
     * 初始化整合
     * 確認 FluentCart 已啟用後，掛載 init hook 來註冊 endpoint
     *
     * @return void
     */
    public static function init() {
        // 確認 FluentCart 已啟用
        if (!function_exists('fluent_cart_api')) {
            return;
        }

        // 在 init hook 裡註冊 endpoint（優先級 20，確保 FluentCart 已初始化）
        add_action('init', [self::class, 'registerEndpoints'], 20);
    }

    /**
     * 向 FluentCart 會員中心註冊自訂頁面
     *
     * @return void
     */
    public static function registerEndpoints() {
        if (!function_exists('fluent_cart_api')) {
            return;
        }

        // 1. 訂單進度頁（Lucide: truck icon）
        fluent_cart_api()->addCustomerDashboardEndpoint('order-tracking', [
            'title'           => '訂單進度',
            'icon_svg'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
            'render_callback' => [self::class, 'renderOrderTracking'],
        ]);

        // 2. LINE 綁定頁（LINE logo 簡化版）
        fluent_cart_api()->addCustomerDashboardEndpoint('line-binding', [
            'title'           => 'LINE 綁定',
            'icon_svg'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>',
            'render_callback' => [self::class, 'renderLineBinding'],
        ]);
    }

    /**
     * 渲染「訂單進度」頁面
     *
     * 顯示登入用戶所有進行中訂單的分配與出貨狀態。
     * 透過 customer email 對應 FluentCart 的 customer_id，
     * 查詢未完成/未取消訂單的所有項目。
     *
     * @return void
     */
    public static function renderOrderTracking() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<p>請先登入</p>';
            return;
        }

        global $wpdb;
        $table_orders        = $wpdb->prefix . 'fct_orders';
        $table_items         = $wpdb->prefix . 'fct_order_items';
        $table_customers     = $wpdb->prefix . 'fct_customers';
        $table_variations    = $wpdb->prefix . 'fct_product_variations';
        $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';

        // 從 wp_users.email 對應到 fct_customers.id
        $user           = get_userdata($user_id);
        $customer_email = $user ? $user->user_email : '';

        $customer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_customers} WHERE email = %s LIMIT 1",
            $customer_email
        ));

        if (!$customer_id) {
            echo '<div style="text-align:center;padding:40px 20px;color:#64748B;">';
            echo '<p style="font-size:18px;">目前沒有訂單紀錄</p>';
            echo '</div>';
            return;
        }

        // 查詢進行中訂單的所有項目（排除已完成、已取消、已退款）
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT o.id as order_id, o.invoice_no, o.shipping_status, o.currency,
                    oi.quantity, oi.unit_price, oi.object_id, oi.id as order_item_id,
                    oi.line_meta,
                    pv.variation_title,
                    COALESCE(p.post_title, oi.title, '') as product_name,
                    COALESCE((SELECT SUM(si.quantity) FROM {$table_shipment_items} si WHERE si.order_item_id = oi.id), 0) as shipped_qty
             FROM {$table_orders} o
             INNER JOIN {$table_items} oi ON oi.order_id = o.id
             LEFT JOIN {$table_variations} pv ON pv.id = oi.object_id
             LEFT JOIN {$wpdb->posts} p ON p.ID = pv.post_id
             WHERE o.customer_id = %d
               AND o.parent_id IS NULL
               AND o.status NOT IN ('cancelled', 'refunded', 'completed')
             ORDER BY o.id DESC",
            $customer_id
        ));

        if (empty($items)) {
            echo '<div style="text-align:center;padding:40px 20px;color:#64748B;">';
            echo '<p style="font-size:18px;">目前沒有進行中的訂單</p>';
            echo '<p style="font-size:14px;margin-top:8px;">所有訂單都已完成！</p>';
            echo '</div>';
            return;
        }

        // 依訂單分組，計算每個項目的狀態
        $total_amount = 0;
        $order_groups = [];

        foreach ($items as $item) {
            $meta      = json_decode($item->line_meta ?? '{}', true) ?: [];
            $allocated = (int) ($meta['_allocated_qty'] ?? 0);
            $shipped   = max((int) $item->shipped_qty, (int) ($meta['_shipped_qty'] ?? 0));
            $qty       = (int) $item->quantity;

            // 判斷出貨狀態（依優先順序）
            if ($shipped >= $qty) {
                $status       = '已出貨';
                $status_icon  = '🚚';
                $status_bg    = '#DCFCE7';
                $status_color = '#166534';
            } elseif ($item->shipping_status === 'preparing') {
                $status       = '備貨中';
                $status_icon  = '📦';
                $status_bg    = '#DBEAFE';
                $status_color = '#1E40AF';
            } elseif ($allocated > 0 || $shipped > 0) {
                $status       = '已分配';
                $status_icon  = '✅';
                $status_bg    = '#D1FAE5';
                $status_color = '#065F46';
            } else {
                $status       = '待分配';
                $status_icon  = '⏳';
                $status_bg    = '#FEF3C7';
                $status_color = '#92400E';
            }

            // 商品顯示名稱（含規格）
            $name = $item->product_name;
            if (!empty($item->variation_title)) {
                $name .= ' ' . $item->variation_title;
            }

            // FluentCart 金額以分為單位
            $unit_price = (float) $item->unit_price / 100;
            $line_total = $unit_price * $qty;
            $total_amount += $line_total;

            // 幣別符號
            $currency = $item->currency ?: 'JPY';
            $symbol   = ($currency === 'JPY' || $currency === 'jpy') ? '¥' : '$';

            $order_id = $item->order_id;
            if (!isset($order_groups[$order_id])) {
                $order_groups[$order_id] = [
                    'invoice_no' => $item->invoice_no ?: "#{$order_id}",
                    'items'      => [],
                    'subtotal'   => 0,
                    'symbol'     => $symbol,
                ];
            }

            $order_groups[$order_id]['items'][] = [
                'name'         => $name,
                'qty'          => $qty,
                'price'        => $unit_price,
                'status'       => $status,
                'status_icon'  => $status_icon,
                'status_bg'    => $status_bg,
                'status_color' => $status_color,
            ];
            $order_groups[$order_id]['subtotal'] += $line_total;
        }

        // 輸出 HTML
        $order_count = count($order_groups);
        $symbol      = !empty($order_groups) ? reset($order_groups)['symbol'] : '¥';

        echo '<div style="max-width:600px;">';
        echo "<h3 style='font-size:18px;font-weight:600;color:#1E293B;margin-bottom:16px;'>📦 您有 {$order_count} 筆進行中訂單</h3>";

        foreach ($order_groups as $oid => $group) {
            echo '<div style="background:#F8FAFC;border-radius:12px;padding:16px;margin-bottom:12px;">';
            echo "<div style='font-size:13px;color:#64748B;margin-bottom:12px;font-weight:600;'>" . esc_html($group['invoice_no']) . "</div>";

            foreach ($group['items'] as $itm) {
                echo '<div style="background:#FFFFFF;border-radius:8px;padding:12px 16px;margin-bottom:8px;">';
                echo "<div style='font-size:14px;font-weight:500;color:#1E293B;'>" . esc_html($itm['name']) . "</div>";
                echo "<div style='font-size:13px;color:#64748B;margin-top:4px;'>{$itm['qty']} &times; {$group['symbol']}" . number_format($itm['price']) . "</div>";
                echo "<span style='display:inline-block;margin-top:6px;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:500;"
                    . "background:{$itm['status_bg']};color:{$itm['status_color']};'>"
                    . "{$itm['status_icon']} " . esc_html($itm['status']) . "</span>";
                echo '</div>';
            }

            if (count($group['items']) > 1) {
                echo "<div style='text-align:right;font-size:13px;color:#64748B;margin-top:4px;'>小計：{$group['symbol']}" . number_format($group['subtotal']) . "</div>";
            }
            echo '</div>';
        }

        echo "<div style='text-align:right;font-size:16px;font-weight:600;color:#1E293B;margin-top:8px;'>合計：{$symbol}" . number_format($total_amount) . "</div>";
        echo '</div>';
    }

    /**
     * 渲染「LINE 綁定」頁面
     *
     * 顯示目前用戶的 LINE 綁定狀態：
     * - 已綁定：顯示綁定資訊
     * - 待綁定（有效綁定碼未過期）：顯示綁定碼
     * - 未綁定：顯示提示訊息
     *
     * @return void
     */
    public static function renderLineBinding() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<p>請先登入</p>';
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'buygo_line_bindings';

        // 查詢已完成的綁定記錄
        $binding = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'completed' LIMIT 1",
            $user_id
        ));

        echo '<div style="max-width:500px;">';

        if ($binding) {
            // 已綁定：顯示綁定資訊
            $user         = get_userdata($user_id);
            $display_name = $user ? $user->display_name : '未知';
            $line_uid     = $binding->line_uid;
            $bound_date   = date('Y-m-d', strtotime($binding->completed_at ?: $binding->created_at));

            echo '<div style="background:#F0FDF4;border-radius:12px;padding:24px;text-align:center;">';
            echo '<div style="font-size:48px;margin-bottom:12px;">🟢</div>';
            echo '<h3 style="font-size:18px;font-weight:600;color:#166534;margin-bottom:16px;">LINE 帳號已綁定</h3>';
            echo '<div style="background:#FFFFFF;border-radius:8px;padding:16px;text-align:left;">';
            echo "<div style='font-size:15px;font-weight:500;color:#1E293B;'>" . esc_html($display_name) . "</div>";
            echo "<div style='font-size:12px;color:#64748B;margin-top:4px;'>LINE UID: " . esc_html(substr($line_uid, 0, 12)) . "...</div>";
            echo "<div style='font-size:12px;color:#64748B;margin-top:2px;'>綁定日期：" . esc_html($bound_date) . "</div>";
            echo '</div>';
            echo '</div>';
        } else {
            // 未完成綁定：查看是否有尚未過期的 pending 綁定碼
            $pending = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND status = 'pending' AND binding_code_expires_at > NOW() LIMIT 1",
                $user_id
            ));

            echo '<div style="background:#FEF3C7;border-radius:12px;padding:24px;text-align:center;">';
            echo '<div style="font-size:48px;margin-bottom:12px;">⚠️</div>';
            echo '<h3 style="font-size:18px;font-weight:600;color:#92400E;margin-bottom:8px;">尚未綁定 LINE</h3>';
            echo '<p style="font-size:14px;color:#92400E;margin-bottom:16px;">綁定後可接收訂單通知和查詢訂單狀態</p>';

            if ($pending) {
                // 有有效的綁定碼，提示用戶前往 LINE 輸入
                echo '<div style="background:#FFFFFF;border-radius:8px;padding:16px;">';
                echo '<p style="font-size:13px;color:#64748B;margin-bottom:8px;">請在 LINE 官方帳號輸入以下綁定碼：</p>';
                echo "<div style='font-size:24px;font-weight:700;color:#2563EB;letter-spacing:4px;'>" . esc_html($pending->binding_code) . "</div>";
                echo '</div>';
            } else {
                // 無有效綁定碼，引導聯繫客服
                echo '<p style="font-size:13px;color:#92400E;">請聯繫客服取得綁定方式</p>';
            }

            echo '</div>';
        }

        echo '</div>';
    }
}
