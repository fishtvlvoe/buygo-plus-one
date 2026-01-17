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
            <p class="text-slate-600">訂單 ID: {{ orderId }}</p>
            <!-- 這裡預留給詳細內容 -->
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
    template: `<?php echo $order_detail_modal_template; ?>`
};
</script>
