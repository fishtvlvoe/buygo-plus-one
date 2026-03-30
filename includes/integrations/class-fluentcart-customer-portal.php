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

        // 1. 訂單進度頁（跟 FluentCart 同風格：20x20 filled icon）
        fluent_cart_api()->addCustomerDashboardEndpoint('order-tracking', [
            'title'           => '訂單進度',
            'icon_svg'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M16 17.5H4C3.80109 17.5 3.61032 17.421 3.46967 17.2803C3.32902 17.1397 3.25 16.9489 3.25 16.75V3.25C3.25 3.05109 3.32902 2.86032 3.46967 2.71967C3.61032 2.57902 3.80109 2.5 4 2.5H16C16.1989 2.5 16.3897 2.57902 16.5303 2.71967C16.671 2.86032 16.75 3.05109 16.75 3.25V16.75C16.75 16.9489 16.671 17.1397 16.5303 17.2803C16.3897 17.421 16.1989 17.5 16 17.5ZM15.25 16V4H4.75V16H15.25ZM6.25 6.25H10V7.75H6.25V6.25ZM6.25 9.25H13.75V10.75H6.25V9.25ZM6.25 12.25H13.75V13.75H6.25V12.25ZM12.25 6.25L13.75 7.75L12.25 6.25Z" fill="currentColor"/><path d="M7.5 6.625L8.75 7.875L11.25 5.375" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'render_callback' => [self::class, 'renderOrderTracking'],
        ]);

        // LINE 綁定頁已移除（每個頁面都有 LINE 登入，不需要獨立頁面）
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

        // 把所有項目攤平成一維陣列（方便分頁）
        $all_rows = [];
        foreach ($order_groups as $oid => $group) {
            foreach ($group['items'] as $itm) {
                $all_rows[] = array_merge($itm, [
                    'invoice_no' => $group['invoice_no'],
                    'symbol' => $group['symbol'],
                    'line_total' => $itm['price'] * $itm['qty'],
                ]);
            }
        }

        $total_items = count($all_rows);
        $per_page = (int)($_GET['per_page'] ?? 10);
        $current_page = max(1, (int)($_GET['pg'] ?? 1));
        $total_pages = max(1, ceil($total_items / $per_page));
        $current_page = min($current_page, $total_pages);
        $offset = ($current_page - 1) * $per_page;
        $page_rows = array_slice($all_rows, $offset, $per_page);
        $symbol = !empty($all_rows) ? $all_rows[0]['symbol'] : '¥';

        echo '<div class="fct_order_list" style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">';

        // 表頭
        echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid #e5e7eb;">';
        echo '<h3 style="font-size:16px;font-weight:600;color:#1f2937;margin:0;">訂單進度</h3>';
        echo '</div>';

        // 表格列表
        foreach ($page_rows as $itm) {
            echo '<div style="display:flex;align-items:center;padding:16px 24px;border-bottom:1px solid #f3f4f6;gap:16px;">';

            // 左：訂單編號
            echo '<div style="min-width:120px;">';
            echo "<div style='font-size:14px;font-weight:500;color:#1f2937;'>" . esc_html($itm['invoice_no']) . "</div>";
            echo '</div>';

            // 中：商品名稱
            echo '<div style="flex:1;min-width:0;">';
            echo "<div style='font-size:14px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>" . esc_html($itm['name']) . "</div>";
            echo '</div>';

            // 狀態標籤
            echo "<span style='display:inline-block;padding:4px 12px;border-radius:9999px;font-size:12px;font-weight:500;white-space:nowrap;"
                . "background:{$itm['status_bg']};color:{$itm['status_color']};'>"
                . esc_html($itm['status']) . "</span>";

            // 右：金額
            echo "<div style='min-width:100px;text-align:right;font-size:14px;color:#1f2937;white-space:nowrap;'>"
                . "{$itm['symbol']}" . number_format($itm['line_total'], 2) . "</div>";

            echo '</div>';
        }

        // 底部：分頁 + 合計
        echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 24px;border-top:1px solid #e5e7eb;background:#f9fafb;flex-wrap:wrap;gap:8px;">';
        echo "<span style='font-size:13px;color:#6b7280;'>第 {$current_page} 頁，共 {$total_pages} 頁　總計 {$total_items}</span>";
        echo "<span style='font-size:14px;font-weight:600;color:#1f2937;'>合計：{$symbol}" . number_format($total_amount, 2) . "</span>";
        echo '</div>';

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
