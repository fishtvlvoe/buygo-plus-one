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
                
                <!-- 商品明細 -->
                <div>
                    <h4 class="text-sm font-bold text-slate-900 mb-3 border-l-4 border-slate-900 pl-3">訂單明細</h4>
                    
                    <!-- 桌面版：表格樣式 -->
                    <div class="hidden md:block space-y-0 divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden">
                        <div v-for="item in (orderData.items || [])" :key="item.id" class="p-3 flex gap-3 bg-white hover:bg-slate-50 transition">
                            <!-- 商品圖片 -->
                            <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md border border-slate-200">
                                <img v-if="item.image || item.product_image" :src="item.image || item.product_image" :alt="item.product_name" class="h-full w-full object-cover object-center">
                                <div v-else class="h-full w-full bg-slate-100 flex items-center justify-center text-slate-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- 商品資訊 -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-bold text-slate-900 line-clamp-2 leading-snug mb-2">{{ item.product_name }}</h3>
                                
                                <!-- 價格資訊 -->
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded">數量: {{ item.quantity }}</span>
                                        <span class="text-xs text-slate-500">×</span>
                                        <span class="text-xs text-slate-600">{{ formatPrice(item.price, orderData.currency) }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-slate-900">{{ formatPrice(item.total, orderData.currency) }}</span>
                                </div>
                                
                                <!-- 數量統計 -->
                                <div class="flex items-center gap-2 mt-2">
                                    <span v-if="(item.shipped_quantity || 0) > 0" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">
                                        已出貨: {{ item.shipped_quantity || 0 }}
                                    </span>
                                    <span v-if="(item.allocated_quantity || 0) > 0" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                                        已分配: {{ item.allocated_quantity || 0 }}
                                    </span>
                                    <span v-if="(item.pending_quantity || 0) > 0" class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded">
                                        待出貨: {{ item.pending_quantity || 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 手機版：卡片樣式 -->
                    <div class="md:hidden space-y-3">
                        <div v-for="item in (orderData.items || [])" :key="item.id" class="border border-slate-200 rounded-lg p-3 bg-white">
                            <div class="flex gap-3">
                                <!-- 商品圖片 -->
                                <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-md border border-slate-200">
                                    <img v-if="item.image || item.product_image" :src="item.image || item.product_image" :alt="item.product_name" class="h-full w-full object-cover object-center">
                                    <div v-else class="h-full w-full bg-slate-100 flex items-center justify-center text-slate-400">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- 商品資訊 -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-bold text-slate-900 line-clamp-2 leading-snug mb-2">{{ item.product_name }}</h3>
                                    
                                    <!-- 價格資訊 -->
                                    <div class="mb-2">
                                        <div class="text-xs text-slate-500 mb-1">
                                            數量: {{ item.quantity }} × {{ formatPrice(item.price, orderData.currency) }}
                                        </div>
                                        <div class="text-sm font-bold text-slate-900">
                                            小計: {{ formatPrice(item.total, orderData.currency) }}
                                        </div>
                                    </div>
                                    
                                    <!-- 數量統計 -->
                                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span v-if="(item.shipped_quantity || 0) > 0" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">
                                            已出貨: {{ item.shipped_quantity || 0 }}
                                        </span>
                                        <span v-if="(item.allocated_quantity || 0) > 0" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                                            已分配: {{ item.allocated_quantity || 0 }}
                                        </span>
                                        <span v-if="(item.pending_quantity || 0) > 0" class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded">
                                            待出貨: {{ item.pending_quantity || 0 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 總計區塊 -->
                    <div class="mt-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-slate-600 font-medium">商品總數：</span>
                            <span class="text-sm font-bold text-slate-900">{{ calculateTotalItems }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                            <span class="text-base font-bold text-slate-900">訂單總金額：</span>
                            <span class="text-lg font-bold text-slate-900">{{ formatPrice(orderData.total_amount, orderData.currency) }}</span>
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
        const { ref, onMounted, watch, computed } = Vue;
        
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
        
        // 計算商品總數
        const calculateTotalItems = computed(() => {
            if (!orderData.value || !orderData.value.items) return 0;
            return orderData.value.items.reduce((sum, item) => {
                return sum + (parseInt(item.quantity) || 0);
            }, 0);
        });
        
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
            shipOrderItem,
            calculateTotalItems
        };
    }
};
</script>
