/**
 * useShipmentProducts Composable
 * 備貨管理頁面資料邏輯
 *
 * 功能:
 * - 出貨單列表載入與分頁
 * - 搜尋處理（本地過濾）
 * - 出貨單展開/收合
 * - 批次操作（標記已出貨、合併出貨單）
 * - 轉出貨（pending → ready_to_ship）
 * - 確認 Modal 與 Toast 通知
 * - 預載資料初始化（消除 Loading）
 *
 * 使用方式:
 * const { shipments, loading, loadShipments, ... } = useShipmentProducts();
 *
 * Dependencies:
 * - Vue 3
 * - useCurrency (composable)
 * - BuyGoCache (optional, global)
 *
 * @version 1.0.0
 */
function useShipmentProducts() {
    const { ref, computed, onMounted, watch } = Vue;

    // WordPress REST API nonce（用於 API 認證）
    const wpNonce = window.buygoWpNonce || '';

    // 使用 useCurrency Composable 處理幣別邏輯
    const { formatPrice } = useCurrency();

    // ========================================
    // 狀態變數
    // ========================================
    const shipments = ref([]);
    const loading = ref(false);
    const error = ref(null);

    // 分頁狀態
    const currentPage = ref(1);
    const perPage = ref(5);
    const totalShipments = ref(0);

    // 搜尋狀態
    const searchQuery = ref(null);
    const searchFilter = ref(null);

    // 狀態篩選（與 activeTab 同步）
    const currentStatusFilter = ref('pending');
    const statusFilters = [
        { value: 'all', label: '全部' },
        { value: 'pending', label: '備貨中' },
        { value: 'ready_to_ship', label: '待出貨' },
        { value: 'shipped', label: '已出貨' }
    ];

    // 批次操作
    const selectedItems = ref([]);
    const selectedShipments = ref([]);

    // 展開狀態（用於商品列表展開）
    const expandedShipments = ref(new Set());

    // 確認 Modal 狀態
    const showConfirmModal = ref(false);
    const confirmModal = ref({
        title: '確認操作',
        message: '',
        onConfirm: null
    });

    // Toast 通知狀態
    const toastMessage = ref({
        show: false,
        message: '',
        type: 'success' // 'success' | 'error' | 'info'
    });

    // 全域搜尋狀態
    const showMobileSearch = ref(false);
    const globalSearchQuery = ref('');

    // ========================================
    // 全域搜尋處理
    // ========================================
    const handleGlobalSearch = () => {
        if (globalSearchQuery.value.trim()) {
            // 可以實作跨頁面搜尋邏輯
            // TODO: 實作跨頁面搜尋功能
        }
    };

    // ========================================
    // Toast 通知
    // ========================================
    const showToast = (message, type = 'success') => {
        toastMessage.value = { show: true, message, type };
        setTimeout(() => {
            toastMessage.value.show = false;
        }, 3000);
    };

    // ========================================
    // API 呼叫：載入出貨單列表
    // ========================================
    const loadShipments = async () => {
        loading.value = true;
        error.value = null;

        try {
            // 加入時間戳記強制繞過所有快取
            let url = `/wp-json/buygo-plus-one/v1/shipments?page=${currentPage.value}&per_page=${perPage.value}&status=pending&_t=${Date.now()}`;

            // 加入搜尋參數
            if (searchQuery.value) {
                url += `&search=${encodeURIComponent(searchQuery.value)}`;
            }

            const response = await fetch(url, {
                credentials: 'include',
                cache: 'no-store',  // 防止瀏覽器快取，確保每次都取得最新資料
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache',
                    'X-WP-Nonce': wpNonce
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success && result.data) {
                shipments.value = result.data;
                totalShipments.value = result.total || result.data.length;

                // 儲存到 BuyGoCache
                if (window.BuyGoCache) { window.BuyGoCache.set('shipment-products', result); }
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

    // ========================================
    // 搜尋處理（本地過濾）
    // ========================================
    const handleLocalSearchInput = (query) => {
        searchQuery.value = query;
        currentPage.value = 1;  // 重置到第一頁
        loadShipments();
    };

    const handleLocalSearchSelect = (item) => {
        // 選擇後過濾顯示該出貨單
        if (item && item.shipment_number) {
            searchQuery.value = item.shipment_number;
            currentPage.value = 1;
            loadShipments();
        }
    };

    const handleLocalSearchClear = () => {
        searchQuery.value = null;
        currentPage.value = 1;
        loadShipments();
    };

    // ========================================
    // 格式化函數
    // ========================================
    const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('zh-TW');
    };

    const formatItemsDisplay = (shipment, maxLength = 50) => {
        if (!shipment.items || !Array.isArray(shipment.items) || shipment.items.length === 0) {
            return `0 個項目`;
        }

        // 顯示商品名稱列表
        const itemCount = shipment.items.length;
        const firstItem = shipment.items[0];
        const itemNames = shipment.items.map(item => item.product_name || '未知商品').join('、');

        // 如果只有一個商品,顯示完整名稱
        if (itemCount === 1) {
            return firstItem.product_name || '未知商品';
        }

        // 多個商品時,截斷顯示
        if (itemNames.length <= maxLength) {
            return itemNames;
        }

        return `${firstItem.product_name || '未知商品'} 等 ${itemCount} 項`;
    };

    const getStatusClass = (status) => {
        const statusClasses = {
            'pending': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
            'ready_to_ship': 'bg-orange-100 text-orange-800 border border-orange-200',
            'shipped': 'bg-green-100 text-green-800 border border-green-200',
            'archived': 'bg-slate-100 text-slate-800 border border-slate-200',
            'delivered': 'bg-blue-100 text-blue-800 border border-blue-200'
        };
        return statusClasses[status] || 'bg-slate-100 text-slate-800';
    };

    const getStatusText = (status) => {
        const statusTexts = {
            'pending': '備貨中',
            '備貨中': '備貨中',
            'ready_to_ship': '待出貨',
            'shipped': '已出貨',
            '已出貨': '已出貨',
            'archived': '已存檔',
            'delivered': '已送達'
        };
        return statusTexts[status] || '備貨中';
    };

    // ========================================
    // 展開控制
    // ========================================
    const toggleShipmentExpand = (shipmentId) => {
        if (expandedShipments.value.has(shipmentId)) {
            expandedShipments.value.delete(shipmentId);
        } else {
            expandedShipments.value.add(shipmentId);
        }
    };

    const isShipmentExpanded = (shipmentId) => {
        return expandedShipments.value.has(shipmentId);
    };

    // ========================================
    // 確認 Modal
    // ========================================
    const showConfirm = (message, title = '確認操作', onConfirm = null) => {
        confirmModal.value = {
            title,
            message,
            onConfirm
        };
        showConfirmModal.value = true;
    };

    const executeConfirm = () => {
        if (confirmModal.value.onConfirm) {
            confirmModal.value.onConfirm();
        }
        showConfirmModal.value = false;
    };

    const cancelConfirm = () => {
        showConfirmModal.value = false;
        confirmModal.value.onConfirm = null;
    };

    // ========================================
    // 出貨操作
    // ========================================
    const markShipped = (shipmentId) => {
        showConfirm('確定要標記此出貨單為已出貨嗎？', '確認標記已出貨', async () => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        shipment_ids: [shipmentId]
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('標記成功！', 'success');
                    await loadShipments();
                } else {
                    showToast('標記失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('標記失敗:', err);
                showToast('標記失敗：' + err.message, 'error');
            }
        });
    };

    const batchMarkShipped = () => {
        if (selectedShipments.value.length === 0) {
            return;
        }

        showConfirm(
            `確定要標記 ${selectedShipments.value.length} 個出貨單為已出貨嗎？`,
            '批次標記已出貨',
            async () => {
                try {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            shipment_ids: selectedShipments.value
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        showToast(`成功標記 ${result.count} 個出貨單為已出貨！`, 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                    } else {
                        showToast('標記失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    console.error('批次標記失敗:', err);
                    showToast('標記失敗：' + err.message, 'error');
                }
            }
        );
    };

    const moveToShipment = async (shipmentId) => {
        showConfirm(
            '確認轉出貨',
            '確定要將此出貨單轉為待出貨嗎？轉出貨後將出現在「出貨」頁面。',
            async () => {
                try {
                    const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/transfer`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include'
                    });

                    const result = await response.json();

                    if (result.success) {
                        showToast('已轉為待出貨', 'success');
                        await loadShipments();
                    } else {
                        showToast('轉出貨失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    showToast('轉出貨失敗', 'error');
                }
            }
        );
    };

    const mergeShipments = async () => {
        if (!canMerge.value) {
            showToast('只能合併相同客戶的出貨單', 'error');
            return;
        }

        showConfirm(
            '確認合併出貨單',
            `確定要合併 ${selectedShipments.value.length} 個出貨單嗎？`,
            async () => {
                try {
                    const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/merge', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            shipment_ids: selectedShipments.value
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        showToast('合併成功！', 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                    } else {
                        showToast('合併失敗：' + (result.message || '未知錯誤'), 'error');
                    }
                } catch (err) {
                    console.error('合併失敗:', err);
                    showToast('合併失敗：' + err.message, 'error');
                }
            }
        );
    };

    // ========================================
    // 全選邏輯
    // ========================================
    const toggleSelectAll = (event) => {
        if (event.target.checked) {
            selectedShipments.value = shipments.value.map(s => s.id);
        } else {
            selectedShipments.value = [];
        }
    };

    const isAllSelected = computed(() => {
        return shipments.value.length > 0 &&
               selectedShipments.value.length === shipments.value.length;
    });

    const canMerge = computed(() => {
        if (selectedShipments.value.length < 2) return false;

        // 取得所有選中出貨單的客戶 ID
        const customerIds = selectedShipments.value.map(id => {
            const shipment = shipments.value.find(s => s.id === id);
            return shipment?.customer_id;
        });

        // 檢查是否都是同一個客戶
        return customerIds.every(id => id === customerIds[0]);
    });

    // ========================================
    // 分頁邏輯
    // ========================================
    const totalPages = computed(() => {
        if (perPage.value === -1) return 1;
        return Math.ceil(totalShipments.value / perPage.value);
    });

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

    // ========================================
    // 預載初始化
    // ========================================
    const initFromPreloadedData = () => {
        const preloaded = window.buygoInitialData?.shipments;
        if (!preloaded || !preloaded.success || !preloaded.data) return false;

        // 備貨商品頁只顯示 pending 狀態，預注入的是全部資料，需要過濾
        const pendingShipments = preloaded.data.filter(s => s.status === 'pending');
        shipments.value = pendingShipments;
        totalShipments.value = pendingShipments.length;
        loading.value = false;
        // 寫入快取，讓 preload 失敗時有 fallback
        if (window.BuyGoCache) { window.BuyGoCache.set('shipment-products', preloaded); }
        delete window.buygoInitialData?.shipments;
        return true;
    };

    // ========================================
    // 生命週期
    // ========================================
    onMounted(() => {
        if (!initFromPreloadedData()) {
            // 快取 fallback：使用 sessionStorage 快取加速重複訪問
            const cached = window.BuyGoCache && window.BuyGoCache.get('shipment-products');
            if (cached && cached.success && cached.data) {
                const pendingShipments = cached.data.filter(s => s.status === 'pending');
                shipments.value = pendingShipments;
                totalShipments.value = pendingShipments.length;
                loading.value = false;
                // 背景靜默刷新
                loadShipments();
            } else {
                loadShipments();
            }
        }

        // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) {
                loadShipments();
            }
        });

        // 監聯頁面可見性變化
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                loadShipments();
            }
        });
    });

    // 幣別切換處理（Header 元件會呼叫此方法）
    const onCurrencyChange = (newCurrency) => {
        console.log('[ShipmentProductsPage] 幣別變更:', newCurrency);
        currentCurrency.value = newCurrency;
    };

    // ========================================
    // 回傳所有公開的狀態和方法
    // ========================================
    return {
        // 狀態
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
        formatPrice,
        getStatusClass,
        getStatusText,
        toggleShipmentExpand,
        isShipmentExpanded,
        expandedShipments,
        currentStatusFilter,
        statusFilters,
        markShipped,
        batchMarkShipped,
        toggleSelectAll,
        selectedItems,
        selectedShipments,
        isAllSelected,
        canMerge,
        moveToShipment,
        mergeShipments,
        loadShipments,

        // 全域搜尋
        showMobileSearch,
        globalSearchQuery,
        handleGlobalSearch,

        // Smart Search Box 事件處理
        handleSearchInput: handleLocalSearchInput,
        handleSearchSelect: handleLocalSearchSelect,
        handleSearchClear: handleLocalSearchClear,

        // Modal 和 Toast
        showConfirmModal,
        confirmModal,
        showConfirm,
        executeConfirm,
        cancelConfirm,
        toastMessage,
        showToast,

        // 幣別切換
        onCurrencyChange
    };
}
