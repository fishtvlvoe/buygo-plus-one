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
    
    <!-- 出貨單列表 -->
    <div class="p-6">
        <div v-if="loading" class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"></div>
            <p class="mt-2 text-slate-600">載入中...</p>
        </div>
        
        <div v-else-if="shipments.length === 0" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            <p class="mt-2 text-slate-600">目前沒有出貨單</p>
        </div>
        
        <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">出貨單號</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">客戶</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">商品數量</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">日期</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <tr v-for="shipment in shipments" :key="shipment.id" class="hover:bg-slate-50 transition">
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
                            <button 
                                v-if="activeTab === 'pending'"
                                @click="markShipped(shipment.id)"
                                class="text-orange-600 hover:text-orange-900 mr-4"
                            >
                                標記已出貨
                            </button>
                            <button 
                                v-if="activeTab === 'shipped'"
                                @click="archiveShipment(shipment.id)"
                                class="text-slate-600 hover:text-slate-900 mr-4"
                            >
                                移至存檔
                            </button>
                            <button 
                                @click="viewDetail(shipment.id)"
                                class="text-blue-600 hover:text-blue-900"
                            >
                                查看詳情
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
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
                        class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 font-medium transition"
                    >
                        取消
                    </button>
                    <button 
                        @click="handleConfirm"
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 font-medium transition"
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
</div>
HTML;

// Vue Component
?>
<script>
const { ref, onMounted, watch } = Vue;

const ShipmentDetailsPageComponent = {
    name: 'ShipmentDetailsPage',
    template: `<?php echo $shipment_details_template; ?>`,
    setup() {
        const activeTab = ref('pending');
        const shipments = ref([]);
        const loading = ref(false);
        const stats = ref({ pending: 0, shipped: 0, archived: 0 });
        
        // Modal 狀態
        const confirmModal = ref({ show: false, title: '', message: '', onConfirm: null });
        const toastMessage = ref({ show: false, message: '', type: 'success' });
        
        // 載入出貨單列表
        const loadShipments = async () => {
            loading.value = true;
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments?status=${activeTab.value}`, {
                    credentials: 'include'
                });
                const result = await response.json();
                
                if (result.success) {
                    shipments.value = result.data || [];
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
        
        // 查看詳情（暫時用 alert，之後可改為 Modal）
        const viewDetail = (shipmentId) => {
            alert('查看詳情功能開發中，出貨單 ID: ' + shipmentId);
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
        
        // 監聽分頁切換
        watch(() => activeTab.value, () => {
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
            confirmModal,
            toastMessage,
            markShipped,
            archiveShipment,
            viewDetail,
            closeConfirmModal,
            handleConfirm,
            formatDate
        };
    }
};
</script>
