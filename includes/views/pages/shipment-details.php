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
<div class="min-h-screen bg-slate-50 relative">
    <!-- 列表視圖 -->
    <div v-show="currentView === 'list'">
    <!-- 頁面標題 -->
    <div class="bg-white border-b border-slate-200 px-6 py-4 shadow-sm sticky top-0 z-30 md:static">
        <div class="pl-12 md:pl-0">
            <h1 class="text-xl font-bold text-slate-900">出貨</h1>
        </div>
    </div>

    <!-- 分頁 Tabs -->
    <div class="bg-white border-b border-slate-200">
        <div class="flex gap-8 px-6">
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

    <!-- 智慧搜尋框 -->
    <div class="px-6 py-4 border-b border-slate-200">
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
            <p class="mt-2 text-slate-600">目前沒有出貨單</p>
        </div>

        <template v-else>
            <!-- 桌面版表格 -->
            <div class="hidden md:block buygo-card overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
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
    </div><!-- 列表視圖結束 -->

    <!-- 子分頁視圖（詳情） -->
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
                    <thead class="bg-slate-50">
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
    
    <!-- 查看詳情 Modal -->
    <div 
        v-if="detailModal.show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
        @click.self="closeDetailModal"
    >
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- Modal 標題 -->
            <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900">
                    出貨明細 - {{ detailModal.shipment?.shipment_number }}
                </h3>
                <button 
                    @click="closeDetailModal"
                    class="text-slate-400 hover:text-slate-600"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Modal 內容 -->
            <div class="p-6">
                <!-- 客戶資訊 -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3">客戶資訊</h4>
                    <div class="bg-slate-50 rounded-lg p-4 space-y-2">
                        <div class="flex">
                            <span class="text-sm text-slate-600 w-20">姓名</span>
                            <span class="text-sm text-slate-900 font-medium">{{ detailModal.shipment?.customer_name || '-' }}</span>
                        </div>
                        <div class="flex">
                            <span class="text-sm text-slate-600 w-20">電話</span>
                            <span class="text-sm text-slate-900">{{ detailModal.shipment?.customer_phone || '-' }}</span>
                        </div>
                        <div class="flex">
                            <span class="text-sm text-slate-600 w-20">地址</span>
                            <span class="text-sm text-slate-900">{{ detailModal.shipment?.customer_address || '-' }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- 商品明細 -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3">商品明細</h4>
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500">商品名稱</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">數量</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">單價</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500">小計</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-200">
                                <tr v-if="!detailModal.items || detailModal.items.length === 0">
                                    <td colspan="4" class="px-4 py-6 text-sm text-slate-500 text-center">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                            <span>尚無商品資料</span>
                                            <span class="text-xs text-slate-400">此出貨單可能是手動建立或資料已遺失</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="item in detailModal.items" :key="item.id">
                                    <td class="px-4 py-3 text-sm text-slate-900">{{ item.product_name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-900 text-right">{{ item.quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-900 text-right">{{ formatPrice(item.price) }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-900 text-right">{{ formatPrice(item.quantity * item.price) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-50">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm font-semibold text-slate-900 text-right">總計</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900 text-right">
                                        {{ formatPrice(detailModal.total) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <!-- 出貨資訊 -->
                <div v-if="activeTab === 'shipped' || activeTab === 'archived'" class="mb-6">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3">出貨資訊</h4>
                    <div class="bg-slate-50 rounded-lg p-4 space-y-2">
                        <div class="flex">
                            <span class="text-sm text-slate-600 w-20">出貨日期</span>
                            <span class="text-sm text-slate-900">{{ formatDate(detailModal.shipment?.shipped_at) }}</span>
                        </div>
                        <div class="flex">
                            <span class="text-sm text-slate-600 w-20">物流方式</span>
                            <span class="text-sm text-slate-900">{{ detailModal.shipment?.shipping_method || '-' }}</span>
                        </div>
                        <div class="flex">
                            <span class="text-sm text-slate-600 w-20">追蹤號碼</span>
                            <span class="text-sm text-slate-900">{{ detailModal.shipment?.tracking_number || '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal 底部 -->
            <div class="sticky bottom-0 bg-slate-50 border-t border-slate-200 px-6 py-4 flex justify-end gap-3">
                <button
                    @click="closeDetailModal"
                    class="buygo-btn buygo-btn-secondary"
                >
                    關閉
                </button>
                <button
                    @click="exportShipment(detailModal.shipment?.id)"
                    class="px-4 py-2 bg-slate-100 text-slate-900 rounded-lg hover:bg-slate-200 transition font-medium"
                >
                    <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    匯出 Excel
                </button>
                <button
                    @click="printDetail"
                    class="buygo-btn buygo-btn-primary"
                >
                    列印收據
                </button>
            </div>
        </div>
    </div>
</div>
HTML;

// Vue Component
?>
<script>
const { ref, onMounted, watch } = Vue;

const ShipmentDetailsPageComponent = {
    name: 'ShipmentDetailsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: `<?php echo $shipment_details_template; ?>`,
    setup() {
        const { computed, watch } = Vue;

        // 使用 useCurrency Composable 處理幣別邏輯
        const { formatPrice, getCurrencySymbol, systemCurrency } = useCurrency();
        const activeTab = ref('ready_to_ship');
        const shipments = ref([]);
        const loading = ref(false);
        const stats = ref({ ready_to_ship: 0, shipped: 0, archived: 0 });

        // ============================================
        // 路由狀態（子分頁切換）
        // ============================================
        const currentView = ref('list');  // 'list' | 'detail'
        const currentShipmentId = ref(null);
        
        // 勾選狀態
        const selectedShipments = ref([]);
        
        // Modal 狀態
        const confirmModal = ref({ show: false, title: '', message: '', onConfirm: null });
        const toastMessage = ref({ show: false, message: '', type: 'success' });
        
        // 詳情 Modal 狀態
        const detailModal = ref({
            show: false,
            shipment: null,
            items: [],
            total: 0
        });

        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalShipments = ref(0);

        // 搜尋狀態
        const searchQuery = ref(null);
        const searchFilter = ref(null);

        // 載入出貨單列表
        const loadShipments = async () => {
            loading.value = true;
            try {
                // 加入時間戳記強制繞過所有快取
                let url = `/wp-json/buygo-plus-one/v1/shipments?status=${activeTab.value}&page=${currentPage.value}&per_page=${perPage.value}&_t=${Date.now()}`;

                // 加入搜尋參數
                if (searchQuery.value) {
                    url += `&search=${encodeURIComponent(searchQuery.value)}`;
                }

                const response = await fetch(url, {
                    credentials: 'include',
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                const result = await response.json();

                if (result.success) {
                    shipments.value = result.data || [];
                    totalShipments.value = result.total || result.data.length;
                }
            } catch (err) {
                console.error('載入出貨單失敗:', err);
                showToast('載入失敗', 'error');
            } finally {
                loading.value = false;
            }
        };
        
        // 載入統計數據
        const loadStats = async () => {
            try {
                const statuses = ['ready_to_ship', 'shipped', 'archived'];
                for (const status of statuses) {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments?status=${status}&per_page=1`, {
                        credentials: 'include'
                    });
                    const result = await response.json();
                    if (result.success && result.total !== undefined) {
                        stats.value[status] = result.total;
                    }
                }
            } catch (err) {
                console.error('載入統計失敗:', err);
            }
        };
        
        // 顯示標記已出貨確認對話框（含出貨單詳細資訊）
        const showMarkShippedConfirm = (shipment) => {
            const formattedDate = formatDate(shipment.created_at || shipment.shipped_at);
            const message = `
                出貨單號: ${shipment.shipment_number}
                客戶姓名: ${shipment.customer_name || '未知客戶'}
                商品數量: ${shipment.total_quantity || 0} 件
                出貨日期: ${formattedDate}

                ⚠️ 此操作無法撤銷
            `;

            showConfirm(
                '⚠️ 確認出貨',
                message.trim(),
                async () => {
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ shipment_ids: [shipment.id] })
                        });
                        const result = await response.json();

                        if (result.success) {
                            showToast('✓ 出貨單已標記為已出貨', 'success');
                            selectedShipments.value = [];
                            await loadShipments();
                            await loadStats();
                        } else {
                            showToast('✗ 操作失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        showToast('✗ 操作失敗，請稍後再試', 'error');
                    }
                }
            );
        };

        // 標記已出貨（保留原有函數供批次操作使用）
        const markShipped = (shipmentId) => {
            // 從列表中找到對應的出貨單
            const shipment = shipments.value.find(s => s.id === shipmentId);
            if (shipment) {
                showMarkShippedConfirm(shipment);
            }
        };
        
        // 移至存檔
        const archiveShipment = (shipmentId) => {
            showConfirm(
                '確認移至存檔',
                '確定要將此出貨單移至存檔區嗎？',
                async () => {
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/archive`, {
                            method: 'POST',
                            credentials: 'include'
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast('已移至存檔區', 'success');
                            selectedShipments.value = [];
                            await loadShipments();
                            await loadStats();
                        } else {
                            showToast('移至存檔失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        showToast('移至存檔失敗', 'error');
                    }
                }
            );
        };
        
        // 是否全選
        const isAllSelected = computed(() => {
            return shipments.value.length > 0 && 
                   selectedShipments.value.length === shipments.value.length;
        });

        // 切換全選
        const toggleSelectAll = (event) => {
            if (event.target.checked) {
                selectedShipments.value = shipments.value.map(s => s.id);
            } else {
                selectedShipments.value = [];
            }
        };

        // 清除勾選
        const clearSelection = () => {
            selectedShipments.value = [];
        };

        // 分頁處理函數
        const changePerPage = () => {
            currentPage.value = 1; // 重置到第一頁
            loadShipments();
        };

        const previousPage = () => {
            if (currentPage.value > 1) {
                currentPage.value--;
                loadShipments();
            }
        };

        const nextPage = () => {
            if (currentPage.value < totalPages.value) {
                currentPage.value++;
                loadShipments();
            }
        };

        // 計算屬性：總頁數
        const totalPages = computed(() => {
            return Math.ceil(totalShipments.value / perPage.value);
        });

        // 計算可見頁碼
        const visiblePages = computed(() => {
            const pages = [];
            const maxPages = Math.min(5, totalPages.value);
            let startPage = Math.max(1, currentPage.value - Math.floor(maxPages / 2));
            let endPage = startPage + maxPages - 1;

            if (endPage > totalPages.value) {
                endPage = totalPages.value;
                startPage = Math.max(1, endPage - maxPages + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                pages.push(i);
            }
            return pages;
        });

        // 跳轉到指定頁
        const goToPage = (page) => {
            if (page < 1 || page > totalPages.value) return;
            currentPage.value = page;
            loadShipments();
        };

        // 批次標記已出貨
        const batchMarkShipped = () => {
            if (selectedShipments.value.length === 0) {
                showToast('請先選擇出貨單', 'error');
                return;
            }
            
            showConfirm(
                '確認批次標記已出貨',
                `確定要將 ${selectedShipments.value.length} 個出貨單標記為已出貨嗎？`,
                async () => {
                    try {
                        const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ shipment_ids: selectedShipments.value })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast('批次標記成功！', 'success');
                            selectedShipments.value = [];
                            await loadShipments();
                            await loadStats();
                        } else {
                            showToast('批次標記失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('批次標記失敗:', err);
                        showToast('批次標記失敗', 'error');
                    }
                }
            );
        };

        // 批次移至存檔
        const batchArchive = () => {
            if (selectedShipments.value.length === 0) {
                showToast('請先選擇出貨單', 'error');
                return;
            }
            
            showConfirm(
                '確認批次移至存檔',
                `確定要將 ${selectedShipments.value.length} 個出貨單移至存檔區嗎？`,
                async () => {
                    try {
                        const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/batch-archive', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ shipment_ids: selectedShipments.value })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast('批次移至存檔成功！', 'success');
                            selectedShipments.value = [];
                            await loadShipments();
                            await loadStats();
                        } else {
                            showToast('批次移至存檔失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('批次移至存檔失敗:', err);
                        showToast('批次移至存檔失敗', 'error');
                    }
                }
            );
        };

        // 匯出單張出貨單
        const exportShipment = async (shipmentId) => {
            if (!shipmentId) {
                showToast('出貨單 ID 無效', 'error');
                return;
            }

            try {
                // 建立 URL（使用 GET 參數）
                const url = `/wp-json/buygo-plus-one/v1/shipments/export?shipment_ids=${shipmentId}`;

                // 直接開啟 URL（瀏覽器會自動下載檔案）
                window.location.href = url;

                showToast('正在匯出...', 'info');
            } catch (err) {
                console.error('匯出失敗:', err);
                showToast('匯出失敗：' + err.message, 'error');
            }
        };

        // 批次匯出（參考舊外掛，使用 GET 請求直接開啟 URL）
        const batchExport = () => {
            if (selectedShipments.value.length === 0) {
                showToast('請先選擇出貨單', 'error');
                return;
            }

            try {
                // 建立 URL（使用 GET 參數傳遞 shipment_ids）
                const ids = selectedShipments.value.join(',');
                const url = `/wp-json/buygo-plus-one/v1/shipments/export?shipment_ids=${ids}`;

                // 直接開啟 URL（瀏覽器會自動下載檔案）
                window.location.href = url;

                showToast(`正在匯出 ${selectedShipments.value.length} 個出貨單...`, 'info');
            } catch (err) {
                console.error('批次匯出失敗:', err);
                showToast('批次匯出失敗：' + err.message, 'error');
            }
        };

        // 查看詳情（改為使用子分頁）
        const viewDetail = (shipmentId) => {
            openShipmentDetail(shipmentId);
        };

        // 關閉詳情（改為使用子分頁）
        const closeDetailModal = () => {
            closeShipmentDetail();
        };

        // ============================================
        // 路由邏輯（子分頁切換）
        // ============================================

        // 檢查 URL 參數
        const checkUrlParams = () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view === 'detail' && id) {
                currentView.value = 'detail';
                currentShipmentId.value = id;
                loadShipmentDetail(id);
            } else {
                currentView.value = 'list';
                currentShipmentId.value = null;
            }
        };

        // 導航函數
        const navigateTo = (view, shipmentId = null, updateUrl = true) => {
            currentView.value = view;

            if (shipmentId) {
                currentShipmentId.value = shipmentId;
                loadShipmentDetail(shipmentId);

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo(view, shipmentId);
                }
            } else {
                currentShipmentId.value = null;
                detailModal.value = { show: false, shipment: null, items: [], total: 0 };

                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        // 載入出貨單詳情（供子分頁使用）
        const loadShipmentDetail = async (shipmentId) => {
            try {
                const url = `/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/detail`;
                const response = await fetch(url, {
                    credentials: 'include',
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                const result = await response.json();

                if (result.success) {
                    detailModal.value = {
                        show: true,
                        shipment: result.data.shipment,
                        items: result.data.items,
                        total: result.data.items.reduce((sum, item) => sum + (item.quantity * item.price), 0)
                    };
                } else {
                    showToast('載入詳情失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('載入詳情失敗:', err);
                showToast('載入詳情失敗', 'error');
            }
        };

        // 開啟出貨單詳情
        const openShipmentDetail = (shipmentId) => {
            navigateTo('detail', shipmentId);
        };

        // 關閉出貨單詳情
        const closeShipmentDetail = () => {
            navigateTo('list');
        };

        // 列印收據
        const printDetail = () => {
            window.print();
        };
        
        // Modal 控制
        const showConfirm = (title, message, onConfirm) => {
            confirmModal.value = { show: true, title, message, onConfirm };
        };
        
        const closeConfirmModal = () => {
            confirmModal.value = { show: false, title: '', message: '', onConfirm: null };
        };
        
        const handleConfirm = () => {
            if (confirmModal.value.onConfirm) {
                confirmModal.value.onConfirm();
            }
            closeConfirmModal();
        };
        
        const showToast = (message, type = 'success') => {
            toastMessage.value = { show: true, message, type };
            setTimeout(() => {
                toastMessage.value.show = false;
            }, 3000);
        };
        
        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`;
        };

        // 智慧搜尋處理
        const handleSearchInput = (query) => {
            // 本地搜尋處理函數（輸入時過濾列表）
            searchQuery.value = query;
            currentPage.value = 1;  // 重置到第一頁
            loadShipments();
        };

        const handleSearchSelect = (item) => {
            // 搜尋選中項目後的處理
            if (item && item.id) {
                viewDetail(item.id);
            }
        };

        const handleSearchClear = () => {
            // 清除搜尋後重新載入列表
            searchQuery.value = null;
            currentPage.value = 1;
            loadShipments();
        };

        // 監聽分頁切換，清除勾選
        watch(() => activeTab.value, () => {
            selectedShipments.value = [];
            loadShipments();
        });
        
        onMounted(() => {
            loadShipments();
            loadStats();

            // 檢查 URL 參數（支援直接訪問詳情頁）
            checkUrlParams();

            // 監聽瀏覽器上一頁/下一頁
            window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) {
                    loadShipments();
                    loadStats();
                }
            });

            // 監聽頁面可見性變化
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    loadShipments();
                    loadStats();
                }
            });
        });

        return {
            activeTab,
            shipments,
            loading,
            stats,
            selectedShipments,
            isAllSelected,
            confirmModal,
            toastMessage,
            detailModal,
            showMarkShippedConfirm,
            markShipped,
            archiveShipment,
            viewDetail,
            closeConfirmModal,
            handleConfirm,
            formatDate,
            toggleSelectAll,
            clearSelection,
            batchMarkShipped,
            batchArchive,
            closeDetailModal,
            formatPrice,
            printDetail,
            getCurrencySymbol,
            systemCurrency,
            handleSearchInput,
            handleSearchSelect,
            handleSearchClear,
            showToast,
            // 匯出功能
            exportShipment,
            batchExport,
            // 分頁相關
            currentPage,
            perPage,
            totalShipments,
            totalPages,
            visiblePages,
            changePerPage,
            previousPage,
            nextPage,
            goToPage
        };
    }
};
</script>
