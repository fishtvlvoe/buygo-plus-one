<?php
/**
 * 訂單詳情 Modal 元件
 *
 * 變更重點（WPCS / CR）：
 * - 所有可見字串改用 i18n（buygo-plus-one）
 * - wpNonce 以 wp_json_encode() 輸出到 JS，避免未轉義輸出
 */

// 可見字串（HTML 文字節點用 esc_html__，避免 XSS）。
$buygo_order_detail_i18n_html = [
    'loading'                 => esc_html__( '載入中...', 'buygo-plus-one' ),
    'load_order_failed'       => esc_html__( '載入訂單失敗', 'buygo-plus-one' ),
    'retry'                   => esc_html__( '重試', 'buygo-plus-one' ),
    'order_not_found'         => esc_html__( '找不到訂單資料', 'buygo-plus-one' ),
    'reload'                  => esc_html__( '重新載入', 'buygo-plus-one' ),
    'status_actions'          => esc_html__( '狀態操作', 'buygo-plus-one' ),
    'order_status'            => esc_html__( '訂單狀態', 'buygo-plus-one' ),
    'shipping_status'         => esc_html__( '運送狀態', 'buygo-plus-one' ),
    'updating'                => esc_html__( '更新中...', 'buygo-plus-one' ),
    'update_status'           => esc_html__( '更新狀態', 'buygo-plus-one' ),
    'customer_info'           => esc_html__( '客戶資訊', 'buygo-plus-one' ),
    'name'                    => esc_html__( '姓名', 'buygo-plus-one' ),
    'phone'                   => esc_html__( '電話', 'buygo-plus-one' ),
    'email'                   => esc_html__( 'Email', 'buygo-plus-one' ),
    'address'                 => esc_html__( '配送地址', 'buygo-plus-one' ),
    'payment_method'          => esc_html__( '付款方式', 'buygo-plus-one' ),
    'order_details'           => esc_html__( '訂單明細', 'buygo-plus-one' ),
    'shipped_label'           => esc_html__( '已出貨:', 'buygo-plus-one' ),
    'allocated_label'         => esc_html__( '已分配:', 'buygo-plus-one' ),
    'pending_label'           => esc_html__( '待出貨:', 'buygo-plus-one' ),
    'cancel'                  => esc_html__( '取消', 'buygo-plus-one' ),
    'remove'                  => esc_html__( '移除', 'buygo-plus-one' ),
    'total_items'             => esc_html__( '商品總數', 'buygo-plus-one' ),
    'order_total'             => esc_html__( '訂單總金額', 'buygo-plus-one' ),
    'close'                   => esc_html__( '關閉', 'buygo-plus-one' ),
    'pending'                 => esc_html__( '待處理', 'buygo-plus-one' ),
    'on_hold'                 => esc_html__( '保留', 'buygo-plus-one' ),
    'processing'              => esc_html__( '處理中', 'buygo-plus-one' ),
    'completed'               => esc_html__( '已完成', 'buygo-plus-one' ),
    'cancelled'               => esc_html__( '已取消', 'buygo-plus-one' ),
    'refunded'                => esc_html__( '已退款', 'buygo-plus-one' ),
    'unshipped'               => esc_html__( '未出貨', 'buygo-plus-one' ),
    'preparing'               => esc_html__( '備貨中', 'buygo-plus-one' ),
    'shipping_processing'     => esc_html__( '處理中', 'buygo-plus-one' ),
    'shipped'                 => esc_html__( '已出貨', 'buygo-plus-one' ),
    'shipping_completed'      => esc_html__( '交易完成', 'buygo-plus-one' ),
    'out_of_stock'            => esc_html__( '斷貨', 'buygo-plus-one' ),
];

