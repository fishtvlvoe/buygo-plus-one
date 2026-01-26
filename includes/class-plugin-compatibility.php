<?php
namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Compatibility - 外掛兼容性檢查
 *
 * 確保新外掛與舊外掛不會同時運行造成衝突
 */
class PluginCompatibility
{
    /**
     * 舊外掛的主檔案路徑
     */
    const OLD_PLUGIN_FILE = 'buygo/buygo.php';

    /**
     * 舊外掛的目錄名稱
     */
    const OLD_PLUGIN_DIR = 'buygo';

    /**
     * 開發版外掛的主檔案路徑
     */
    const DEV_PLUGIN_FILE = 'buygo-plus-one-dev/buygo-plus-one.php';

    /**
     * 開發版外掛的目錄名稱
     */
    const DEV_PLUGIN_DIR = 'buygo-plus-one-dev';

    /**
     * 檢查是否可以安全啟用新外掛
     *
     * @return array ['can_activate' => bool, 'message' => string, 'warnings' => array]
     */
    public static function check_activation_safety(): array
    {
        $result = [
            'can_activate' => true,
            'message' => '',
            'warnings' => []
        ];

        // 檢查開發版是否啟用 - 這是不允許的（會產生常數衝突）
        if (self::is_dev_plugin_active()) {
            $result['can_activate'] = false;
            $result['message'] = self::get_dev_conflict_message();
            return $result;
        }

        // 檢查舊外掛是否啟用 - 允許共存，但顯示警告
        if (self::is_old_plugin_active()) {
            $result['warnings'][] = [
                'type' => 'old_plugin_active',
                'message' => '偵測到舊版 BuyGo 外掛正在運行。新外掛將以共存模式運行，自動停用可能衝突的功能。建議測試後逐步移轉到新外掛。'
            ];
        }

        // 檢查舊外掛是否存在（但未啟用）
        if (self::is_old_plugin_installed() && !self::is_old_plugin_active()) {
            $result['warnings'][] = [
                'type' => 'old_plugin_installed',
                'message' => '偵測到舊版 BuyGo 外掛已安裝但未啟用。建議在新外掛穩定後移除舊外掛以避免混淆。'
            ];
        }

        // 檢查是否有舊外掛建立的數據
        $data_check = self::check_legacy_data();
        if ($data_check['has_legacy_data']) {
            $result['warnings'][] = [
                'type' => 'legacy_data',
                'message' => sprintf(
                    '偵測到舊外掛的數據：%d 筆 +1 訂單記錄。這些數據將保留，新外掛會讀取 FluentCart 的訂單數據。',
                    $data_check['plus_one_orders_count']
                )
            ];
        }

        return $result;
    }

    /**
     * 檢查舊外掛是否已啟用
     */
    public static function is_old_plugin_active(): bool
    {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active(self::OLD_PLUGIN_FILE);
    }

    /**
     * 檢查舊外掛是否已安裝
     */
    public static function is_old_plugin_installed(): bool
    {
        $plugin_dir = WP_PLUGIN_DIR . '/' . self::OLD_PLUGIN_DIR;
        return is_dir($plugin_dir);
    }

    /**
     * 檢查開發版外掛是否已啟用
     */
    public static function is_dev_plugin_active(): bool
    {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active(self::DEV_PLUGIN_FILE);
    }

    /**
     * 檢查開發版外掛是否已安裝
     */
    public static function is_dev_plugin_installed(): bool
    {
        $plugin_dir = WP_PLUGIN_DIR . '/' . self::DEV_PLUGIN_DIR;
        return is_dir($plugin_dir);
    }

    /**
     * 檢查是否有舊外掛的數據
     */
    public static function check_legacy_data(): array
    {
        global $wpdb;

        $result = [
            'has_legacy_data' => false,
            'plus_one_orders_count' => 0,
            'status_log_count' => 0
        ];

        // 檢查 mygo_plus_one_orders 表
        $plus_one_table = $wpdb->prefix . 'mygo_plus_one_orders';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$plus_one_table}'") === $plus_one_table;

