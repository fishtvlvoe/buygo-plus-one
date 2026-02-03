<?php
/**
 * 出貨明細頁面
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php';

// 設定 Header 參數
$header_title = '出貨';
$header_breadcrumb = '首頁 <span class="text-slate-300">/</span> 出貨管理';
$show_currency_toggle = false;

// 載入共用 Header
ob_start();
include __DIR__ . '/header-component.php';
$header_html = ob_get_clean();

// HTML Template
$shipment_details_template = <<<'HTML'
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
    <!-- Main Content -->
    <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">
HTML;

// 將 Header 加入模板
$shipment_details_template .= $header_html;

$shipment_details_template .= <<<'HTML'

    <!-- ============================================ -->
    <!-- 內容區域 -->
    <!-- ============================================ -->
    <div class="flex-1 overflow-auto bg-slate-50/50 relative">

        <!-- 列表檢視 -->
        <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6 w-full max-w-7xl mx-auto space-y-4 md:space-y-6">

            <!-- Toolbar: Search -->
            <div class="flex items-center">
                <div class="flex-1 flex items-center">
                    <smart-search-box
                        api-endpoint="/wp-json/buygo-plus-one/v1/shipments"
                        :search-fields="['product_name', 'customer_name']"
                        placeholder="搜尋商品或客戶"
                        display-field="product_name"
                        display-sub-field="customer_name"
                        :show-currency-toggle="false"
                        @select="handleSearchSelect"
                        @search="handleSearchInput"
                        @clear="handleSearchClear"
                        class="w-full"
                    />
                </div>
            </div>

            <!-- 分頁 Tabs -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="flex gap-2 md:gap-8 px-3 md:px-6 border-b border-slate-200">
                    <button
                        @click="activeTab = 'ready_to_ship'"
                        :class="activeTab === 'ready_to_ship' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                        class="flex-1 py-4 px-1 border-b-2 font-medium text-sm transition whitespace-nowrap"
                    >
                        待出貨
                        <span v-if="stats.ready_to_ship > 0" class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-600 rounded-full text-xs">
                            {{ stats.ready_to_ship }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'shipped'"
                        :class="activeTab === 'shipped' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                        class="flex-1 py-4 px-1 border-b-2 font-medium text-sm transition whitespace-nowrap"
                    >
                        已出貨
                        <span v-if="stats.shipped > 0" class="ml-2 px-2 py-0.5 bg-green-100 text-green-600 rounded-full text-xs">
                            {{ stats.shipped }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'archived'"
                        :class="activeTab === 'archived' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                        class="flex-1 py-4 px-1 border-b-2 font-medium text-sm transition whitespace-nowrap"
                    >
                        存檔區
                        <span v-if="stats.archived > 0" class="ml-2 px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full text-xs">
                            {{ stats.archived }}
                        </span>
                    </button>
                </div>
    
                <!-- 批次操作工具列（只在有勾選時顯示） -->
                <div v-if="selectedShipments.length > 0" class="bg-orange-50 border-b border-orange-200 px-4 md:px-6 py-3">
                    <!-- 桌面版 -->
                    <div class="hidden md:flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-slate-700">
                                已選擇 {{ selectedShipments.length }} 個出貨單
                            </span>
                            <button
                                @click="clearSelection"
                                class="text-sm text-slate-600 hover:text-slate-900"
                            >
                                清除勾選
                            </button>
                        </div>

                        <div class="flex items-center gap-3">
                            <!-- 待出貨分頁：批次標記已出貨 -->
                            <button
                                v-if="activeTab === 'ready_to_ship'"
                                @click="batchMarkShipped"
                                class="btn btn-primary"
                            >
                                批次標記已出貨（{{ selectedShipments.length }}）
                            </button>

                            <!-- 已出貨分頁：批次移至存檔 -->
                            <button
                                v-if="activeTab === 'shipped'"
                                @click="batchArchive"
                                class="btn btn-secondary"
                            >
                                批次移至存檔（{{ selectedShipments.length }}）
                            </button>

                            <!-- 批次匯出 Excel -->
                            <button
                                v-if="selectedShipments.length > 0"
                                @click="batchExport"
                                class="px-4 py-2 bg-slate-100 text-slate-900 rounded-lg hover:bg-slate-200 transition font-medium text-sm"
                            >
                                <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                匯出 Excel（{{ selectedShipments.length }}）
                            </button>
                        </div>
                    </div>

                    <!-- 手機版 -->
                    <div class="md:hidden space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-700">
                                已選 {{ selectedShipments.length }} 筆
                            </span>
                            <button
                                @click="clearSelection"
                                class="text-xs text-slate-600"
                            >
                                清除
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <!-- 待出貨分頁：批次出貨 -->
                            <button
                                v-if="activeTab === 'ready_to_ship'"
                                @click="batchMarkShipped"
                                class="btn btn-primary flex-1"
                            >
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                出貨 ({{ selectedShipments.length }})
                            </button>

                            <!-- 已出貨分頁：批次存檔 -->
                            <button
                                v-if="activeTab === 'shipped'"
                                @click="batchArchive"
                                class="btn btn-secondary flex-1"
                            >
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                </svg>
                                存檔 ({{ selectedShipments.length }})
                            </button>

                            <!-- 批次匯出 Excel -->
                            <button
                                @click="batchExport"
                                class="flex-1 px-3 py-2 bg-green-500 text-white rounded-lg text-sm font-medium active:bg-green-600"
                            >
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Excel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 出貨單列表 -->
                <div class="p-4 md:p-6">
        <div v-if="loading" class="buygo-loading">
            <div class="buygo-loading-spinner"></div>
            <p>載入中...</p>
        </div>

        <div v-else-if="shipments.length === 0" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            <p class="mt-2 text-slate-600">
                {{ activeTab === 'ready_to_ship' ? '目前沒有待出貨' : activeTab === 'shipped' ? '目前沒有已出貨' : '目前沒有已存檔' }}
            </p>
        </div>

        <template v-else>
            <!-- 桌面版表格 -->
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>
                                <input
                                    type="checkbox"
                                    @change="toggleSelectAll"
                                    :checked="isAllSelected"
                                    class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary"
                                >
                            </th>
                            <th>出貨單號</th>
                            <th>日期</th>
                            <th>客戶</th>
                            <th>商品數量</th>
                            <th class="text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="shipment in shipments" :key="shipment.id">
                            <td>
                                <input
                                    type="checkbox"
                                    :value="shipment.id"
                                    v-model="selectedShipments"
                                    class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary"
                                >
                            </td>
                            <td>
                                {{ shipment.shipment_number }}
                            </td>
                            <td>
                                {{ formatDate(activeTab === 'ready_to_ship' ? shipment.created_at : shipment.shipped_at) }}
                            </td>
                            <td>
                                {{ shipment.customer_name }}
                            </td>
                            <td>
                                {{ shipment.total_quantity }} 件
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="viewDetail(shipment.id)"
                                        class="btn btn-secondary btn-sm"
                                    >
                                        查看
                                    </button>
                                    <!-- 待出貨頁面：出貨按鈕 -->
                                    <button
                                        v-if="activeTab === 'ready_to_ship'"
                                        @click="showMarkShippedConfirm(shipment)"
                                        :title="'點擊標記為已出貨'"
                                        class="btn btn-primary btn-sm"
                                    >
                                        出貨
                                    </button>
                                    <!-- 已出貨頁面：狀態指示器 (不可點擊) -->
                                    <button
                                        v-if="activeTab === 'shipped'"
                                        disabled
                                        class="px-4 py-2 bg-green-50 text-green-600 rounded-lg cursor-default opacity-80 font-medium text-sm"
                                    >
                                        已出貨 ✓
                                    </button>
                                    <!-- 存檔區：存檔按鈕 -->
                                    <button
                                        v-if="activeTab === 'archived'"
                                        class="px-4 py-2 bg-slate-100 text-slate-500 rounded-lg cursor-default opacity-60 font-medium text-sm"
                                        disabled
                                    >
                                        已存檔
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 手機版卡片 -->
            <div class="card-list">
                <div v-for="shipment in shipments" :key="shipment.id" class="card">
                    <div class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            :value="shipment.id"
                            v-model="selectedShipments"
                            class="mt-1 w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary"
                        >
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="card-title">{{ shipment.shipment_number }}</h3>
                                    <p class="card-subtitle">{{ shipment.customer_name }}</p>
                                </div>
                                <span :class="[
                                    'status-tag',
                                    activeTab === 'ready_to_ship' ? 'status-tag-warning' :
                                    activeTab === 'shipped' ? 'status-tag-success' :
                                    'status-tag-neutral'
                                ]">
                                    {{ activeTab === 'ready_to_ship' ? '待出貨' : activeTab === 'shipped' ? '已出貨' : '已存檔' }}
                                </span>
                            </div>
                            <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
                                <span>{{ shipment.total_quantity }} 件</span>
                                <span>{{ formatDate(activeTab === 'ready_to_ship' ? shipment.created_at : shipment.shipped_at) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 border-t border-slate-200 p-2" style="margin: 1rem -1rem -1rem -1rem;">
                        <button
                            @click="viewDetail(shipment.id)"
                            class="btn btn-secondary flex-1 py-3 flex items-center justify-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <span class="text-xs font-bold">查看</span>
                        </button>
                        <!-- 待出貨頁面：出貨按鈕 -->
                        <button
                            v-if="activeTab === 'ready_to_ship'"
                            @click="showMarkShippedConfirm(shipment)"
                            class="btn btn-primary flex-1 py-3 flex items-center justify-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span class="text-xs font-bold">出貨</span>
                        </button>
                        <!-- 已出貨頁面：狀態指示器 (不可點擊) -->
                        <button
                            v-if="activeTab === 'shipped'"
                            disabled
                            class="flex-1 py-3 flex items-center justify-center gap-1.5 text-green-600 bg-green-50 cursor-default opacity-80"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            <span class="text-xs font-bold">已出貨 ✓</span>
                        </button>
                        <!-- 存檔區：狀態指示器 -->
                        <button
                            v-if="activeTab === 'archived'"
                            disabled
                            class="flex-1 py-3 flex items-center justify-center gap-1.5 text-slate-400 bg-slate-50 cursor-default opacity-60"
                        >
                            <span class="text-xs font-bold">已存檔</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
            </div><!-- Tabs 卡片結束 -->

    <!-- 分頁控制 -->
    <div v-if="totalShipments > 0" class="mt-6">
        <div class="pagination-container">
            <div class="pagination-info">
                顯示 <span class="font-medium">{{ (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ Math.min(currentPage * perPage, totalShipments) }}</span> 筆，共 <span class="font-medium">{{ totalShipments }}</span> 筆
            </div>
            <div class="pagination-controls">
                <select v-model.number="perPage" @change="changePerPage" class="pagination-select">
                    <option :value="5">5 筆</option>
                    <option :value="10">10 筆</option>
                    <option :value="20">20 筆</option>
                    <option :value="50">50 筆</option>
                </select>
                <nav class="pagination-nav" aria-label="Pagination">
                    <button @click="previousPage" :disabled="currentPage === 1" class="pagination-button first">
                        <span class="sr-only">上一頁</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <button v-for="p in visiblePages" :key="p" @click="goToPage(p)" :class="['pagination-button page', { 'active': p === currentPage }]">
                        {{ p }}
                    </button>
                    <button @click="nextPage" :disabled="currentPage >= totalPages" class="pagination-button last">
                        <span class="sr-only">下一頁</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>
    </div>
    <!-- 結束：列表檢視 -->

    <!-- 詳情檢視 -->
    <div v-show="currentView !== 'list'" class="absolute inset-0 bg-slate-50 z-30 overflow-y-auto w-full" style="min-height: 100vh;">
        <!-- Sticky Header -->
        <div class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 md:px-6 py-3 md:py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 md:gap-4 overflow-hidden">
                <button @click="navigateTo('list')" class="p-2 -ml-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-full transition-colors flex items-center gap-1 group shrink-0">
                    <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span class="text-sm font-medium">返回</span>
                </button>
                <div class="h-5 w-px bg-slate-200 hidden md:block"></div>
                <div class="truncate">
                    <h2 class="text-base md:text-xl font-bold text-slate-900 truncate">
                        出貨明細 - {{ detailModal.shipment?.shipment_number }}
                    </h2>
                </div>
            </div>
            <div class="flex gap-2 shrink-0">
                <button
                    @click="exportShipment(detailModal.shipment?.id)"
                    class="px-3 py-1.5 md:px-4 md:py-2 bg-slate-100 text-slate-900 rounded-lg hover:bg-slate-200 transition text-xs md:text-sm font-medium"
                >
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Excel
                </button>
                <button
                    @click="printDetail"
                    class="btn btn-primary btn-sm md:btn"
                >
                    列印
                </button>
                <button @click="navigateTo('list')" class="btn btn-secondary btn-sm md:btn">
                    關閉
                </button>
            </div>
        </div>

        <!-- 詳情內容 -->
        <div class="max-w-4xl mx-auto p-4 md:p-6 space-y-6 md:space-y-8">
            <!-- 客戶資訊 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <h4 class="text-sm font-bold text-slate-900 mb-4 border-l-4 border-orange-500 pl-3">客戶資訊</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">姓名</span>
                        <span class="text-sm text-slate-900 font-medium">{{ detailModal.shipment?.customer_name || '-' }}</span>
                    </div>
                    <div class="flex" v-if="detailModal.shipment?.line_display_name">
                        <span class="text-sm text-slate-600 w-20 shrink-0">LINE 名稱</span>
                        <span class="text-sm text-slate-900">{{ detailModal.shipment?.line_display_name }}</span>
                    </div>
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">電話</span>
                        <span class="text-sm text-slate-900">{{ detailModal.shipment?.customer_phone || '-' }}</span>
                    </div>
                    <div class="flex" v-if="detailModal.shipment?.taiwan_id_number">
                        <span class="text-sm text-slate-600 w-20 shrink-0">身分證字號</span>
                        <span class="text-sm text-slate-900 font-mono">{{ detailModal.shipment?.taiwan_id_number }}</span>
                    </div>
                    <div class="flex md:col-span-2">
                        <span class="text-sm text-slate-600 w-20 shrink-0">地址</span>
                        <span class="text-sm text-slate-900">{{ detailModal.shipment?.customer_address || '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- 商品明細 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-4 md:p-6 border-b border-slate-200">
                    <h4 class="text-sm font-bold text-slate-900 border-l-4 border-orange-500 pl-3">商品明細</h4>
                </div>
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">商品名稱</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">數量</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">單價</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">小計</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-if="!detailModal.items || detailModal.items.length === 0">
                            <td colspan="4" class="px-4 py-8 text-sm text-slate-500 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <span>尚無商品資料</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="item in detailModal.items" :key="item.id" class="hover:bg-slate-50">
                            <td class="px-4 py-4 text-sm text-slate-900">{{ item.product_name }}</td>
                            <td class="px-4 py-4 text-sm text-slate-900 text-right">{{ item.quantity }}</td>
                            <td class="px-4 py-4 text-sm text-slate-900 text-right">{{ formatPrice(item.price) }}</td>
                            <td class="px-4 py-4 text-sm text-slate-900 text-right font-medium">{{ formatPrice(item.quantity * item.price) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-sm font-bold text-slate-900 text-right">總計</td>
                            <td class="px-4 py-4 text-sm font-bold text-orange-600 text-right">
                                {{ formatPrice(detailModal.total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 出貨資訊（只在已出貨/存檔狀態顯示） -->
            <div v-if="activeTab === 'shipped' || activeTab === 'archived'" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <h4 class="text-sm font-bold text-slate-900 mb-4 border-l-4 border-green-500 pl-3">出貨資訊</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">出貨日期</span>
                        <span class="text-sm text-slate-900">{{ formatDate(detailModal.shipment?.shipped_at) }}</span>
                    </div>
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">物流方式</span>
                        <span class="text-sm text-slate-900">{{ detailModal.shipment?.shipping_method || '-' }}</span>
                    </div>
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">追蹤號碼</span>
                        <span class="text-sm text-slate-900">{{ detailModal.shipment?.tracking_number || '-' }}</span>
                    </div>
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">預計送達</span>
                        <span class="text-sm text-slate-900">{{ detailModal.shipment?.estimated_delivery_at ? formatDate(detailModal.shipment.estimated_delivery_at) : '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- 詳情子分頁視圖結束 -->

    <!-- 標記出貨子頁面 -->
    <div v-show="currentView === 'shipment-mark'" class="absolute inset-0 bg-slate-50 z-30 overflow-y-auto w-full" style="min-height: 100vh;">
        <!-- Sticky Header -->
        <div class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 md:px-6 py-3 md:py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 md:gap-4 overflow-hidden">
                <button @click="navigateTo('list')" class="p-2 -ml-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-full transition-colors flex items-center gap-1 group shrink-0">
                    <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span class="text-sm font-medium">返回</span>
                </button>
                <div class="h-5 w-px bg-slate-200 hidden md:block"></div>
                <div class="truncate">
                    <h2 class="text-base md:text-xl font-bold text-slate-900 truncate">
                        標記出貨 - {{ markShippedData.shipment?.shipment_number }}
                    </h2>
                </div>
            </div>
        </div>

        <!-- 標記出貨內容 -->
        <div class="max-w-4xl mx-auto p-4 md:p-6 space-y-6 md:space-y-8">
            <!-- 客戶資訊 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <h4 class="text-sm font-bold text-slate-900 mb-4 border-l-4 border-orange-500 pl-3">客戶資訊</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">姓名</span>
                        <span class="text-sm text-slate-900 font-medium">{{ markShippedData.shipment?.customer_name || '-' }}</span>
                    </div>
                    <div class="flex" v-if="markShippedData.shipment?.line_display_name">
                        <span class="text-sm text-slate-600 w-20 shrink-0">LINE 名稱</span>
                        <span class="text-sm text-slate-900">{{ markShippedData.shipment?.line_display_name }}</span>
                    </div>
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">電話</span>
                        <span class="text-sm text-slate-900">{{ markShippedData.shipment?.customer_phone || '-' }}</span>
                    </div>
                    <div class="flex" v-if="markShippedData.shipment?.taiwan_id_number">
                        <span class="text-sm text-slate-600 w-20 shrink-0">身分證字號</span>
                        <span class="text-sm text-slate-900 font-mono">{{ markShippedData.shipment?.taiwan_id_number }}</span>
                    </div>
                    <div class="flex md:col-span-2">
                        <span class="text-sm text-slate-600 w-20 shrink-0">地址</span>
                        <span class="text-sm text-slate-900">{{ markShippedData.shipment?.customer_address || '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- 商品明細 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-4 md:p-6 border-b border-slate-200">
                    <h4 class="text-sm font-bold text-slate-900 border-l-4 border-orange-500 pl-3">商品明細</h4>
                </div>
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">商品名稱</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">數量</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">單價</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">小計</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-if="!markShippedData.items || markShippedData.items.length === 0">
                            <td colspan="4" class="px-4 py-8 text-sm text-slate-500 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <span>載入中...</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="item in markShippedData.items" :key="item.id" class="hover:bg-slate-50">
                            <td class="px-4 py-4 text-sm text-slate-900">{{ item.product_name }}</td>
                            <td class="px-4 py-4 text-sm text-slate-900 text-right">{{ item.quantity }}</td>
                            <td class="px-4 py-4 text-sm text-slate-900 text-right">{{ formatPrice(item.price) }}</td>
                            <td class="px-4 py-4 text-sm text-slate-900 text-right font-medium">{{ formatPrice(item.quantity * item.price) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-sm font-bold text-slate-900 text-right">總計</td>
                            <td class="px-4 py-4 text-sm font-bold text-orange-600 text-right">
                                {{ formatPrice(markShippedData.total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 出貨設定 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <h4 class="text-sm font-bold text-slate-900 mb-4 border-l-4 border-green-500 pl-3">出貨設定</h4>
                <div class="space-y-4">
                    <!-- 出貨時間（自動填入，唯讀） -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">📦 出貨時間</label>
                        <input
                            type="text"
                            :value="getCurrentDateTime()"
                            readonly
                            class="w-full md:w-64 px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-600 text-sm cursor-not-allowed"
                        />
                        <p class="text-xs text-slate-500 mt-2">系統自動填入（確認出貨時的當下時間）</p>
                    </div>

                    <!-- 到貨時間（手動選擇） -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">📅 到貨時間（選填）</label>
                        <input
                            type="date"
                            v-model="markShippedData.estimated_delivery_date"
                            class="w-full md:w-64 px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none text-sm"
                            :min="getTodayDate()"
                        />
                        <p class="text-xs text-slate-500 mt-2">買家預計收貨日期，會顯示在出貨通知中</p>
                    </div>

                    <!-- 物流方式（下拉選單） -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">🚚 物流方式（選填）</label>
                        <select
                            v-model="markShippedData.shipping_method"
                            class="w-full md:w-64 px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none text-sm bg-white"
                        >
                            <option value="">請選擇物流方式</option>
                            <option value="易利">易利</option>
                            <option value="千森">千森</option>
                            <option value="OMI">OMI</option>
                            <option value="多賀">多賀</option>
                            <option value="賀來">賀來</option>
                            <option value="神奈川">神奈川</option>
                            <option value="新日本">新日本</option>
                            <option value="EMS">EMS</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-2">請選擇使用的物流公司</p>
                    </div>
                </div>
            </div>

            <!-- 操作按鈕 -->
            <div class="flex justify-end gap-3 pb-8">
                <button
                    @click="navigateTo('list')"
                    class="btn btn-secondary"
                >
                    取消
                </button>
                <button
                    @click="confirmMarkShipped"
                    class="btn btn-primary"
                    :disabled="markShippedData.loading"
                >
                    <span v-if="markShippedData.loading">處理中...</span>
                    <span v-else>確認出貨</span>
                </button>
            </div>
        </div>
    </div><!-- 標記出貨子頁面結束 -->

    <!-- 確認 Modal -->
    <div
        v-if="confirmModal.show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
        @click.self="closeConfirmModal"
    >
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">{{ confirmModal.title }}</h3>
                <p class="text-slate-600 mb-6">{{ confirmModal.message }}</p>
                <div class="flex justify-end gap-3">
                    <button
                        @click="closeConfirmModal"
                        class="btn btn-secondary"
                    >
                        取消
                    </button>
                    <button
                        @click="handleConfirm"
                        class="btn btn-primary"
                    >
                        確認
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>
    <!-- 結束：內容區域 -->

    <!-- Toast 通知 -->
    <div
        v-if="toastMessage.show"
        class="fixed top-4 right-4 z-50 animate-slide-in"
    >
        <div :class="[
            'px-6 py-4 rounded-lg shadow-lg flex items-center gap-3',
            toastMessage.type === 'success' ? 'bg-green-500 text-white' :
            toastMessage.type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        ]">
            <svg v-if="toastMessage.type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span class="font-medium">{{ toastMessage.message }}</span>
        </div>
    </div>

    </main>
</div>
HTML;

// Vue Component
?>

<!-- Shipment Details Page Template -->
<script type="text/x-template" id="shipment-details-page-template">
    <?php echo $shipment_details_template; ?>
</script>

<!-- Shipment Details Page Component -->
<script>
window.buygoWpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
</script>
<script src="<?php echo esc_url(plugins_url('js/components/ShipmentDetailsPage.js', dirname(__FILE__))); ?>"></script>