// JS 訊息用 __()，由 wp_json_encode() 轉成安全的 JS 字串。
$buygo_order_detail_i18n_js = [
    'order_id_missing'              => __( '訂單 ID 不存在', 'buygo-plus-one' ),
    'order_data_format_invalid'     => __( '訂單資料格式錯誤', 'buygo-plus-one' ),
    'load_order_failed_fallback'    => __( '載入訂單失敗', 'buygo-plus-one' ),
    'load_order_error_fallback'     => __( '載入訂單詳情時發生錯誤', 'buygo-plus-one' ),
    'confirm_cancel_order'          => __( '確定要取消此訂單嗎？此操作無法復原。', 'buygo-plus-one' ),
    'order_status_update_failed'    => __( '訂單狀態更新失敗', 'buygo-plus-one' ),
    'shipping_status_update_failed' => __( '運送狀態更新失敗', 'buygo-plus-one' ),
    'updated_suffix'                => __( '%s已更新', 'buygo-plus-one' ),
    'update_status_error_fallback'  => __( '更新狀態時發生錯誤', 'buygo-plus-one' ),
    'confirm_ship_item'             => __( '確定要出貨 %1$s 個「%2$s」嗎？', 'buygo-plus-one' ),
    'ship_success'                  => __( '出貨成功！出貨單號：SH-%s', 'buygo-plus-one' ),
    'ship_failed_prefix'            => __( '出貨失敗：%s', 'buygo-plus-one' ),
    'confirm_cancel_child_order'    => __( '確定要取消子訂單 #%s 嗎？此操作無法復原。', 'buygo-plus-one' ),
    'child_order_cancelled'         => __( '子訂單已取消', 'buygo-plus-one' ),
    'unknown_error'                 => __( '未知錯誤', 'buygo-plus-one' ),
    'cancel_failed_retry'           => __( '取消失敗，請稍後再試', 'buygo-plus-one' ),
    'confirm_remove_item'           => __( '確定要從訂單中移除「%s」嗎？此操作無法復原。', 'buygo-plus-one' ),
    'remove_failed_retry'           => __( '移除失敗，請稍後再試', 'buygo-plus-one' ),
    'item_removed'                  => __( '商品已從訂單移除', 'buygo-plus-one' ),
    'remove_failed'                 => __( '移除失敗', 'buygo-plus-one' ),
    'remove_failed_http'            => __( '移除失敗（HTTP %s）', 'buygo-plus-one' ),
];

// 變更：nonce 透過 wp_json_encode() 輸出到 JS（避免未轉義）。
$buygo_wp_nonce = wp_create_nonce( 'wp_rest' );