        if ($table_exists) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$plus_one_table}");
            $result['plus_one_orders_count'] = $count;
            if ($count > 0) {
                $result['has_legacy_data'] = true;
            }
        }

        // 檢查 mygo_order_status_log 表
        $status_log_table = $wpdb->prefix . 'mygo_order_status_log';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$status_log_table}'") === $status_log_table;

        if ($table_exists) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$status_log_table}");
            $result['status_log_count'] = $count;
            if ($count > 0) {
                $result['has_legacy_data'] = true;
            }
        }

        return $result;
    }

    /**
     * 取得舊外掛衝突警告訊息（已不再使用，因為允許共存）
     * @deprecated 保留此方法以維持向後兼容
     */
    public static function get_conflict_message(): string
    {
        return <<<HTML
<div class="notice notice-warning">
    <h3>⚠️ BuyGo 外掛共存模式</h3>
    <p>偵測到 <strong>舊版 BuyGo 外掛</strong> 正在運行。</p>
    <p>新外掛將以共存模式運行，並自動停用可能衝突的功能。</p>
    <h4>建議步驟：</h4>
    <ol>
        <li>測試新外掛的所有功能是否正常</li>
        <li>確認數據正確顯示</li>
        <li>測試完成後，可考慮停用舊外掛並移除</li>
    </ol>
</div>
HTML;
    }

    /**
     * 取得開發版衝突錯誤訊息
     */
    public static function get_dev_conflict_message(): string
    {
        return <<<HTML
<div class="notice notice-error">
    <h3>⚠️ 無法啟用 BuyGo+1</h3>
    <p>偵測到 <strong>BuyGo+1 開發版</strong> 正在運行。</p>
    <p>兩個版本使用相同的 PHP 常數和類別名稱，無法同時啟用，會導致嚴重錯誤。</p>
    <h4>發生的問題：</h4>
    <ul>
        <li>PHP Fatal Error: Cannot redeclare constant</li>
        <li>Class already declared 錯誤</li>
        <li>系統完全無法運作</li>
    </ul>
    <h4>解決步驟：</h4>
    <ol>
        <li>停用 BuyGo+1 開發版</li>
        <li>啟用 BuyGo+1 正式版</li>
        <li>若需要開發測試，請在開發環境中使用開發版</li>
    </ol>
</div>
HTML;
    }

    /**
     * 檢查是否有新外掛建立的子訂單數據
     *
     * 子訂單使用 parent_id 和 type='split' 識別
     * 舊外掛無法正確顯示這些訂單
     */
    public static function check_child_orders(): array
    {
        global $wpdb;

        $result = [
            'has_child_orders' => false,
            'child_orders_count' => 0
        ];

        // 檢查 FluentCart 訂單表中的子訂單
        $fc_orders_table = $wpdb->prefix . 'fc_orders';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$fc_orders_table}'") === $fc_orders_table;

        if ($table_exists) {
            // 檢查是否有 parent_id 欄位（子訂單標記）
            $columns = $wpdb->get_col("DESCRIBE {$fc_orders_table}", 0);
            if (in_array('parent_id', $columns)) {
                $count = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$fc_orders_table} WHERE parent_id IS NOT NULL AND parent_id > 0"
                );
                $result['child_orders_count'] = $count;
                if ($count > 0) {
                    $result['has_child_orders'] = true;
                }
            }
        }

        return $result;
    }

    /**
     * 取得子訂單警告訊息
     */
    public static function get_child_orders_warning(): string
    {
        $check = self::check_child_orders();

        if (!$check['has_child_orders']) {
            return '';
        }

        return sprintf(
            '<div class="notice notice-warning"><p><strong>⚠️ 子訂單警告：</strong>偵測到 %d 筆子訂單。' .
            '這些訂單是由新外掛建立的「分單」功能產生。若切換回舊外掛，這些訂單將顯示為「Unknown Product」。</p></div>',
            $check['child_orders_count']
        );
    }

    /**
     * 在外掛啟用時執行檢查
     *
     * 如果檢查失敗，會中止啟用並顯示錯誤訊息
     */
    public static function on_activation(): void
    {
        $check = self::check_activation_safety();

        if (!$check['can_activate']) {
            // 停止啟用並顯示錯誤
            deactivate_plugins(plugin_basename(BUYGO_PLUS_ONE_PLUGIN_FILE));

            wp_die(
                $check['message'],
                '外掛啟用失敗',
                ['back_link' => true]
            );
        }

        // 記錄警告到日誌
        if (!empty($check['warnings'])) {
            $log_file = WP_CONTENT_DIR . '/buygo-plus-one.log';
            foreach ($check['warnings'] as $warning) {
                file_put_contents($log_file, sprintf(
                    "[%s] [COMPATIBILITY_WARNING] %s\n",
                    date('Y-m-d H:i:s'),
                    $warning['message']
                ), FILE_APPEND);
            }
        }
    }

    /**
     * 在運行時檢查兼容性（每次載入時）
     *
     * 如果偵測到開發版被啟用，顯示管理員錯誤通知
     * 如果偵測到舊外掛被啟用，顯示管理員資訊通知
     */
    public static function runtime_check(): void
    {
        if (!is_admin()) {
            return;
        }

        // 檢查開發版衝突（嚴重錯誤）
        if (self::is_dev_plugin_active()) {
            add_action('admin_notices', [self::class, 'show_dev_conflict_notice']);
        }

        // 檢查舊外掛共存（資訊通知）
        if (self::is_old_plugin_active()) {
            add_action('admin_notices', [self::class, 'show_coexist_info_notice']);
        }
    }

    /**
     * 顯示衝突通知（已廢棄，保留向後兼容）
     * @deprecated
     */
    public static function show_conflict_notice(): void
    {
        // 此方法已被 show_dev_conflict_notice 和 show_coexist_info_notice 取代
        self::show_coexist_info_notice();
    }

    /**
     * 顯示開發版衝突的嚴重錯誤通知
     */
    public static function show_dev_conflict_notice(): void
    {
        echo <<<HTML
<div class="notice notice-error is-dismissible">
    <h3>🚫 BuyGo+1 外掛衝突 - 嚴重錯誤</h3>
    <p><strong>BuyGo+1 正式版</strong> 和 <strong>BuyGo+1 開發版</strong> 同時啟用中！</p>
    <p style="color: #d63638;"><strong>⚠️ 這會導致 PHP Fatal Error 和系統崩潰！</strong></p>
    <h4>衝突原因：</h4>
    <ul>
        <li>兩個外掛定義了相同的 PHP 常數（BUYGO_PLUS_ONE_VERSION 等）</li>
        <li>兩個外掛使用相同的 namespace 和類別名稱</li>
        <li>PHP 不允許重複定義，會產生 Fatal Error</li>
    </ul>
    <p><strong>請立即停用其中一個外掛！建議保留正式版，停用開發版。</strong></p>
</div>
HTML;
    }

    /**
     * 顯示與舊外掛共存的資訊通知
     */
    public static function show_coexist_info_notice(): void
    {
        $child_orders_check = self::check_child_orders();
        $child_orders_warning = '';

        if ($child_orders_check['has_child_orders']) {
            $child_orders_warning = sprintf(
                '<p style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;">' .
                '<strong>📊 子訂單資訊：</strong>系統中有 %d 筆子訂單（由新外掛的分單功能產生）。' .
                '若停用新外掛，這些訂單在舊外掛中將顯示為「Unknown Product」。</p>',
                $child_orders_check['child_orders_count']
            );
        }

        echo <<<HTML
<div class="notice notice-info is-dismissible">
    <h3>ℹ️ BuyGo 外掛共存模式</h3>
    <p><strong>BuyGo+1</strong> 和 <strong>舊版 BuyGo</strong> 正在共存模式下運行。</p>
    <p>新外掛已自動偵測到舊外掛，並停用可能衝突的功能以確保系統穩定。</p>
    <h4>📋 共存模式說明：</h4>
    <ul>
        <li>✅ 新外掛的核心功能正常運作</li>
        <li>✅ 與舊外掛不衝突的功能已啟用</li>
        <li>⚠️ 可能衝突的功能已自動停用</li>
    </ul>
    {$child_orders_warning}
    <p><strong>建議：</strong>測試新外掛功能正常後，可考慮停用並移除舊外掛以獲得最佳效能。</p>
</div>
HTML;
    }

    /**
     * 取得兼容性狀態報告
     */
    public static function get_status_report(): array
    {
        $dev_active = self::is_dev_plugin_active();
        $old_active = self::is_old_plugin_active();

        return [
            'dev_plugin_active' => $dev_active,
            'dev_plugin_installed' => self::is_dev_plugin_installed(),
            'old_plugin_active' => $old_active,
            'old_plugin_installed' => self::is_old_plugin_installed(),
            'legacy_data' => self::check_legacy_data(),
            'child_orders' => self::check_child_orders(),
            'safe_to_run' => !$dev_active, // 只要開發版未啟用就安全
            'coexist_mode' => $old_active && !$dev_active // 共存模式
        ];
    }

    /**
     * 取得 HTML 格式的狀態報告（用於設定頁面）
     */
    public static function get_html_status_report(): string
    {
        $report = self::get_status_report();

        $html = '<div class="buygo-compatibility-report" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin: 10px 0;">';
        $html .= '<h3 style="margin-top: 0;">🔍 外掛兼容性狀態</h3>';

        // 開發版外掛狀態
        if ($report['dev_plugin_active']) {
            $html .= '<p style="color: #d63638;">🚫 <strong>BuyGo+1 開發版：</strong>啟用中（嚴重衝突！請立即停用）</p>';
        } elseif ($report['dev_plugin_installed']) {
            $html .= '<p style="color: #dba617;">⚠️ <strong>BuyGo+1 開發版：</strong>已安裝但未啟用</p>';
        } else {
            $html .= '<p style="color: #00a32a;">✅ <strong>BuyGo+1 開發版：</strong>未安裝</p>';
        }

        // 舊外掛狀態
        if ($report['old_plugin_active']) {
            $html .= '<p style="color: #0073aa;">ℹ️ <strong>舊版 BuyGo 外掛：</strong>啟用中（共存模式）</p>';
        } elseif ($report['old_plugin_installed']) {
            $html .= '<p style="color: #dba617;">⚠️ <strong>舊版 BuyGo 外掛：</strong>已安裝但未啟用</p>';
        } else {
            $html .= '<p style="color: #00a32a;">✅ <strong>舊版 BuyGo 外掛：</strong>未安裝</p>';
        }

        // 舊數據狀態
        if ($report['legacy_data']['has_legacy_data']) {
            $html .= sprintf(
                '<p style="color: #dba617;">⚠️ <strong>舊外掛數據：</strong>%d 筆 +1 訂單記錄</p>',
                $report['legacy_data']['plus_one_orders_count']
            );
        } else {
            $html .= '<p style="color: #00a32a;">✅ <strong>舊外掛數據：</strong>無</p>';
        }

        // 子訂單狀態
        if ($report['child_orders']['has_child_orders']) {
            $html .= sprintf(
                '<p style="color: #dba617;">⚠️ <strong>子訂單數據：</strong>%d 筆（若切換回舊外掛將無法正常顯示）</p>',
                $report['child_orders']['child_orders_count']
            );
        } else {
            $html .= '<p style="color: #00a32a;">✅ <strong>子訂單數據：</strong>無</p>';
        }

        // 總結
        $html .= '<hr style="margin: 15px 0;">';
        if ($report['dev_plugin_active']) {
            $html .= '<p style="color: #d63638;"><strong>🚫 目前狀態：嚴重衝突！開發版和正式版不能同時啟用</strong></p>';
        } elseif ($report['coexist_mode']) {
            $html .= '<p style="color: #0073aa;"><strong>ℹ️ 目前狀態：共存模式 - 新舊外掛同時運行</strong></p>';
        } elseif ($report['safe_to_run']) {
            $html .= '<p style="color: #00a32a;"><strong>✅ 目前狀態：安全運行中</strong></p>';
        } else {
            $html .= '<p style="color: #d63638;"><strong>❌ 目前狀態：檢測到衝突</strong></p>';
        }

        // 切換建議
        if ($report['dev_plugin_active']) {
            $html .= '<p><strong>🔧 處理建議：</strong>請立即停用 BuyGo+1 開發版或正式版其中一個，建議保留正式版。</p>';
        } elseif ($report['coexist_mode']) {
            $html .= '<p><strong>📋 共存模式建議：</strong>新外掛已自動停用可能衝突的功能。測試完成後建議停用舊外掛以獲得最佳效能。</p>';
        } elseif ($report['child_orders']['has_child_orders']) {
            $html .= '<p><strong>📋 切換建議：</strong>由於已有子訂單數據，建議繼續使用新外掛。若需切換回舊外掛，子訂單將顯示異常。</p>';
        } else {
            $html .= '<p><strong>📋 切換建議：</strong>目前可安全在新舊外掛間切換（一次只能啟用一個，開發版與正式版互斥）。</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * 判斷是否可以安全切換回舊外掛
     */
    public static function can_safely_switch_to_old(): array
    {
        $child_orders = self::check_child_orders();

        $result = [
            'can_switch' => true,
            'warnings' => []
        ];

        if ($child_orders['has_child_orders']) {
            $result['can_switch'] = false;
            $result['warnings'][] = sprintf(
                '系統中有 %d 筆子訂單，切換回舊外掛會導致這些訂單顯示為「Unknown Product」',
                $child_orders['child_orders_count']
            );
        }

        return $result;
    }
}
