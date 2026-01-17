<?php
// 出貨管理頁面元件

$shipment_products_component_template = <<<'HTML'
<main class="min-h-screen bg-slate-50">
    <!-- 頁面標題 -->
    <div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 mb-1 font-title">出貨管理</h1>
                    <p class="text-sm text-slate-500">管理您的出貨單與出貨狀態</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- 新增出貨單按鈕 -->
                    <button 
                        @click="openCreateModal"
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-sm flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        新增出貨單
                    </button>
                </div>
            </div>
            
            <!-- 狀態篩選 -->
            <div class="flex items-center gap-2 mb-4">
                <button
                    v-for="filter in statusFilters"
                    :key="filter.value"
                    @click="setStatusFilter(filter.value)"
                    :class="currentStatusFilter === filter.value 
                        ? 'bg-primary text-white border-primary' 
                        : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                    class="px-4 py-2 border rounded-lg text-sm font-medium transition">
                    {{ filter.label }}
                </button>
            </div>
        </div>
    </div>

    <!-- 出貨單列表容器 -->
    <div class="p-6">
        <!-- 載入狀態 -->
        <div v-if="loading" class="text-center py-8">
            <p class="text-slate-600">載入中...</p>
        </div>
        
        <!-- 錯誤訊息 -->
        <div v-else-if="error" class="text-center py-8">
            <p class="text-red-600">{{ error }}</p>
            <button @click="loadShipments" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-sm">重新載入</button>
        </div>
        
        <!-- 出貨單列表 -->
        <div v-else>
            <!-- 批次操作工具列 -->
            <div v-if="selectedItems.length > 0" class="mb-4 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
                <div class="text-sm text-blue-700 font-medium">
                    已選擇 {{ selectedItems.length }} 個出貨單
                </div>
                <button 
                    @click="batchMarkShipped"
                    class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 font-medium transition shadow-sm">
                    批次標記為已出貨
                </button>
            </div>
            
            <!-- 桌面版表格 -->
            <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                <input type="checkbox" @change="toggleSelectAll" class="rounded border-slate-300">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">出貨單號</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">客戶名稱</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">商品清單</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">總數量</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">狀態</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">建立日期</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-for="shipment in shipments" :key="shipment.id" class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3">
                                <input type="checkbox" :value="shipment.id" v-model="selectedItems" class="rounded border-slate-300">
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ shipment.shipment_number }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ shipment.customer_name || '未知客戶' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <div class="flex items-center gap-2">
                                    <span 
                                        @click="toggleShipmentExpand(shipment.id)"
                                        class="cursor-pointer hover:text-primary transition truncate max-w-xs"
                                        :title="formatItemsDisplay(shipment, 999)"
                                    >
                                        {{ formatItemsDisplay(shipment) }}
                                    </span>
                                    <button 
                                        v-if="shipment.items && shipment.items.length > 0"
                                        @click="toggleShipmentExpand(shipment.id)"
                                        class="text-slate-400 hover:text-primary transition flex-shrink-0"
                                    >
                                        <svg 
                                            class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-180': isShipmentExpanded(shipment.id) }"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                </div>
                                <!-- 展開的商品詳細列表 -->
                                <div 
                                    v-if="isShipmentExpanded(shipment.id) && shipment.items && shipment.items.length > 0"
                                    class="mt-2 pt-2 border-t border-slate-200 space-y-2"
                                >
                                    <div 
                                        v-for="item in shipment.items" 
                                        :key="item.id"
                                        class="flex items-center gap-3 text-xs"
                                    >
                                        <img 
                                            v-if="item.product_image"
                                            :src="item.product_image" 
                                            :alt="item.product_name"
                                            class="w-10 h-10 object-cover rounded border border-slate-200"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-slate-900 truncate">{{ item.product_name }}</div>
                                            <div class="text-slate-500">
                                                訂單：{{ item.order_invoice_no }} × {{ item.quantity }} 個
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ shipment.total_quantity || 0 }}</td>
                            <td class="px-4 py-3">
                                <span :class="getStatusClass(shipment.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                    {{ getStatusText(shipment.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ formatDate(shipment.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button 
                                        v-if="shipment.status === 'pending'"
                                        @click="markShipped(shipment.id)" 
                                        class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition shadow-sm">
                                        標記已出貨
                                    </button>
                                    <button 
                                        @click="viewShipmentDetail(shipment.id)" 
                                        class="text-primary hover:text-primary-dark text-sm font-medium">
                                        查看詳情
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 手機版卡片 -->
            <div class="md:hidden space-y-4">
                <div v-for="shipment in shipments" :key="shipment.id" class="bg-white border border-slate-200 rounded-xl p-4 mb-3">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="text-sm font-bold text-slate-900 mb-1">{{ shipment.shipment_number }}</div>
                            <div class="text-xs text-slate-500">{{ shipment.customer_name || '未知客戶' }}</div>
                        </div>
                        <input type="checkbox" :value="shipment.id" v-model="selectedItems" class="rounded border-slate-300">
                    </div>
                    
                    <div class="mb-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs text-slate-500">商品：</span>
                            <span 
                                @click="toggleShipmentExpand(shipment.id)"
                                class="font-medium text-slate-900 cursor-pointer hover:text-primary transition flex-1 truncate"
                                :title="formatItemsDisplay(shipment, 999)"
                            >
                                {{ formatItemsDisplay(shipment, 40) }}
                            </span>
                            <button 
                                v-if="shipment.items && shipment.items.length > 0"
                                @click="toggleShipmentExpand(shipment.id)"
                                class="text-slate-400 hover:text-primary transition flex-shrink-0"
                            >
                                <svg 
                                    class="w-3 h-3 transition-transform"
                                    :class="{ 'rotate-180': isShipmentExpanded(shipment.id) }"
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                        </div>
                        <!-- 展開的商品詳細列表 -->
                        <div 
                            v-if="isShipmentExpanded(shipment.id) && shipment.items && shipment.items.length > 0"
                            class="mt-2 pt-2 border-t border-slate-200 space-y-2"
                        >
                            <div 
                                v-for="item in shipment.items" 
                                :key="item.id"
                                class="flex items-center gap-2 text-xs bg-slate-50 p-2 rounded"
                            >
                                <img 
                                    v-if="item.product_image"
                                    :src="item.product_image" 
                                    :alt="item.product_name"
                                    class="w-8 h-8 object-cover rounded border border-slate-200"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-slate-900 truncate">{{ item.product_name }}</div>
                                    <div class="text-slate-500">
                                        訂單：{{ item.order_invoice_no }} × {{ item.quantity }} 個
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                        <div>
                            <span class="text-slate-500">總數量：</span>
                            <span class="font-bold text-slate-900">{{ shipment.total_quantity || 0 }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">狀態：</span>
                            <span :class="getStatusClass(shipment.status)" class="px-2 py-0.5 text-xs font-medium rounded-full">
                                {{ getStatusText(shipment.status) }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-slate-500">建立日期：</span>
                            <span class="text-slate-900">{{ formatDate(shipment.created_at) }}</span>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <button 
                            v-if="shipment.status === 'pending'"
                            @click="markShipped(shipment.id)" 
                            class="flex-1 px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition shadow-sm">
                            標記已出貨
                        </button>
                        <button 
                            @click="viewShipmentDetail(shipment.id)" 
                            class="flex-1 py-2 bg-primary text-white rounded-lg text-sm font-medium">
                            查看詳情
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- 桌面版分頁 -->
            <footer class="hidden md:flex items-center justify-between px-6 py-4 bg-white border border-slate-200 rounded-2xl shadow-sm mt-6">
                <div class="flex items-center gap-4">
                    <span class="text-xs text-slate-500 font-medium">
                        <template v-if="perPage === -1">顯示全部 {{ totalShipments }} 筆</template>
                        <template v-else>顯示 {{ totalShipments }} 筆中的第 {{ (currentPage - 1) * perPage + 1 }} 到 {{ Math.min(currentPage * perPage, totalShipments) }} 筆</template>
                    </span>
                    <select 
                        v-model="perPage" 
                        @change="changePerPage"
                        class="px-3 py-1.5 text-xs font-medium border border-slate-200 rounded-lg bg-white focus:ring-1 focus:ring-primary outline-none">
                        <option :value="5">5 / 頁</option>
                        <option :value="10">10 / 頁</option>
                        <option :value="30">30 / 頁</option>
                        <option :value="50">50 / 頁</option>
                        <option :value="100">100 / 頁</option>
                        <option :value="-1">全部</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button 
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'cursor-not-allowed text-slate-400' : 'text-slate-600 hover:bg-slate-50'"
                        class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs transition">
                        上一頁
                    </button>
                    <button 
                        v-for="page in visiblePages" 
                        :key="page"
                        @click="goToPage(page)"
                        :class="page === currentPage ? 'bg-primary text-white border-primary font-bold shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                        class="px-3 py-1.5 border rounded-lg text-xs transition">
                        {{ page }}
                    </button>
                    <button 
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        :class="currentPage === totalPages ? 'cursor-not-allowed text-slate-400' : 'text-slate-600 hover:bg-slate-50'"
                        class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs transition">
                        下一頁
                    </button>
                </div>
            </footer>
            
            <!-- 手機版分頁 -->
            <footer class="flex md:hidden items-center justify-between px-4 py-3 bg-white border border-slate-200 rounded-2xl shadow-sm mt-6">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 font-medium">
                        <template v-if="perPage === -1">全部 {{ totalShipments }} 筆</template>
                        <template v-else>第 {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalShipments) }} 筆</template>
                    </span>
                    <select 
                        v-model="perPage" 
                        @change="changePerPage"
                        class="text-xs px-2 py-1.5 border border-slate-200 rounded-lg bg-white outline-none">
                        <option :value="5">5/頁</option>
                        <option :value="10">10/頁</option>
                        <option :value="30">30/頁</option>
                        <option :value="50">50/頁</option>
                        <option :value="100">100/頁</option>
                        <option :value="-1">全部</option>
                    </select>
                </div>
                <div class="flex gap-1.5">
                    <button 
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-lg bg-white transition"
                        :class="currentPage === 1 ? 'text-slate-400 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-50'">
                        ◀
                    </button>
                    <button 
                        class="w-8 h-8 flex items-center justify-center bg-primary text-white rounded-lg text-xs font-bold shadow-sm">
                        {{ currentPage }}
                    </button>
                    <button 
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-lg bg-white transition"
                        :class="currentPage === totalPages ? 'text-slate-400 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-50'">
                        ▶
                    </button>
                </div>
            </footer>
        </div>
    </div>
