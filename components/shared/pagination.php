<?php
/**
 * BuyGo 共用分頁組件
 * 基於 products.php 的分頁設計
 */

$pagination_template = <<<'HTML'
<div v-if="total > 0" class="mt-6 flex flex-col sm:flex-row items-center justify-between bg-white px-4 py-3 border border-slate-200 rounded-xl shadow-sm gap-3">
    <div class="text-sm text-slate-700 text-center sm:text-left">
        顯示 <span class="font-medium">{{ startIndex }}</span> 到 <span class="font-medium">{{ endIndex }}</span> 筆，共 <span class="font-medium">{{ total }}</span> 筆
    </div>
    <div class="flex items-center gap-3">
        <!-- Per Page Selector -->
        <select v-model.number="localPerPage" @change="handlePerPageChange" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
            <option v-for="option in perPageOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <!-- Page Navigation -->
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                <span class="sr-only">上一頁</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <button v-for="p in visiblePages" :key="p" @click="goToPage(p)"
                :class="[p === currentPage ? 'z-10 bg-blue-50 border-primary text-primary' : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50', 'relative inline-flex items-center px-4 py-2 border text-sm font-medium']">
                {{ p }}
            </button>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage >= totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                <span class="sr-only">下一頁</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
        </nav>
    </div>
</div>
HTML;
?>

<script>
const BuyGoPagination = {
    name: 'BuyGoPagination',
    template: `<?php echo $pagination_template; ?>`,
    props: {
        total: {
            type: Number,
            required: true
        },
        currentPage: {
            type: Number,
            default: 1
        },
        perPage: {
            type: Number,
            default: 10
        },
        perPageOptions: {
            type: Array,
            default: () => [
                { value: 5, label: '5 / 頁' },
                { value: 10, label: '10 / 頁' },
                { value: 20, label: '20 / 頁' },
                { value: 30, label: '30 / 頁' },
                { value: -1, label: '全部' }
            ]
        },
        maxVisiblePages: {
            type: Number,
            default: 5
        }
    },
    emits: ['update:currentPage', 'update:perPage', 'page-change'],
    setup(props, { emit }) {
        const { ref, computed, watch } = Vue;

        const localPerPage = ref(props.perPage);

        // Watch for external perPage changes
        watch(() => props.perPage, (newVal) => {
            localPerPage.value = newVal;
        });

        const totalPages = computed(() => {
            if (localPerPage.value === -1) return 1;
            return Math.ceil(props.total / localPerPage.value);
        });

        const startIndex = computed(() => {
            if (props.total === 0) return 0;
            if (localPerPage.value === -1) return 1;
            return (props.currentPage - 1) * localPerPage.value + 1;
        });

        const endIndex = computed(() => {
            if (localPerPage.value === -1) return props.total;
            return Math.min(props.currentPage * localPerPage.value, props.total);
        });

        const visiblePages = computed(() => {
            const pages = [];
            const maxPages = Math.min(props.maxVisiblePages, totalPages.value);

            let startPage = Math.max(1, props.currentPage - Math.floor(maxPages / 2));
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

        const goToPage = (page) => {
            if (page < 1 || page > totalPages.value) return;
            emit('update:currentPage', page);
            emit('page-change', { page, perPage: localPerPage.value });
        };

        const handlePerPageChange = () => {
            emit('update:perPage', localPerPage.value);
            emit('update:currentPage', 1);
            emit('page-change', { page: 1, perPage: localPerPage.value });
        };

        return {
            localPerPage,
            totalPages,
            startIndex,
            endIndex,
            visiblePages,
            goToPage,
            handlePerPageChange
        };
    }
};
</script>
