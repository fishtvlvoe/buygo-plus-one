<?php
/**
 * 共用 Header 元件
 *
 * 使用方式：
 * <?php
 * $header_title = '頁面標題';
 * $header_breadcrumb = '<a href="/buygo-portal/dashboard">首頁</a> > <span class="active">當前頁面</span>';
 * include __DIR__ . '/header-component.php';
 * ?>
 */

if (!defined('ABSPATH')) {
    exit;
}

// 預設值
$header_title = $header_title ?? '頁面';
$header_breadcrumb = $header_breadcrumb ?? '<a href="/buygo-portal/dashboard" class="active">首頁</a>';
?>

<!-- ============================================ -->
<!-- 頁首部分 (共用 Header 元件) -->
<!-- ============================================ -->
<header class="page-header">
    <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
        <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0">
            <h1 class="page-header-title"><?php echo esc_html($header_title); ?></h1>
            <nav class="page-header-breadcrumb">
                <?php echo $header_breadcrumb; // 已在各頁面 escape ?>
            </nav>
        </div>
    </div>

    <!-- 右側操作區 -->
    <div class="flex items-center gap-2 md:gap-3 shrink-0">
        <!-- 手機版搜尋 icon (640px以下顯示) -->
        <button class="notification-bell sm:hidden" @click="toggleMobileSearch" title="搜尋">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </button>

        <!-- 桌面版全域搜尋框 (640px以上顯示) -->
        <div class="global-search">
            <input type="text" placeholder="搜尋訂單、商品、客戶、出貨單..." v-model="globalSearchQuery" @input="handleGlobalSearch" @keydown.enter="goToSearchPage">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>

        <!-- 幣別切換按鈕 (只在 Dashboard 顯示) -->
        <?php if (isset($show_currency_toggle) && $show_currency_toggle): ?>
        <button class="currency-toggle-btn" @click="cycleCurrency" :title="'切換幣別: ' + displayCurrency">
            <span class="currency-code">{{ displayCurrency }}</span>
        </button>
        <?php endif; ?>

        <!-- 通知鈴鐺 -->
        <button class="notification-bell" @click="toggleNotifications" title="通知">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <span v-if="unreadCount > 0" class="notification-badge">{{ unreadCount }}</span>
        </button>
    </div>
</header>
<!-- 結束:頁首部分 -->

<style>
/* 幣別切換按鈕樣式 */
.currency-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 40px;
    padding: 0 12px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
    font-size: 13px;
    color: #475569;
}

.currency-toggle-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.currency-toggle-btn .currency-code {
    font-family: 'Fira Sans', sans-serif;
    letter-spacing: 0.5px;
}

/* 通知紅點 */
.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 600;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-bell {
    position: relative;
}
</style>