</main>
HTML;
?>

<script>
const ShipmentProductsPageComponent = {
    name: 'ShipmentProductsPage',
    template: `<?php echo $shipment_products_component_template; ?>`,
    setup() {
        const { ref, computed, onMounted } = Vue;
        
        // 狀態變數
        const shipments = ref([]);
        const loading = ref(false);
        const error = ref(null);
        
        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(10);
        const totalShipments = ref(0);
        
        // 狀態篩選
        const currentStatusFilter = ref('all');
        const statusFilters = [
            { value: 'all', label: '全部' },
            { value: 'pending', label: '待出貨' },
            { value: 'shipped', label: '已出貨' }
        ];
        
        // 批次操作
        const selectedItems = ref([]);
        
        // 展開狀態（用於商品列表展開）
        const expandedShipments = ref(new Set());
        
        // 載入出貨單列表
        const loadShipments = async () => {
            loading.value = true;
            error.value = null;
            
            try {
                let url = `/wp-json/buygo-plus-one/v1/shipments?page=${currentPage.value}&per_page=${perPage.value}`;
                
                if (currentStatusFilter.value !== 'all') {
                    url += `&status=${currentStatusFilter.value}`;
                }
                
                const response = await fetch(url, {
                    credentials: 'include',
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    shipments.value = result.data;
                    totalShipments.value = result.total || result.data.length;
                } else {
                    throw new Error(result.message || '載入出貨單失敗');
                }
            } catch (err) {
                console.error('載入出貨單錯誤:', err);
                error.value = err.message;
                shipments.value = [];
            } finally {
                loading.value = false;
            }
        };
        
        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 格式化商品列表顯示
        const formatItemsDisplay = (shipment, maxLength = 50) => {
            if (!shipment.items || !Array.isArray(shipment.items) || shipment.items.length === 0) {
                return `${shipment.items_count || 0} 件商品`;
            }
            
            const itemsText = shipment.items
                .map(item => `${item.product_name || '未知商品'} x${item.quantity || 0}`)
                .join(', ');
            
            // 如果文字太長，截斷並加上省略號
            if (itemsText.length > maxLength) {
                return itemsText.substring(0, maxLength) + '...';
            }
            
            return itemsText;
        };
        
        // 切換出貨單展開狀態
        const toggleShipmentExpand = (shipmentId) => {
            if (expandedShipments.value.has(shipmentId)) {
                expandedShipments.value.delete(shipmentId);
            } else {
                expandedShipments.value.add(shipmentId);
            }
        };
        
        // 檢查出貨單是否展開
        const isShipmentExpanded = (shipmentId) => {
            return expandedShipments.value.has(shipmentId);
        };
        
        // 取得狀態樣式
        const getStatusClass = (status) => {
            const statusClasses = {
                'pending': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                'shipped': 'bg-green-100 text-green-800 border border-green-200',
                'delivered': 'bg-blue-100 text-blue-800 border border-blue-200'
            };
            return statusClasses[status] || 'bg-slate-100 text-slate-800';
        };
        
        // 取得狀態文字
        const getStatusText = (status) => {
            const statusTexts = {
                'pending': '待出貨',
                'shipped': '已出貨',
                'delivered': '已送達'
            };
            return statusTexts[status] || status;
        };
        
        // 設定狀態篩選
        const setStatusFilter = (status) => {
            currentStatusFilter.value = status;
            currentPage.value = 1;
            loadShipments();
        };
        
        // 標記為已出貨
        const markShipped = async (shipmentId) => {
            if (!confirm('確定要標記此出貨單為已出貨嗎？')) {
                return;
            }
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        shipment_ids: [shipmentId]
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('標記成功！');
                    await loadShipments();
                } else {
                    alert('標記失敗：' + result.message);
                }
            } catch (err) {
                console.error('標記失敗:', err);
                alert('標記失敗：' + err.message);
            }
        };
        
        // 批次標記為已出貨
        const batchMarkShipped = async () => {
            if (selectedItems.value.length === 0) {
                return;
            }
            
            if (!confirm(`確定要標記 ${selectedItems.value.length} 個出貨單為已出貨嗎？`)) {
                return;
            }
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        shipment_ids: selectedItems.value
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`成功標記 ${result.count} 個出貨單為已出貨！`);
                    selectedItems.value = [];
                    await loadShipments();
                } else {
                    alert('標記失敗：' + result.message);
                }
            } catch (err) {
                console.error('批次標記失敗:', err);
                alert('標記失敗：' + err.message);
            }
        };
        
        // 查看出貨單詳情
        const viewShipmentDetail = (shipmentId) => {
            // TODO: 實作出貨單詳情頁面或 Modal
            alert('查看出貨單詳情：' + shipmentId);
        };
        
        // 開啟建立出貨單 Modal
        const openCreateModal = () => {
            // TODO: 實作建立出貨單 Modal
            alert('建立出貨單功能開發中...');
        };
        
        // 全選/取消全選
        const toggleSelectAll = (event) => {
            if (event.target.checked) {
                selectedItems.value = shipments.value.map(s => s.id);
            } else {
                selectedItems.value = [];
            }
        };
        
        // 分頁
        const totalPages = computed(() => {
            if (perPage.value === -1) return 1;
            return Math.ceil(totalShipments.value / perPage.value);
        });
        
        // 可見的頁碼（最多顯示 5 頁）
        const visiblePages = computed(() => {
            const pages = [];
            const total = totalPages.value;
            const current = currentPage.value;
            
            if (total <= 5) {
                for (let i = 1; i <= total; i++) {
                    pages.push(i);
                }
            } else {
                if (current <= 3) {
                    pages.push(1, 2, 3, 4, 5);
                } else if (current >= total - 2) {
                    for (let i = total - 4; i <= total; i++) {
                        pages.push(i);
                    }
                } else {
                    for (let i = current - 2; i <= current + 2; i++) {
                        pages.push(i);
                    }
                }
            }
            
            return pages;
        });
        
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
        
        const goToPage = (page) => {
            currentPage.value = page;
            loadShipments();
        };
        
        const changePerPage = () => {
            currentPage.value = 1;
            loadShipments();
        };
        
        // 初始化
        onMounted(() => {
            loadShipments();
        });
        
        return {
            shipments,
            loading,
            error,
            currentPage,
            perPage,
            totalShipments,
            totalPages,
            visiblePages,
            previousPage,
            nextPage,
            goToPage,
            changePerPage,
            formatDate,
            formatItemsDisplay,
            getStatusClass,
            getStatusText,
            toggleShipmentExpand,
            isShipmentExpanded,
            expandedShipments,
            currentStatusFilter,
            statusFilters,
            setStatusFilter,
            markShipped,
            batchMarkShipped,
            viewShipmentDetail,
            openCreateModal,
            toggleSelectAll,
            selectedItems,
            loadShipments
        };
    }
};
</script>
