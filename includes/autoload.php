<?php
/**
 * PSR-4 自動載入器
 *
 * 命名空間 → 檔案路徑自動對應，用到才載入。
 * 參考 LineHub 的 autoloader 實作。
 *
 * @package BuyGoPlus
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {

    // ── 處理 BuyGoPlus 命名空間 ──
    $prefix = 'BuyGoPlus\\';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) === 0) {
        $relative = substr($class, $len);
        $file = buygo_resolve_class_file($relative);
        if ($file && file_exists($file)) {
            require_once $file;
        }
        return;
    }

    // ── 處理 BuygoPlus（小寫 g）命名空間 ──
    $prefix_alt = 'BuygoPlus\\';
    $len_alt = strlen($prefix_alt);

    if (strncmp($prefix_alt, $class, $len_alt) === 0) {
        $relative = substr($class, $len_alt);
        $file = buygo_resolve_class_file($relative);
        if ($file && file_exists($file)) {
            require_once $file;
        }
        return;
    }
});

/**
 * 將命名空間相對路徑轉換為檔案路徑
 *
 * 範例：
 *   Services\ProductService → includes/services/class-product-service.php
 *   Admin\SettingsPage      → includes/admin/class-settings-page.php
 *   Database                → includes/class-database.php（根命名空間）
 *
 * @param string $relative 去掉前綴後的相對類別名稱
 * @return string|null 完整檔案路徑
 */
function buygo_resolve_class_file(string $relative): ?string {

    $parts = explode('\\', $relative);

    // 目錄名稱轉小寫
    for ($i = 0; $i < count($parts) - 1; $i++) {
        $parts[$i] = strtolower($parts[$i]);
    }

    // 類別名稱轉 kebab-case
    $class_name = array_pop($parts);

    // CamelCase → kebab-case（先處理連續大寫如 API → api）
    $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name);
    $kebab = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $kebab);
    $kebab = strtolower($kebab);

    // 處理下劃線類名（如 Products_API → products-api）
    $kebab = str_replace('_', '-', $kebab);

    $filename = 'class-' . $kebab . '.php';

    // 組合路徑
    $base = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/';
    if (!empty($parts)) {
        $base .= implode('/', $parts) . '/';
    }

    $path = $base . $filename;

    // Fallback：複合詞檔名（CamelCase 拆分與實際檔名不一致時）
    // 例：FluentCart → fluent-cart，但實際檔名是 fluentcart
    //     BuyGo → buy-go，但實際檔名是 buygo
    if (!file_exists($path)) {
        $compound_words = ['fluent-cart' => 'fluentcart', 'buy-go' => 'buygo'];
        $alt_kebab = str_replace(array_keys($compound_words), array_values($compound_words), $kebab);
        if ($alt_kebab !== $kebab) {
            $alt_path = $base . 'class-' . $alt_kebab . '.php';
            if (file_exists($alt_path)) {
                return $alt_path;
            }
        }
    }

    return $path;
}
