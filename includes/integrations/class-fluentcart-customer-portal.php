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

        // 偵測 ?buygo_embed=1 參數：注入 CSS 隱藏 WordPress header/footer/sidebar
        // 讓 iframe 只顯示 FluentCart 會員中心內容區
        add_action('wp_head', [self::class, 'maybeInjectEmbedStyles']);
    }

    /**
     * 偵測 ?buygo_embed=1 並注入 CSS 隱藏 WordPress 外框
     *
     * 讓 WordPress 正常載入頁面（shortcode 正常渲染），
     * 但用 CSS 隱藏 header、footer、sidebar、admin bar 等外框元素，
     * 只留下 FluentCart 會員中心的內容區。
     *
     * @return void
     */
    public static function maybeInjectEmbedStyles(): void {
        if (empty($_GET['buygo_embed'])) {
            return;
        }

        // 隱藏 WordPress 外框，只顯示 FluentCart 內容區
        ?>
        <style id="buygo-embed-styles">
            /* Blocksy 主題：隱藏 header、footer、頁面標題 */
            #wpadminbar,
            header.ct-header, .ct-header,
            footer.ct-footer,
            .hero-section,
            .ct-breadcrumbs { display: none !important; }

            /* 移除 admin bar 留白 */
            html.admin-bar { margin-top: 0 !important; }

            /* 內容區全寬、移除主題邊距 */
            body { margin: 0; padding: 0; background: #f9fafb; }
            .ct-container { max-width: 100% !important; padding: 0 !important; }
            .site-main { padding: 0 !important; }
        </style>
        <?php
    }

    /**
     * 向 FluentCart 會員中心註冊自訂頁面
     *
     * 同時移除 line-hub 外掛在 fluent_cart/customer_app 注入的 LINE 綁定區塊：
     * 買家入口（/buygo-portal/）已有獨立的 LINE 綁定頁面，
     * 在 FluentCart 儀表板重複顯示會造成版面混亂。
     *
     * @return void
     */
    public static function registerEndpoints() {
        if (!function_exists('fluent_cart_api')) {
            return;
        }

        // 移除 line-hub 外掛在 FluentCart 客戶儀表板注入的 LINE 綁定區塊
        // 注入點：LineHub\Integration\FluentCartConnector::renderBindingSection（優先級 90）
        remove_action('fluent_cart/customer_app', ['LineHub\Integration\FluentCartConnector', 'renderBindingSection'], 90);

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
        // 白名單驗證，防止 per_page=0 造成除以零
        $valid_per_page = [10, 20, 50, 100];
        $per_page = in_array((int)($_GET['per_page'] ?? 10), $valid_per_page, true)
            ? (int)$_GET['per_page']
            : 10;
        $current_page = max(1, (int)($_GET['pg'] ?? 1));
        $total_pages = max(1, ceil($total_items / $per_page));
        $current_page = min($current_page, $total_pages);
        $offset = ($current_page - 1) * $per_page;
        $page_rows = array_slice($all_rows, $offset, $per_page);
        $symbol = !empty($all_rows) ? $all_rows[0]['symbol'] : '¥';

        echo '<div class="fct_order_list" style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">';

        // 標題
        echo '<div style="padding:16px 24px;border-bottom:1px solid #e5e7eb;">';
        echo '<h3 style="font-size:16px;font-weight:600;color:#1f2937;margin:0;">訂單進度</h3>';
        echo '</div>';

        // 欄位標頭
        echo '<div style="display:flex;align-items:center;padding:10px 24px;border-bottom:1px solid #e5e7eb;background:#f9fafb;gap:16px;">';
        echo '<div style="min-width:120px;font-size:13px;font-weight:600;color:#374151;">#</div>';
        echo '<div style="flex:1;font-size:13px;font-weight:600;color:#374151;">產品</div>';
        echo '<div style="min-width:80px;text-align:center;font-size:13px;font-weight:600;color:#374151;">進度</div>';
        echo '<div style="min-width:100px;text-align:right;font-size:13px;font-weight:600;color:#374151;">價格</div>';
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

        // 底部：分頁（跟 FluentCart 購買歷史一樣的排版）
        $base_url = strtok($_SERVER['REQUEST_URI'], '?');
        echo '<div style="padding:12px 24px;border-top:1px solid #e5e7eb;background:#f9fafb;">';

        // 上排：分頁控制（一行排開）
        echo '<div style="display:flex;align-items:center;gap:12px;white-space:nowrap;">';
        echo "<span style='font-size:13px;color:#6b7280;'>第 {$current_page} 頁，共 {$total_pages} 頁</span>";
        echo '<select onchange="window.location.href=\'' . esc_url($base_url) . '?per_page=\'+this.value" style="padding:4px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;color:#374151;background:#fff;cursor:pointer;width:auto;max-width:90px;-webkit-appearance:menulist;">';
        foreach ([10, 20, 50, 100] as $opt) {
            $sel = ($opt === $per_page) ? ' selected' : '';
            echo "<option value='{$opt}'{$sel}>{$opt} / 頁</option>";
        }
        echo '</select>';
        echo "<span style='font-size:13px;color:#6b7280;'>總計{$total_items}</span>";

        // 頁碼按鈕
        if ($total_pages > 1) {
            echo '<span style="display:inline-flex;align-items:center;gap:4px;margin-left:auto;">';
            if ($current_page > 1) {
                echo "<a href='" . esc_url($base_url . "?pg=" . ($current_page-1) . "&per_page={$per_page}") . "' style='padding:4px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;color:#374151;text-decoration:none;'>&lt;</a>";
            }
            for ($p = 1; $p <= $total_pages; $p++) {
                $style = ($p === $current_page) ? 'background:#fff;border:2px solid #374151;font-weight:600;' : 'border:1px solid #d1d5db;';
                echo "<a href='" . esc_url($base_url . "?pg={$p}&per_page={$per_page}") . "' style='padding:4px 10px;border-radius:6px;font-size:13px;color:#374151;text-decoration:none;{$style}'>{$p}</a>";
            }
            if ($current_page < $total_pages) {
                echo "<a href='" . esc_url($base_url . "?pg=" . ($current_page+1) . "&per_page={$per_page}") . "' style='padding:4px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;color:#374151;text-decoration:none;'>&gt;</a>";
            }
            echo '</span>';
        }
        echo '</div>';

        // 下排：合計
        echo "<div style='text-align:right;margin-top:8px;font-size:14px;font-weight:600;color:#1f2937;'>合計：{$symbol}" . number_format($total_amount, 2) . "</div>";

        echo '</div>';
        echo '</div>';
    }

}
