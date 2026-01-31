<?php
/**
 * 共用 Header 元件
 *
 * 使用方式：
 * <?php
 * $header_title = '頁面標題';
 * $header_breadcrumb = '<a href="/buygo-portal/dashboard">首頁</a> > <span class="active">當前頁面</span>';
 * $show_currency_toggle = true; // 或 false (預設)
 * include __DIR__ . '/header-component.php';
 * ?>
 */

if (!defined('ABSPATH')) {
    exit;
}

// 預設值
$header_title = $header_title ?? '頁面';
$header_breadcrumb = $header_breadcrumb ?? '<a href="/buygo-portal/dashboard" class="active">首頁</a>';
$show_currency_toggle = $show_currency_toggle ?? false;
?>

<page-header-component
    title="<?php echo esc_attr($header_title); ?>"
    breadcrumb='<?php echo $header_breadcrumb; // 已在各頁面 escape ?>'
    :show-currency-toggle="<?php echo $show_currency_toggle ? 'true' : 'false'; ?>"
    @currency-changed="onCurrencyChange"
></page-header-component>
