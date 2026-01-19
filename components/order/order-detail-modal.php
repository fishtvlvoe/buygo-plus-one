<?php
// 訂單詳情 Modal 元件
$order_detail_modal_template = <<<'HTML'
<!-- Subpage 模式（無 Modal 外框） -->
<div v-if="isSubpage" class="space-y-4 md:space-y-6">
    <!-- Loading -->
    <div v-if="loading" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
        <p class="mt-2 text-slate-500">載入中...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-center py-8">
        <svg class="w-12 h-12 mx-auto mb-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-red-600 font-medium mb-2">載入訂單失敗</p>
        <p class="text-sm text-slate-500">{{ error }}</p>
        <button @click="loadOrderDetail" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-blue-700 transition">
            重試
        </button>
    </div>

    <!-- 無資料提示 -->
    <div v-else-if="!loading && !error && !orderData" class="text-center py-12">
        <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <p class="text-slate-500">找不到訂單資料</p>
        <button @click="loadOrderDetail" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-blue-700 transition">
            重新載入
        </button>
    </div>

    <!-- Order Details -->
    <div v-else-if="orderData">
        <!-- 客戶資訊卡片 -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
            <h4 class="text-sm font-bold text-slate-900 mb-4 border-l-4 border-primary pl-3">客戶資訊</h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">訂單編號</span>
                    <span class="font-bold text-slate-900 md:block">{{ orderData.invoice_no || ('訂單 #' + orderData.id) }}</span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">訂單狀態</span>
                    <span :class="getStatusClass(orderData.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                        {{ getStatusText(orderData.status) }}
                    </span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">客戶姓名</span>
                    <span class="font-bold text-slate-900 md:block">{{ orderData.customer_name || '-' }}</span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">客戶 Email</span>
                    <span class="text-slate-900 md:block break-all">{{ orderData.customer_email || '-' }}</span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">總金額</span>
                    <span class="font-bold text-slate-900 md:block">{{ formatPrice(orderData.total_amount, orderData.currency) }}</span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">下單日期</span>
                    <span class="text-slate-900 md:block">{{ formatDate(orderData.created_at) }}</span>
                </div>
            </div>
        </div>

        <!-- 狀態操作卡片 -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 md:p-6">
            <h4 class="text-xs md:text-sm font-bold text-slate-900 mb-3 md:mb-4 border-l-4 border-primary pl-2 md:pl-3">狀態操作</h4>

            <div class="flex flex-col gap-3 md:flex-row md:items-end md:gap-4">
                <!-- 訂單狀態 -->
                <div class="flex-1">
                    <label class="block text-xs text-slate-600 font-medium mb-1.5">訂單狀態</label>
                    <select
                        v-model="localOrderStatus"
                        class="w-full rounded-lg border-slate-300 text-xs md:text-sm focus:border-primary focus:ring-primary py-2 md:py-2.5 px-2 md:px-3 bg-white shadow-sm"
                        :disabled="updatingStatus"
                    >
                        <option value="pending">待處理</option>
                        <option value="on-hold">保留</option>
                        <option value="processing">處理中</option>
                        <option value="completed">已完成</option>
                        <option value="cancelled">已取消</option>
                        <option value="refunded">已退款</option>
                    </select>
                </div>

                <!-- 運送狀態 -->
                <div class="flex-1">
                    <label class="block text-xs text-slate-600 font-medium mb-1.5">運送狀態</label>
                    <select
                        v-model="localShippingStatus"
                        class="w-full rounded-lg border-slate-300 text-xs md:text-sm focus:border-primary focus:ring-primary py-2 md:py-2.5 px-2 md:px-3 bg-white shadow-sm"
                        :disabled="updatingStatus"
                    >
                        <option value="pending">未出貨</option>
                        <option value="preparing">備貨中</option>
                        <option value="processing">處理中</option>
                        <option value="shipped">已出貨</option>
                        <option value="completed">交易完成</option>
                        <option value="out_of_stock">斷貨</option>
                    </select>
                </div>

                <!-- 更新按鈕 -->
                <div class="flex-shrink-0">
                    <button
                        @click="updateStatus"
                        :disabled="updatingStatus || !hasStatusChanges"
                        :class="hasStatusChanges ? 'bg-primary hover:bg-blue-700 text-white shadow-md' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                        class="w-full md:w-auto px-4 md:px-6 py-2 md:py-2.5 rounded-lg text-xs md:text-sm font-medium transition-all"
                    >
                        {{ updatingStatus ? '更新中...' : '更新狀態' }}
                    </button>
                </div>
            </div>

            <!-- 錯誤訊息 -->
            <div v-if="statusError" class="mt-3 p-2 md:p-3 bg-red-50 border border-red-200 rounded-lg text-xs md:text-sm text-red-600">
                {{ statusError }}
            </div>
        </div>

        <!-- 商品明細卡片 -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 md:p-6">
            <h4 class="text-xs md:text-sm font-bold text-slate-900 mb-3 md:mb-4 border-l-4 border-primary pl-2 md:pl-3">訂單明細</h4>

            <!-- 商品列表 -->
            <div class="space-y-3 md:space-y-4">
                <div v-for="item in (orderData.items || [])" :key="item.id" class="border border-slate-200 rounded-lg p-3 md:p-4 bg-slate-50/50">
                    <div class="flex gap-3 md:gap-4">
                        <!-- 商品圖片 -->
                        <div class="h-16 w-16 md:h-20 md:w-20 flex-shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-white">
                            <img v-if="item.image || item.product_image" :src="item.image || item.product_image" :alt="item.product_name" class="h-full w-full object-cover">
                            <div v-else class="h-full w-full bg-slate-100 flex items-center justify-center text-slate-400">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>

                        <!-- 商品資訊 -->
                        <div class="flex-1 min-w-0">
                            <h3 class="text-xs md:text-sm font-bold text-slate-900 line-clamp-2 leading-snug mb-1.5 md:mb-2">{{ item.product_name }}</h3>

                            <!-- 價格資訊 -->
                            <div class="flex items-center justify-between mb-2 md:mb-3">
                                <div class="flex items-center gap-1 md:gap-2 text-xs md:text-sm text-slate-600">
                                    <span>{{ item.quantity }} × {{ formatPrice(item.price, orderData.currency) }}</span>
                                </div>
                                <span class="text-xs md:text-sm font-bold text-slate-900">{{ formatPrice(item.total, orderData.currency) }}</span>
                            </div>

                            <!-- 數量統計 -->
                            <div class="flex flex-wrap items-center gap-1.5 md:gap-2">
                                <span v-if="(item.shipped_quantity || 0) > 0" class="text-[10px] md:text-xs bg-green-100 text-green-700 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium">
                                    已出貨: {{ item.shipped_quantity }}
                                </span>
                                <span v-if="(item.allocated_quantity || 0) > 0" class="text-[10px] md:text-xs bg-blue-100 text-blue-700 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium">
                                    已分配: {{ item.allocated_quantity }}
                                </span>
                                <span v-if="(item.pending_quantity || 0) > 0" class="text-[10px] md:text-xs bg-yellow-100 text-yellow-700 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium">
                                    待出貨: {{ item.pending_quantity }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 總計區塊 -->
            <div class="mt-4 md:mt-6 p-3 md:p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs md:text-sm text-slate-600">商品總數</span>
                    <span class="text-xs md:text-sm font-bold text-slate-900">{{ calculateTotalItems }}</span>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                    <span class="text-sm md:text-base font-bold text-slate-900">訂單總金額</span>
                    <span class="text-base md:text-lg font-bold text-primary">{{ formatPrice(orderData.total_amount, orderData.currency) }}</span>
                </div>
            </div>
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
        },
        isSubpage: {
            type: Boolean,
            default: false
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
        
        // 狀態操作相關
        const localOrderStatus = ref('');
        const localShippingStatus = ref('');
        const updatingStatus = ref(false);
        const statusError = ref(null);
        
        // 載入訂單詳情
        const loadOrderDetail = async () => {
            if (!props.orderId) {
                error.value = '訂單 ID 不存在';
                return;
            }

            loading.value = true;
            error.value = null;

            try {
                // 嘗試兩種 API 格式
                let response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${props.orderId}`, {
                    credentials: 'include'
                });

                // 如果第一種格式失敗，嘗試第二種
                if (!response.ok) {
                    response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${props.orderId}`, {
                        credentials: 'include'
                    });
                }

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    // 處理兩種可能的資料格式
                    if (result.data && Array.isArray(result.data) && result.data.length > 0) {
                        orderData.value = result.data[0];
                    } else if (result.data && !Array.isArray(result.data)) {
                        orderData.value = result.data;
                    } else {
                        throw new Error('訂單資料格式錯誤');
                    }

                    // 初始化本地狀態
                    localOrderStatus.value = orderData.value.status || 'pending';
                    localShippingStatus.value = orderData.value.shipping_status || 'pending';
                    statusError.value = null;
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
        
        // 檢查狀態是否有變更
        const hasStatusChanges = computed(() => {
            if (!orderData.value) return false;
            const orderStatusChanged = localOrderStatus.value !== (orderData.value.status || 'pending');
            const shippingStatusChanged = localShippingStatus.value !== (orderData.value.shipping_status || 'pending');
            return orderStatusChanged || shippingStatusChanged;
        });
        
        // 更新狀態
        const updateStatus = async () => {
            if (!orderData.value || !hasStatusChanges.value) return;
            
            // 確認對話框（取消訂單時）
            if (localOrderStatus.value === 'cancelled' && orderData.value.status !== 'cancelled') {
                if (!confirm('確定要取消此訂單嗎？此操作無法復原。')) {
                    return;
                }
            }
            
            updatingStatus.value = true;
            statusError.value = null;
            
            try {
                const orderId = orderData.value.id;
                const updates = [];
                
                // 更新訂單狀態
                if (localOrderStatus.value !== (orderData.value.status || 'pending')) {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderId}/status`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            status: localOrderStatus.value
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || '訂單狀態更新失敗');
                    }
                    updates.push('訂單狀態');
                }
                
                // 更新運送狀態
                if (localShippingStatus.value !== (orderData.value.shipping_status || 'pending')) {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderId}/shipping-status`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            status: localShippingStatus.value
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || '運送狀態更新失敗');
                    }
                    updates.push('運送狀態');
                }
                
                // 重新載入訂單資料
                await loadOrderDetail();
                
                // 顯示成功訊息
                if (updates.length > 0) {
                    alert(`${updates.join('、')}已更新`);
                }
            } catch (err) {
                console.error('更新狀態失敗:', err);
                statusError.value = err.message || '更新狀態時發生錯誤';
            } finally {
                updatingStatus.value = false;
            }
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
            shipOrderItem,
            calculateTotalItems,
            localOrderStatus,
            localShippingStatus,
            updatingStatus,
            statusError,
            hasStatusChanges,
            updateStatus,
            isSubpage: computed(() => props.isSubpage)
        };
    }
};
</script>