// 變更：NOWDOC → HEREDOC，讓 i18n 文字可透過變數插入（WPCS）。
$order_detail_modal_template = <<<HTML
<!-- Subpage 模式（無 Modal 外框） -->
<div v-if="isSubpage" class="space-y-4 md:space-y-6">
    <!-- Loading -->
    <div v-if="loading" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
        <p class="mt-2 text-slate-500">{$buygo_order_detail_i18n_html['loading']}</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-center py-8">
        <svg class="w-12 h-12 mx-auto mb-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-red-600 font-medium mb-2">{$buygo_order_detail_i18n_html['load_order_failed']}</p>
        <p class="text-sm text-slate-500">{{ error }}</p>
        <button @click="loadOrderDetail" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-blue-700 transition">
            {$buygo_order_detail_i18n_html['retry']}
        </button>
    </div>

    <!-- 無資料提示 -->
    <div v-else-if="!loading && !error && !orderData" class="text-center py-12">
        <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <p class="text-slate-500">{$buygo_order_detail_i18n_html['order_not_found']}</p>
        <button @click="loadOrderDetail" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-blue-700 transition">
            {$buygo_order_detail_i18n_html['reload']}
        </button>
    </div>

    <!-- Order Details -->
    <div v-else-if="orderData" class="space-y-4 md:space-y-6">
        <!-- 狀態操作卡片 -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 md:p-6">
            <h4 class="text-xs md:text-sm font-bold text-slate-900 mb-3 md:mb-4 border-l-4 border-primary pl-2 md:pl-3">{$buygo_order_detail_i18n_html['status_actions']}</h4>

            <div class="flex flex-col gap-3 md:flex-row md:items-end md:gap-4">
                <!-- 訂單狀態 -->
                <div class="flex-1">
                    <label class="block text-xs text-slate-600 font-medium mb-1.5">{$buygo_order_detail_i18n_html['order_status']}</label>
                    <select
                        v-model="localOrderStatus"
                        class="w-full rounded-lg border-slate-300 text-xs md:text-sm focus:border-primary focus:ring-primary py-2 md:py-2.5 px-2 md:px-3 bg-white shadow-sm"
                        :disabled="updatingStatus"
                    >
                        <option value="pending">{$buygo_order_detail_i18n_html['pending']}</option>
                        <option value="on-hold">{$buygo_order_detail_i18n_html['on_hold']}</option>
                        <option value="processing">{$buygo_order_detail_i18n_html['processing']}</option>
                        <option value="completed">{$buygo_order_detail_i18n_html['completed']}</option>
                        <option value="cancelled">{$buygo_order_detail_i18n_html['cancelled']}</option>
                        <option value="refunded">{$buygo_order_detail_i18n_html['refunded']}</option>
                    </select>
                </div>

                <!-- 運送狀態 -->
                <div class="flex-1">
                    <label class="block text-xs text-slate-600 font-medium mb-1.5">{$buygo_order_detail_i18n_html['shipping_status']}</label>
                    <div class="relative">
                        <button
                            type="button"
                            @click="toggleShippingDropdown"
                            :disabled="updatingStatus"
                            class="w-full rounded-lg border border-slate-300 text-xs md:text-sm focus:border-primary focus:ring-2 focus:ring-primary py-2 md:py-2.5 px-2 md:px-3 bg-white shadow-sm text-left flex items-center justify-between disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span :class="getShippingStatusColor(localShippingStatus)" class="px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap">
                                {{ getShippingStatusText(localShippingStatus) }}
                            </span>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- 下拉選單 -->
                        <div
                            v-if="showShippingDropdown"
                            @click.stop
                            class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg py-1 max-h-60 overflow-auto"
                        >
                            <button
                                v-for="status in shippingStatuses"
                                :key="status.value"
                                type="button"
                                @click="selectShippingStatus(status.value)"
                                :class="[
                                    'w-full px-3 py-2 text-left text-xs hover:bg-slate-50 transition',
                                    localShippingStatus === status.value ? 'bg-slate-100' : ''
                                ]"
                            >
                                <span :class="status.color" class="px-3 py-1 rounded-full whitespace-nowrap inline-block">
                                    {{ status.label }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 更新按鈕 -->
                <div class="flex-shrink-0">
                    <button
                        @click="updateStatus"
                        :disabled="updatingStatus || !hasStatusChanges"
                        :class="hasStatusChanges ? 'bg-primary hover:bg-blue-700 text-white shadow-md' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                        class="w-full md:w-auto px-4 md:px-6 py-2 md:py-2.5 rounded-lg text-xs md:text-sm font-medium transition-all"
                    >
                        {{ updatingStatus ? '{$buygo_order_detail_i18n_html['updating']}' : '{$buygo_order_detail_i18n_html['update_status']}' }}
                    </button>
                </div>
            </div>

            <!-- 錯誤訊息 -->
            <div v-if="statusError" class="mt-3 p-2 md:p-3 bg-red-50 border border-red-200 rounded-lg text-xs md:text-sm text-red-600">
                {{ statusError }}
            </div>
        </div>

        <!-- 客戶資訊卡片 -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
            <h4 class="text-sm font-bold text-slate-900 mb-4 border-l-4 border-primary pl-3">{$buygo_order_detail_i18n_html['customer_info']}</h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">{$buygo_order_detail_i18n_html['name']}</span>
                    <span class="font-bold text-slate-900 md:block">{{ orderData.customer_name || '-' }}</span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">{$buygo_order_detail_i18n_html['phone']}</span>
                    <span class="text-slate-900 md:block">{{ orderData.customer_phone || '-' }}</span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">{$buygo_order_detail_i18n_html['email']}</span>
                    <span class="text-slate-900 md:block break-all">{{ orderData.customer_email || '-' }}</span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">{$buygo_order_detail_i18n_html['address']}</span>
                    <span class="text-slate-900 md:block">{{ orderData.customer_address || '-' }}</span>
                </div>
                <div class="flex justify-between md:block">
                    <span class="text-slate-500">{$buygo_order_detail_i18n_html['payment_method']}</span>
                    <span class="text-slate-900 md:block">{{ orderData.payment_method || '-' }}</span>
                </div>
            </div>
        </div>

        <!-- 商品明細卡片 -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 md:p-6">
            <h4 class="text-xs md:text-sm font-bold text-slate-900 mb-3 md:mb-4 border-l-4 border-primary pl-2 md:pl-3">{$buygo_order_detail_i18n_html['order_details']}</h4>

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
                                    {$buygo_order_detail_i18n_html['shipped_label']} {{ item.shipped_quantity }}
                                </span>
                                <span v-if="(item.allocated_quantity || 0) > 0" class="text-[10px] md:text-xs bg-blue-100 text-blue-700 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium">
                                    {$buygo_order_detail_i18n_html['allocated_label']} {{ item.allocated_quantity }}
                                </span>
                                <span v-if="(item.pending_quantity || 0) > 0" class="text-[10px] md:text-xs bg-yellow-100 text-yellow-700 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium">
                                    {$buygo_order_detail_i18n_html['pending_label']} {{ item.pending_quantity }}
                                </span>
                                <button
                                    v-if="item.child_order_id && (!item.shipping_status || item.shipping_status === 'unshipped') && item.status !== 'cancelled'"
                                    @click="cancelChildOrder({id: item.child_order_id, invoice_no: item.child_invoice_no})"
                                    class="text-[10px] md:text-xs bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium transition"
                                >{$buygo_order_detail_i18n_html['cancel']}</button>
                                <button
                                    v-if="orderData.status !== 'completed' && orderData.status !== 'cancelled'"
                                    @click="removeOrderItem(item)"
                                    class="text-[10px] md:text-xs bg-orange-50 text-orange-600 border border-orange-200 hover:bg-orange-100 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium transition"
                                >{$buygo_order_detail_i18n_html['remove']}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 總計區塊 -->
            <div class="mt-4 md:mt-6 p-3 md:p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs md:text-sm text-slate-600">{$buygo_order_detail_i18n_html['total_items']}</span>
                    <span class="text-xs md:text-sm font-bold text-slate-900">{{ calculateTotalItems }}</span>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                    <span class="text-sm md:text-base font-bold text-slate-900">{$buygo_order_detail_i18n_html['order_total']}</span>
                    <span class="text-base md:text-lg font-bold text-primary">{{ formatPrice(orderData.total_amount, orderData.currency) }}</span>
                </div>
            </div>

            <!-- 底部關閉按鈕 -->
            <div v-if="isSubpage" class="mt-6 md:mt-8 flex justify-center">
                <button @click="$emit('close')" class="w-full md:w-auto px-6 py-3 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition text-sm font-medium shadow-md">{$buygo_order_detail_i18n_html['close']}</button>
            </div>
        </div>
    </div>
</div>

HTML;
?>

<script>
// 變更：集中管理 JS 字串，並以 wp_json_encode() 輸出，符合 i18n 與轉義規範（WPCS）。
const buygoOrderDetailI18n = <?php echo wp_json_encode( $buygo_order_detail_i18n_js, JSON_UNESCAPED_UNICODE ); ?>;

// 變更：nonce 以 wp_json_encode() 輸出到 JS，避免未轉義輸出（CR）。
const buygoWpNonce = <?php echo wp_json_encode( $buygo_wp_nonce ); ?>;

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
        },
        wpNonce: {
            type: String,
            // 變更：提供預設值（安全輸出），外部仍可覆蓋傳入。
            default: buygoWpNonce
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

        // 運送狀態下拉選單
        const showShippingDropdown = ref(false);

        // 運送狀態選項（與訂單列表保持一致）
        const shippingStatuses = [
            { value: 'unshipped', label: '<?php echo esc_js( $buygo_order_detail_i18n_html['unshipped'] ); ?>', color: 'bg-gray-100 text-gray-800 border border-gray-300' },
            { value: 'preparing', label: '<?php echo esc_js( $buygo_order_detail_i18n_html['preparing'] ); ?>', color: 'bg-yellow-100 text-yellow-800 border border-yellow-300' },
            { value: 'processing', label: '<?php echo esc_js( $buygo_order_detail_i18n_html['shipping_processing'] ); ?>', color: 'bg-blue-100 text-blue-800 border border-blue-300' },
            { value: 'shipped', label: '<?php echo esc_js( $buygo_order_detail_i18n_html['shipped'] ); ?>', color: 'bg-purple-100 text-purple-800 border border-purple-300' },
            { value: 'completed', label: '<?php echo esc_js( $buygo_order_detail_i18n_html['shipping_completed'] ); ?>', color: 'bg-green-100 text-green-800 border border-green-300' },
            { value: 'out_of_stock', label: '<?php echo esc_js( $buygo_order_detail_i18n_html['out_of_stock'] ); ?>', color: 'bg-red-100 text-red-800 border border-red-300' }
        ];

        const toggleShippingDropdown = () => {
            showShippingDropdown.value = !showShippingDropdown.value;
        };

        const selectShippingStatus = (value) => {
            localShippingStatus.value = value;
            showShippingDropdown.value = false;
        };

        const getShippingStatusColor = (status) => {
            const statusObj = shippingStatuses.find(s => s.value === status);
            return statusObj ? statusObj.color : 'bg-slate-100 text-slate-800';
        };

        const getShippingStatusText = (status) => {
            const statusObj = shippingStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : status;
        };

        // 載入訂單詳情
        const loadOrderDetail = async () => {
            if (!props.orderId) {
                error.value = buygoOrderDetailI18n.order_id_missing;
                return;
            }

            loading.value = true;
            error.value = null;

            try {
                // 嘗試兩種 API 格式
                let response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${props.orderId}`, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': props.wpNonce }
                });

                // 如果第一種格式失敗，嘗試第二種
                if (!response.ok) {
                    response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${props.orderId}`, {
                        credentials: 'include',
                        headers: { 'X-WP-Nonce': props.wpNonce }
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
                        throw new Error(buygoOrderDetailI18n.order_data_format_invalid);
                    }

                    // 初始化本地狀態
                    localOrderStatus.value = orderData.value.status || 'pending';
                    localShippingStatus.value = orderData.value.shipping_status || 'unshipped';
                    statusError.value = null;
                } else {
                    error.value = result.message || buygoOrderDetailI18n.load_order_failed_fallback;
                }
            } catch (err) {
                console.error('載入訂單詳情失敗:', err);
                error.value = err.message || buygoOrderDetailI18n.load_order_error_fallback;
            } finally {
                loading.value = false;
            }
        };

        // 格式化金額
        const formatPrice = (price, currency = 'TWD') => {
            return `${price.toLocaleString()} ${currency}`;
        };

        // 格格式化日期
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
                'pending': '<?php echo esc_js( $buygo_order_detail_i18n_html['pending'] ); ?>',
                'processing': '<?php echo esc_js( $buygo_order_detail_i18n_html['processing'] ); ?>',
                'shipped': '<?php echo esc_js( $buygo_order_detail_i18n_html['shipped'] ); ?>',
                'completed': '<?php echo esc_js( $buygo_order_detail_i18n_html['completed'] ); ?>',
                'cancelled': '<?php echo esc_js( $buygo_order_detail_i18n_html['cancelled'] ); ?>'
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
            const shippingStatusChanged = localShippingStatus.value !== (orderData.value.shipping_status || 'unshipped');
            return orderStatusChanged || shippingStatusChanged;
        });

        // 更新狀態
        const updateStatus = async () => {
            if (!orderData.value || !hasStatusChanges.value) return;

            // 確認對話框（取消訂單時）
            if (localOrderStatus.value === 'cancelled' && orderData.value.status !== 'cancelled') {
                if (!confirm(buygoOrderDetailI18n.confirm_cancel_order)) {
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
                            'X-WP-Nonce': props.wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            status: localOrderStatus.value
                        })
                    });

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || buygoOrderDetailI18n.order_status_update_failed);
                    }
                    updates.push('<?php echo esc_js( $buygo_order_detail_i18n_html['order_status'] ); ?>');
                }

                // 更新運送狀態
                if (localShippingStatus.value !== (orderData.value.shipping_status || 'unshipped')) {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderId}/shipping-status`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': props.wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            status: localShippingStatus.value
                        })
                    });

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || buygoOrderDetailI18n.shipping_status_update_failed);
                    }
                    updates.push('<?php echo esc_js( $buygo_order_detail_i18n_html['shipping_status'] ); ?>');
                }

                // 重新載入訂單資料
                await loadOrderDetail();

                // 顯示成功訊息
                if (updates.length > 0) {
                    alert(buygoOrderDetailI18n.updated_suffix.replace('%s', `${updates.join('、')}`));
                }
            } catch (err) {
                console.error('更新狀態失敗:', err);
                statusError.value = err.message || buygoOrderDetailI18n.update_status_error_fallback;
            } finally {
                updatingStatus.value = false;
            }
        };

        // 執行出貨
        const shipOrderItem = async (item) => {
            const confirmMsg = buygoOrderDetailI18n.confirm_ship_item
                .replace('%1$s', `${item.allocated_quantity}`)
                .replace('%2$s', `${item.product_name}`);

            if (!confirm(confirmMsg)) {
                return;
            }

            shipping.value = true;

            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${item.order_id}/ship`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': props.wpNonce
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
                    alert(buygoOrderDetailI18n.ship_success.replace('%s', `${result.shipment_id}`));
                    // 重新載入訂單詳情
                    await loadOrderDetail();
                } else {
                    alert(buygoOrderDetailI18n.ship_failed_prefix.replace('%s', `${result.message}`));
                }
            } catch (err) {
                console.error('出貨失敗:', err);
                alert(buygoOrderDetailI18n.ship_failed_prefix.replace('%s', `${err.message}`));
            } finally {
                shipping.value = false;
            }
        };

        // 點擊外部關閉下拉選單
        onMounted(() => {
            document.addEventListener('click', (e) => {
                if (showShippingDropdown.value && !e.target.closest('.relative')) {
                    showShippingDropdown.value = false;
                }
            });
        });

        // 監聽 orderId 變化，重新載入資料
        watch(() => props.orderId, (newId) => {
            if (newId) {
                orderData.value = null;
                loadOrderDetail();
            }
        }, { immediate: true });

        const cancelChildOrder = async (childOrder) => {
            const orderNo = childOrder.invoice_no || childOrder.id;
            const confirmMsg = buygoOrderDetailI18n.confirm_cancel_child_order.replace('%s', `${orderNo}`);

            const confirmed = await new Promise(resolve => {
                if (!window.confirm(confirmMsg)) {
                    resolve(false);
                } else {
                    resolve(true);
                }
            });

            if (!confirmed) return;

            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/child-orders/${childOrder.id}`, {
                    method: 'DELETE',
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': props.wpNonce }
                });
                const data = await res.json();

                if (data.success) {
                    alert(buygoOrderDetailI18n.child_order_cancelled);
                    await loadOrderDetail();
                } else {
                    alert(buygoOrderDetailI18n.ship_failed_prefix.replace('%s', `${data.message || buygoOrderDetailI18n.unknown_error}`));
                }
            } catch (e) {
                alert(buygoOrderDetailI18n.cancel_failed_retry);
            }
        };

        const removeOrderItem = async (item) => {
            const title = item.title || item.name || item.post_title || '';
            const confirmMsg = buygoOrderDetailI18n.confirm_remove_item.replace('%s', `${title}`);
            const confirmed = window.confirm(confirmMsg);
            if (!confirmed) return;

            let res;
            try {
                res = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderData.value.id}/items/${item.id}`, {
                    method: 'DELETE',
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': props.wpNonce }
                });
            } catch (e) {
                alert(buygoOrderDetailI18n.remove_failed_retry);
                return;
            }

            if (res.ok) {
                await loadOrderDetail();
                alert(buygoOrderDetailI18n.item_removed);
                return;
            }

            try {
                const data = await res.json();
                alert(data.message || buygoOrderDetailI18n.remove_failed);
            } catch (e) {
                alert(buygoOrderDetailI18n.remove_failed_http.replace('%s', `${res.status}`));
            }
        };

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
            showShippingDropdown,
            shippingStatuses,
            toggleShippingDropdown,
            selectShippingStatus,
            getShippingStatusColor,
            getShippingStatusText,
            cancelChildOrder,
            removeOrderItem,
            isSubpage: computed(() => props.isSubpage)
        };
    }
};
</script>
