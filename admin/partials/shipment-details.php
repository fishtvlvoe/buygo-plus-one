<?php
/**
 * 出貨明細頁面
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// HTML Template
$shipment_details_template = <<<'HTML'
<div class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
    <!-- Main Content -->
    <main class="flex flex-col min-w-0 relative bg-slate-50 min-h-screen">

    <!-- ============================================ -->
    <!-- 頁首部分（在 v-show 外面，列表時顯示） -->
    <!-- ============================================ -->
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 shrink-0 z-40 sticky top-0 md:static">
        <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
            <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0">
                <h1 class="text-xl font-bold text-slate-900 leading-tight truncate">出貨</h1>
                <nav class="hidden md:flex text-[10px] md:text-xs text-slate-500 gap-1 items-center truncate">
                    首頁 <span class="text-slate-300">/</span> 出貨管理
                </nav>
            </div>
        </div>

        <!-- Right Actions -->
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            <!-- Desktop Search -->
            <div class="relative hidden sm:block w-32 md:w-48 lg:w-64 transition-all duration-300">
                <input type="text" placeholder="全域搜尋..." v-model="globalSearchQuery" @input="handleGlobalSearch"
                    class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full transition-all">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>

            <!-- Notification -->
            <button class="p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100 relative">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            </button>
        </div>
    </header>
    <!-- 結束：頁首部分 -->

    <!-- ============================================ -->
    <!-- 內容區域 -->
    <!-- ============================================ -->
    <div class="flex-1 overflow-auto bg-slate-50/50 relative">

        <!-- 列表檢視 -->
        <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6 w-full max-w-7xl mx-auto space-y-4 md:space-y-6">

            <!-- Smart Search Box（頁面搜尋框） -->
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
            />

            <!-- 分頁 Tabs -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="flex gap-8 px-6 border-b border-slate-200">
                    <button
                        @click="activeTab = 'ready_to_ship'"
                        :class="activeTab === 'ready_to_ship' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition"
                    >
                        待出貨
                        <span v-if="stats.ready_to_ship > 0" class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-600 rounded-full text-xs">
                            {{ stats.ready_to_ship }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'shipped'"
                        :class="activeTab === 'shipped' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition"
                    >
                        已出貨
                        <span v-if="stats.shipped > 0" class="ml-2 px-2 py-0.5 bg-green-100 text-green-600 rounded-full text-xs">
                            {{ stats.shipped }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'archived'"
                        :class="activeTab === 'archived' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition"
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
                                class="buygo-btn buygo-btn-accent"
                            >
                                批次標記已出貨（{{ selectedShipments.length }}）
                            </button>

                            <!-- 已出貨分頁：批次移至存檔 -->
                            <button
                                v-if="activeTab === 'shipped'"
                                @click="batchArchive"
                                class="buygo-btn buygo-btn-secondary"
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
                                class="flex-1 px-3 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium active:bg-blue-600"
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
                                class="flex-1 px-3 py-2 bg-slate-600 text-white rounded-lg text-sm font-medium active:bg-slate-700"
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
            <div class="hidden md:block buygo-card overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 text-left">
                                <input
                                    type="checkbox"
                                    @change="toggleSelectAll"
                                    :checked="isAllSelected"
                                    class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary"
                                >
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">出貨單號</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">日期</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">客戶</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">商品數量</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-100">
                        <tr v-for="shipment in shipments" :key="shipment.id" class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input
                                    type="checkbox"
                                    :value="shipment.id"
                                    v-model="selectedShipments"
                                    class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary"
                                >
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                {{ shipment.shipment_number }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                {{ formatDate(activeTab === 'ready_to_ship' ? shipment.created_at : shipment.shipped_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                {{ shipment.customer_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                {{ shipment.total_quantity }} 件
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="viewDetail(shipment.id)"
                                        class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition font-medium text-sm"
                                    >
                                        查看
                                    </button>
                                    <!-- 待出貨頁面：出貨按鈕 (iOS 風格) -->
                                    <button
                                        v-if="activeTab === 'ready_to_ship'"
                                        @click="showMarkShippedConfirm(shipment)"
                                        :title="'點擊標記為已出貨'"
                                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 hover:shadow-md transition-all duration-200 font-medium text-sm active:scale-95"
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
            <div class="md:hidden space-y-4">
                <div v-for="shipment in shipments" :key="shipment.id" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4">
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
                                        <h3 class="text-sm font-bold text-slate-900">{{ shipment.shipment_number }}</h3>
                                        <p class="text-xs text-slate-500 mt-0.5">{{ shipment.customer_name }}</p>
                                    </div>
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-medium rounded-full',
                                        activeTab === 'ready_to_ship' ? 'bg-orange-100 text-orange-600' :
                                        activeTab === 'shipped' ? 'bg-green-100 text-green-600' :
                                        'bg-slate-100 text-slate-600'
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
                    </div>
                    <div class="grid grid-cols-2 border-t border-slate-200 divide-x divide-slate-200">
                        <button
                            @click="viewDetail(shipment.id)"
                            class="py-3 flex items-center justify-center gap-1.5 text-slate-600 hover:bg-slate-50 bg-white transition active:bg-slate-100"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <span class="text-xs font-bold">查看</span>
                        </button>
                        <!-- 待出貨頁面：出貨按鈕 (iOS 風格) -->
                        <button
                            v-if="activeTab === 'ready_to_ship'"
                            @click="showMarkShippedConfirm(shipment)"
                            class="py-3 flex items-center justify-center gap-1.5 text-blue-600 hover:bg-blue-50 bg-white transition active:bg-blue-100"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span class="text-xs font-bold">出貨</span>
                        </button>
                        <!-- 已出貨頁面：狀態指示器 (不可點擊) -->
                        <button
                            v-if="activeTab === 'shipped'"
                            disabled
                            class="py-3 flex items-center justify-center gap-1.5 text-green-600 bg-green-50 cursor-default opacity-80"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            <span class="text-xs font-bold">已出貨 ✓</span>
                        </button>
                        <!-- 存檔區：狀態指示器 -->
                        <button
                            v-if="activeTab === 'archived'"
                            disabled
                            class="py-3 flex items-center justify-center gap-1.5 text-slate-400 bg-slate-50 cursor-default opacity-60"
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
    <div v-if="totalShipments > 0" class="px-4 md:px-6 pb-6">
        <div class="flex flex-col sm:flex-row items-center justify-between bg-white px-4 py-3 border border-slate-200 rounded-xl shadow-sm gap-3">
            <div class="text-sm text-slate-700 text-center sm:text-left">
                顯示 <span class="font-medium">{{ (currentPage - 1) * perPage + 1 }}</span> 到 <span class="font-medium">{{ Math.min(currentPage * perPage, totalShipments) }}</span> 筆，共 <span class="font-medium">{{ totalShipments }}</span> 筆
            </div>
            <div class="flex items-center gap-3">
                <select v-model.number="perPage" @change="changePerPage" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option :value="5">5 筆</option>
                    <option :value="10">10 筆</option>
                    <option :value="20">20 筆</option>
                    <option :value="50">50 筆</option>
                </select>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button @click="previousPage" :disabled="currentPage === 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="sr-only">上一頁</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <button v-for="p in visiblePages" :key="p" @click="goToPage(p)" :class="[p === currentPage ? 'z-10 bg-blue-50 border-primary text-primary' : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50', 'relative inline-flex items-center px-4 py-2 border text-sm font-medium']">
                        {{ p }}
                    </button>
                    <button @click="nextPage" :disabled="currentPage >= totalPages" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
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
                    class="px-3 py-1.5 md:px-4 md:py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition text-xs md:text-sm font-medium"
                >
                    列印
                </button>
                <button @click="navigateTo('list')" class="px-3 py-1.5 md:px-4 md:py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition text-xs md:text-sm font-medium">
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
                    <div class="flex">
                        <span class="text-sm text-slate-600 w-20 shrink-0">電話</span>
                        <span class="text-sm text-slate-900">{{ detailModal.shipment?.customer_phone || '-' }}</span>
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
                </div>
            </div>
        </div>
    </div><!-- 子分頁視圖結束 -->

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
                        class="buygo-btn buygo-btn-secondary"
                    >
                        取消
                    </button>
                    <button
                        @click="handleConfirm"
                        class="buygo-btn buygo-btn-accent"
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

