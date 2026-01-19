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
<div class="min-h-screen bg-slate-50">
    <!-- 頁面標題 -->
    <div class="bg-white border-b border-slate-200 px-6 py-4">
        <h1 class="text-2xl font-bold text-slate-900">出貨明細</h1>
        <p class="text-sm text-slate-500 mt-1">管理您的出貨單狀態</p>
    </div>
    
    <!-- 分頁 Tabs -->
    <div class="bg-white border-b border-slate-200">
        <div class="flex gap-8 px-6">
            <button 
                @click="activeTab = 'pending'"
                :class="activeTab === 'pending' ? 'border-orange-500 text-orange-600' : 'border-transparent text-slate-600 hover:text-slate-900'"
                class="py-4 px-1 border-b-2 font-medium text-sm transition"
            >
                待出貨 
                <span v-if="stats.pending > 0" class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-600 rounded-full text-xs">
                    {{ stats.pending }}
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
    <div v-if="selectedShipments.length > 0" class="bg-orange-50 border-b border-orange-200 px-6 py-3">
        <div class="flex items-center justify-between">
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
                    v-if="activeTab === 'pending'"
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
    <div class="p-6">
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
        
        <div v-else class="buygo-card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left">
                            <input 
                                type="checkbox" 
                                @change="toggleSelectAll"
                                :checked="isAllSelected"
                                class="w-4 h-4 text-orange-600 bg-gray-100 border-gray-300 rounded focus:ring-orange-500"
                            >
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">出貨單號</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">客戶</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">商品數量</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">日期</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <tr v-for="shipment in shipments" :key="shipment.id" class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input 
                                type="checkbox" 
                                :value="shipment.id"
                                v-model="selectedShipments"
                                class="w-4 h-4 text-orange-600 bg-gray-100 border-gray-300 rounded focus:ring-orange-500"
                            >
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                            {{ shipment.shipment_number }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                            {{ shipment.customer_name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                            {{ shipment.total_quantity }} 件
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                            {{ formatDate(activeTab === 'pending' ? shipment.created_at : shipment.shipped_at) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end gap-2">
                                <!-- 待出貨分頁：已出貨按鈕 -->
                                <button
                                    v-if="activeTab === 'pending'"
                                    @click="markShipped(shipment.id)"
                                    class="buygo-btn buygo-btn-accent buygo-btn-sm"
                                >
                                    已出貨
                                </button>

                                <!-- 已出貨分頁：存檔按鈕 -->
                                <button
                                    v-if="activeTab === 'shipped'"
                                    @click="archiveShipment(shipment.id)"
                                    class="buygo-btn buygo-btn-secondary buygo-btn-sm"
                                >
                                    存檔
                                </button>

                                <!-- 所有分頁：查看按鈕 -->
                                <button
                                    @click="viewDetail(shipment.id)"
                                    class="buygo-btn buygo-btn-primary buygo-btn-sm"
                                >
                                    查看
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 分頁控制 -->
    <div v-if="totalShipments > perPage" class="flex items-center justify-between mt-6 px-6 pb-6">
        <div class="text-sm text-slate-600">
            顯示 {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalShipments) }} 筆，共 {{ totalShipments }} 筆
        </div>
        <div class="flex items-center gap-2">
            <select v-model="perPage" @change="changePerPage" class="px-3 py-1 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                <option value="5">5 / 頁</option>
                <option value="10">10 / 頁</option>
                <option value="20">20 / 頁</option>
                <option value="50">50 / 頁</option>
            </select>
            <button @click="previousPage" :disabled="currentPage === 1" class="px-3 py-1 border border-slate-300 rounded-lg text-sm hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                上一頁
            </button>
            <button class="px-3 py-1 bg-orange-500 text-white rounded-lg text-sm font-medium">
                {{ currentPage }}
            </button>
            <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 border border-slate-300 rounded-lg text-sm hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                下一頁
            </button>
        </div>
    </div>

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
                                <tr v-for="item in detailModal.items" :key="item.id">
                                    <td class="px-4 py-3 text-sm text-slate-900">{{ item.product_name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-900 text-right">{{ item.quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-900 text-right">NT$ {{ formatPrice(item.price) }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-900 text-right">NT$ {{ formatPrice(item.quantity * item.price) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-50">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm font-semibold text-slate-900 text-right">總計</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900 text-right">
                                        NT$ {{ formatPrice(detailModal.total) }}
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
        const activeTab = ref('pending');
        const shipments = ref([]);
        const loading = ref(false);
        const stats = ref({ pending: 0, shipped: 0, archived: 0 });
        
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
                let url = `/wp-json/buygo-plus-one/v1/shipments?status=${activeTab.value}&page=${currentPage.value}&per_page=${perPage.value}`;

                // 加入搜尋參數
                if (searchQuery.value) {
                    url += `&search=${encodeURIComponent(searchQuery.value)}`;
                }

                const response = await fetch(url, {
                    credentials: 'include'
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
                const statuses = ['pending', 'shipped', 'archived'];
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
        
        // 標記已出貨
        const markShipped = (shipmentId) => {
            showConfirm(
                '確認標記已出貨',
                '確定要標記此出貨單為已出貨嗎？',
                async () => {
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ shipment_ids: [shipmentId] })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showToast('標記成功！', 'success');
                            selectedShipments.value = [];
                            await loadShipments();
                            await loadStats();
                        } else {
                            showToast('標記失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        showToast('標記失敗', 'error');
                    }
                }
            );
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

        // 全局搜尋處理函數
        const handleGlobalSearchInput = (query) => {
            // 全局搜索不需要在本頁面載入資料，因為會跳轉到對應頁面
            console.log('全局搜索輸入:', query);
        };

        const handleGlobalSearchSelect = (item) => {
            // 根據選擇的項目類型跳轉到對應頁面
            if (item.url) {
                window.location.href = item.url;
            } else {
                console.log('搜索項目沒有URL:', item);
            }
        };

        const handleGlobalSearchClear = () => {
            // 清除全局搜索
            console.log('清除全局搜索');
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

        // 查看詳情
        const viewDetail = async (shipmentId) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/detail`, {
                    credentials: 'include'
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

        // 關閉詳情 Modal
        const closeDetailModal = () => {
            detailModal.value = { show: false, shipment: null, items: [], total: 0 };
        };

        // 格式化價格
        const formatPrice = (price) => {
            return new Intl.NumberFormat('zh-TW').format(price);
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
        
        // 監聽分頁切換，清除勾選
        watch(() => activeTab.value, () => {
            selectedShipments.value = [];
            loadShipments();
        });
        
        onMounted(() => {
            loadShipments();
            loadStats();
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
            printDetail
        };
    }
};
</script>
