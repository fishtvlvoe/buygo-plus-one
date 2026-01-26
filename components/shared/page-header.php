<?php
// Page Header Component
// 整合頁面標題、描述、篩選狀態、操作按鈕和搜尋區域
$page_header_component = <<<'HTML'
const PageHeader = {
    props: {
        title: {
            type: String,
            required: true
        },
        description: {
            type: String,
            default: ''
        },
        // 篩選狀態
        showSearchFilter: {
            type: Boolean,
            default: false
        },
        searchFilterName: {
            type: String,
            default: ''
        },
        // 是否顯示重新整理按鈕
        showRefresh: {
            type: Boolean,
            default: false
        }
    },
    emits: ['clear-filter', 'refresh'],
    template: `
    <div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <!-- 標題與描述 -->
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-slate-900 mb-1 font-title">{{ title }}</h1>
                        <button 
                            v-if="showRefresh"
                            @click="$emit('refresh')"
                            class="p-1.5 text-slate-400 hover:text-primary hover:bg-blue-50 rounded-full transition"
                            title="重新整理"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                    </div>
                    <p v-if="description" class="text-sm text-slate-500">{{ description }}</p>
                    
                    <!-- 篩選提示標籤 -->
                    <div v-if="showSearchFilter" class="mt-2 flex items-center gap-2 animate-fade-in">
                        <span class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded-full border border-blue-200 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            篩選：{{ searchFilterName }}
                        </span>
                        <button 
                            @click="$emit('clear-filter')"
                            class="text-xs text-slate-500 hover:text-red-500 underline transition-colors flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            清除篩選
                        </button>
                    </div>
                </div>
                
                <!-- 右側操作按鈕區（Slot） -->
                <div class="flex items-center gap-3">
                    <slot name="actions"></slot>
                </div>
            </div>
            
            <!-- 底部主要操作區（通常放搜尋框） -->
            <div class="w-full">
                <slot name="bottom"></slot>
            </div>
        </div>
    </div>
    `
};
HTML;
?>
<script>
    <?php echo $page_header_component; ?>
</script>
