<?php
// 訂單詳情 Modal 元件
$order_detail_modal_template = <<<'HTML'
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="p-6 border-b border-slate-200 flex-shrink-0">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900 font-title">訂單詳情</h2>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Body -->
        <div class="p-6 overflow-y-auto flex-1">
            <!-- Loading -->
            <div v-if="loading" class="text-center py-8">
                <p class="text-slate-600">載入中...</p>
            </div>
            
            <!-- Error -->
            <div v-else-if="error" class="text-center py-8">
                <p class="text-red-600">{{ error }}</p>
            </div>
            
            <!-- Order Details -->
            <div v-else-if="orderData">
                <!-- 客戶資訊 -->
                <div class="mb-6">
                    <h4 class="text-sm font-bold text-slate-900 mb-3 border-l-4 border-slate-900 pl-3">客戶資訊</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-[80px_1fr] gap-y-3 text-sm border-t border-slate-100 pt-3">
                        <div class="text-slate-500 font-medium">訂單編號</div>
                        <div class="font-bold text-slate-900">{{ orderData.invoice_no || ('訂單 #' + orderData.id) }}</div>
                        
                        <div class="text-slate-500 font-medium">訂單狀態</div>
                        <div>
                            <span :class="getStatusClass(orderData.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                {{ getStatusText(orderData.status) }}
                            </span>
                        </div>
                        
                        <div class="text-slate-500 font-medium">客戶姓名</div>
                        <div class="font-bold text-slate-900">{{ orderData.customer_name || '-' }}</div>
                        
                        <div class="text-slate-500 font-medium">客戶 Email</div>
                        <div class="text-slate-900 break-all">{{ orderData.customer_email || '-' }}</div>
                        
                        <div class="text-slate-500 font-medium">總金額</div>
                        <div class="text-slate-900 font-bold">{{ formatPrice(orderData.total_amount, orderData.currency) }}</div>
                        
                        <div class="text-slate-500 font-medium">下單日期</div>
                        <div class="text-slate-900">{{ formatDate(orderData.created_at) }}</div>
                    </div>
                </div>
                
                <!-- 商品列表 -->
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-4">商品明細</h3>
                    <div class="space-y-4">
                        <div v-for="item in orderData.items" :key="item.id" class="border-b border-slate-200 pb-4 last:border-b-0">
                            <!-- 商品基本資訊 -->
                            <div class="flex items-center gap-4 mb-3">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-slate-900">{{ item.product_name }}</h4>
                                    <div class="text-sm text-slate-600 mt-1">
                                        數量: {{ item.quantity }} × {{ formatPrice(item.price, orderData.currency) }} = {{ formatPrice(item.total, orderData.currency) }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 出貨統計 -->
                            <div class="grid grid-cols-3 gap-2 mb-3">
                                <div class="bg-gray-100 p-2 rounded text-center">
                                    <div class="text-xs text-gray-600 mb-1">已出貨</div>
                                    <div class="font-bold text-slate-900">{{ item.shipped_quantity || 0 }}</div>
                                </div>
                                <div class="bg-blue-100 p-2 rounded text-center" v-if="item.allocated_quantity > 0">
                                    <div class="text-xs text-blue-600 mb-1">本次可出貨</div>
                                    <div class="font-bold text-blue-700">{{ item.allocated_quantity }}</div>
                                </div>
                                <div class="bg-yellow-100 p-2 rounded text-center">
                                    <div class="text-xs text-yellow-600 mb-1">未出貨</div>
                                    <div class="font-bold text-yellow-700">{{ item.pending_quantity || 0 }}</div>
                                </div>
                            </div>
                            
                            <!-- 出貨按鈕 -->
                            <button 
                                v-if="item.allocated_quantity > 0"
                                @click="shipOrderItem(item)" 
                                :disabled="shipping"
                                class="w-full px-4 py-2 bg-accent text-white rounded-lg text-xs font-black shadow-[0_2px_10px_-3px_rgba(249,115,22,0.5)] hover:bg-orange-600 hover:scale-105 transition active:scale-95 uppercase tracking-tighter disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                            >
                                {{ shipping ? '出貨中...' : ('執行出貨 (' + item.allocated_quantity + ' 個)') }}
                            </button>
                            <div v-else class="text-sm text-slate-500 text-center py-2">
                                本商品尚未分配現貨配額，請先至商品管理分配。
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="p-6 border-t border-slate-200 flex-shrink-0 flex justify-end">
            <button @click="$emit('close')" class="px-6 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-lg font-medium transition shadow-sm">
                關閉
            </button>
        </div>
    </div>
</div>
HTML;
?>

<script>
const OrderDetailModal = {
    name: 'OrderDetailModal',
    props: {
        orderId: {
            type: [Number, String],
            default: null
        }
    },
    emits: ['close'],
    template: `<?php echo $order_detail_modal_template; ?>`,
    setup(props, { emit }) {
        const { ref, onMounted, watch } = Vue;
        
        const orderData = ref(null);
        const loading = ref(false);
        const error = ref(null);
        const shipping = ref(false);
        
        // 載入訂單詳情
        const loadOrderDetail = async () => {
            if (!props.orderId) {
                error.value = '訂單 ID 不存在';
                return;
            }
            
            loading.value = true;
            error.value = null;
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${props.orderId}`, {
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success && result.data && result.data.length > 0) {
                    orderData.value = result.data[0];
                } else {
                    error.value = result.message || '載入訂單失敗';
                }
            } catch (err) {
                console.error('載入訂單詳情失敗:', err);
                error.value = err.message || '載入訂單詳情時發生錯誤';
            } finally {
                loading.value = false;
            }
        };
        
        // 格式化金額
        const formatPrice = (price, currency = 'TWD') => {
            return `${price.toLocaleString()} ${currency}`;
        };
        
        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 取得狀態樣式
        const getStatusClass = (status) => {
            const statusClasses = {
                'pending': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                'processing': 'bg-blue-100 text-blue-800 border border-blue-200',
                'shipped': 'bg-purple-100 text-purple-800 border border-purple-200',
                'completed': 'bg-green-100 text-green-800 border border-green-200',
                'cancelled': 'bg-red-100 text-red-800 border border-red-200'
            };
            return statusClasses[status] || 'bg-slate-100 text-slate-800';
        };
        
        // 取得狀態文字
        const getStatusText = (status) => {
            const statusTexts = {
                'pending': '待處理',
                'processing': '處理中',
                'shipped': '已出貨',
                'completed': '已完成',
                'cancelled': '已取消'
            };
            return statusTexts[status] || status;
        };
        
        // 執行出貨
        const shipOrderItem = async (item) => {
            if (!confirm(`確定要出貨 ${item.allocated_quantity} 個「${item.product_name}」嗎？`)) {
                return;
            }
            
            shipping.value = true;
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${item.order_id}/ship`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        items: [{
                            order_item_id: item.id,
                            quantity: item.allocated_quantity,
                            product_id: item.product_id
                        }]
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`出貨成功！出貨單號：SH-${result.shipment_id}`);
                    // 重新載入訂單詳情
                    await loadOrderDetail();
                } else {
                    alert('出貨失敗：' + result.message);
                }
            } catch (err) {
                console.error('出貨失敗:', err);
                alert('出貨失敗：' + err.message);
            } finally {
                shipping.value = false;
            }
        };
        
        // 監聽 orderId 變化，重新載入資料
        watch(() => props.orderId, (newId) => {
            if (newId) {
                orderData.value = null;
                loadOrderDetail();
            }
        }, { immediate: true });
        
        return {
            orderData,
            loading,
            error,
            shipping,
            formatPrice,
            formatDate,
            getStatusClass,
            getStatusText,
            shipOrderItem
        };
    }
};
</script>
