/**
 * useShipmentDetails Composable
 * 出貨明細頁面資料邏輯
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useCurrency (composable)
 * - flatpickr (global, CDN)
 *
 * Required window variables:
 * - window.buygoWpNonce: WordPress REST API nonce
 */
function useShipmentDetails() {
    const { ref, computed, watch, onMounted, onUnmounted, nextTick } = Vue;

    // WordPress Nonce for API authentication
    const wpNonce = window.buygoWpNonce || '';

    // 使用 useCurrency Composable 處理幣別邏輯
    const { formatPrice, getCurrencySymbol, systemCurrency } = useCurrency();
    const activeTab = ref('ready_to_ship');
    const shipments = ref([]);
    const loading = ref(false);
    const stats = ref({ ready_to_ship: 0, shipped: 0, archived: 0 });

    // ============================================
    // 路由狀態（子分頁切換）
    // ============================================
    const currentView = ref('list');  // 'list' | 'detail'
    const currentShipmentId = ref(null);
    
    // 勾選狀態
    const selectedShipments = ref([]);
    
    // Modal 狀態
    const confirmModal = ref({ show: false, title: '', message: '', onConfirm: null });
    const toastMessage = ref({ show: false, message: '', type: 'success' });
    // 標記出貨子頁面資料
    const markShippedData = ref({
        shipment: null,
        items: [],
        total: 0,
        estimated_delivery_date: '',
        shipping_method: '',
        loading: false
    });

    // 物流下拉選單狀態
    const showShippingMethodDropdown = ref(false);
    const dropdownPosition = ref('bottom'); // 'bottom' 向下展開 | 'top' 向上展開

    // 物流方式選項（8 個物流公司 + 彩虹配色）
    const shippingMethods = [
        { value: '易利', label: '易利', color: 'bg-red-100 text-red-800 border border-red-300' },
        { value: '千森', label: '千森', color: 'bg-orange-100 text-orange-800 border border-orange-300' },
        { value: 'OMI', label: 'OMI', color: 'bg-yellow-100 text-yellow-800 border border-yellow-300' },
        { value: '多賀', label: '多賀', color: 'bg-green-100 text-green-800 border border-green-300' },
        { value: '賀來', label: '賀來', color: 'bg-blue-100 text-blue-800 border border-blue-300' },
        { value: '神奈川', label: '神奈川', color: 'bg-indigo-100 text-indigo-800 border border-indigo-300' },
        { value: '新日本', label: '新日本', color: 'bg-purple-100 text-purple-800 border border-purple-300' },
        { value: 'EMS', label: 'EMS', color: 'bg-pink-100 text-pink-800 border border-pink-300' }
    ];

    // 詳情 Modal 狀態
    const detailModal = ref({
        show: false,
        shipment: null,
        items: [],
        total: 0
    });

    // 分頁狀態
    const currentPage = ref(1);
    const perPage = ref(5);
    const totalShipments = ref(0);

    // 搜尋狀態
    const searchQuery = ref(null);
    const searchFilter = ref(null);

    // Flatpickr ref
    const estimatedDeliveryInput = ref(null);
    let flatpickrInstance = null;

    // 載入出貨單列表
    const loadShipments = async (options = {}) => {
        // silent 模式：背景刷新時不顯示 loading skeleton，避免切頁閃爍
        if (!options.silent) loading.value = true;
        try {
            // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
            let url = `/wp-json/buygo-plus-one/v1/shipments?status=${activeTab.value}&page=${currentPage.value}&per_page=${perPage.value}`;

            // 加入搜尋參數
            if (searchQuery.value) {
                url += `&search=${encodeURIComponent(searchQuery.value)}`;
            }

            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': wpNonce
                }
            });
            const result = await response.json();

            if (result.success) {
                shipments.value = result.data || [];
                totalShipments.value = result.total || result.data.length;

                // 儲存到 BuyGoCache
                if (window.BuyGoCache) { window.BuyGoCache.set('shipment-details', result); }
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
            const statuses = ['ready_to_ship', 'shipped', 'archived'];
            for (const status of statuses) {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments?status=${status}&per_page=1`, {
                    credentials: 'include',
                    headers: {
                        'X-WP-Nonce': wpNonce
                    }
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
    
    // 顯示標記出貨子頁面
    const showMarkShippedConfirm = (shipment) => {
        navigateTo('shipment-mark', shipment.id);
    };

    // 載入標記出貨子頁面資料
    const loadMarkShippedData = async (shipmentId) => {
        markShippedData.value.loading = true;
        try {
            const url = `/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/detail`;
            // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': wpNonce
                }
            });
            const result = await response.json();

            if (result.success) {
                markShippedData.value = {
                    shipment: result.data.shipment,
                    items: result.data.items,
                    total: result.data.items.reduce((sum, item) => sum + (item.quantity * item.price), 0),
                    estimated_delivery_date: '',
                    loading: false
                };
            } else {
                showToast('載入出貨單資料失敗：' + result.message, 'error');
                navigateTo('list');
            }
        } catch (err) {
            console.error('載入出貨單資料失敗:', err);
            showToast('載入出貨單資料失敗', 'error');
            navigateTo('list');
        }
    };

    // 確認標記已出貨（從子頁面執行）
    const confirmMarkShipped = async () => {
        const shipment = markShippedData.value.shipment;
        const estimatedDeliveryDate = markShippedData.value.estimated_delivery_date;
        const shippingMethod = markShippedData.value.shipping_method;

        if (!shipment) {
            navigateTo('list');
            return;
        }

        markShippedData.value.loading = true;

        try {
            // 準備 API 請求資料
            const requestData = {
                shipment_ids: [shipment.id]
            };

            // 如果有設定預計送達時間，加入請求資料（轉換為 MySQL DATETIME 格式）
            if (estimatedDeliveryDate) {
                requestData.estimated_delivery_at = estimatedDeliveryDate + ' 00:00:00';
            }

            // 如果有設定物流方式，加入請求資料
            if (shippingMethod) {
                requestData.shipping_method = shippingMethod;
            }

            const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpNonce
                },
                credentials: 'include',
                body: JSON.stringify(requestData)
            });
            const result = await response.json();

            if (result.success) {
                showToast('✓ 出貨單已標記為已出貨', 'success');
                selectedShipments.value = [];
                // 返回列表頁
                navigateTo('list');
                await loadShipments();
                await loadStats();
            } else {
                showToast('✗ 操作失敗：' + result.message, 'error');
            }
        } catch (err) {
            console.error('標記出貨失敗:', err);
            showToast('✗ 操作失敗，請稍後再試', 'error');
        } finally {
            markShippedData.value.loading = false;
        }
    };

    // 標記已出貨（保留原有函數供批次操作使用）
    const markShipped = (shipmentId) => {
        // 從列表中找到對應的出貨單
        const shipment = shipments.value.find(s => s.id === shipmentId);
        if (shipment) {
            showMarkShippedConfirm(shipment);
        }
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
                        credentials: 'include',
                        headers: {
                            'X-WP-Nonce': wpNonce
                        }
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('已移至存檔區', 'success');
                        selectedShipments.value = [];
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
    
    // 是否全選
    const isAllSelected = computed(() => {
        return shipments.value.length > 0 && 
               selectedShipments.value.length === shipments.value.length;
    });

    // 切換全選
    const toggleSelectAll = (event) => {
        if (event.target.checked) {
            selectedShipments.value = shipments.value.map(s => s.id);
        } else {
            selectedShipments.value = [];
        }
    };

    // 清除勾選
    const clearSelection = () => {
        selectedShipments.value = [];
    };

    // 分頁處理函數
    const changePerPage = () => {
        currentPage.value = 1; // 重置到第一頁
        loadShipments();
    };

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

    // 計算屬性：總頁數
    const totalPages = computed(() => {
        return Math.ceil(totalShipments.value / perPage.value);
    });

    // 計算可見頁碼
    const visiblePages = computed(() => {
        const pages = [];
        const maxPages = Math.min(5, totalPages.value);
        let startPage = Math.max(1, currentPage.value - Math.floor(maxPages / 2));
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

    // 跳轉到指定頁
    const goToPage = (page) => {
        if (page < 1 || page > totalPages.value) return;
        currentPage.value = page;
        loadShipments();
    };

    // 批次標記已出貨
    const batchMarkShipped = () => {
        if (selectedShipments.value.length === 0) {
            showToast('請先選擇出貨單', 'error');
            return;
        }
        
        showConfirm(
            '確認批次標記已出貨',
            `確定要將 ${selectedShipments.value.length} 個出貨單標記為已出貨嗎？`,
            async () => {
                try {
                    const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({ shipment_ids: selectedShipments.value })
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('批次標記成功！', 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                        await loadStats();
                    } else {
                        showToast('批次標記失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    console.error('批次標記失敗:', err);
                    showToast('批次標記失敗', 'error');
                }
            }
        );
    };

    // 批次移至存檔
    const batchArchive = () => {
        if (selectedShipments.value.length === 0) {
            showToast('請先選擇出貨單', 'error');
            return;
        }
        
        showConfirm(
            '確認批次移至存檔',
            `確定要將 ${selectedShipments.value.length} 個出貨單移至存檔區嗎？`,
            async () => {
                try {
                    const response = await fetch('/wp-json/buygo-plus-one/v1/shipments/batch-archive', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpNonce
                        },
                        credentials: 'include',
                        body: JSON.stringify({ shipment_ids: selectedShipments.value })
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('批次移至存檔成功！', 'success');
                        selectedShipments.value = [];
                        await loadShipments();
                        await loadStats();
                    } else {
                        showToast('批次移至存檔失敗：' + result.message, 'error');
                    }
                } catch (err) {
                    console.error('批次移至存檔失敗:', err);
                    showToast('批次移至存檔失敗', 'error');
                }
            }
        );
    };

    // 合併顯示開關
    const mergeEnabled = ref(true);

    // 合併同商品顯示（按 product_id 歸組，數量和金額加總）
    const mergeItemsByProduct = (items) => {
        if (!items || items.length === 0) return [];
        const map = {};
        items.forEach(item => {
            const pid = item.product_id;
            if (map[pid]) {
                map[pid].quantity += Number(item.quantity);
            } else {
                map[pid] = { ...item, quantity: Number(item.quantity) };
            }
        });
        return Object.values(map);
    };

    const mergedDetailItems = computed(() =>
        mergeEnabled.value ? mergeItemsByProduct(detailModal.value.items) : detailModal.value.items
    );
    const mergedMarkShippedItems = computed(() =>
        mergeEnabled.value ? mergeItemsByProduct(markShippedData.value.items) : markShippedData.value.items
    );

    // 匯出單張出貨單
    const exportShipment = async (shipmentId) => {
        if (!shipmentId) {
            showToast('出貨單 ID 無效', 'error');
            return;
        }

        try {
            // 建立 URL（使用 GET 參數 + nonce 驗證）
            const url = `/wp-json/buygo-plus-one/v1/shipments/export?shipment_ids=${shipmentId}&_wpnonce=${wpNonce}`;

            // 直接開啟 URL（瀏覽器會自動下載檔案）
            window.location.href = url;

            showToast('正在匯出...', 'info');
        } catch (err) {
            console.error('匯出失敗:', err);
            showToast('匯出失敗：' + err.message, 'error');
        }
    };

    // 批次匯出（參考舊外掛，使用 GET 請求直接開啟 URL）
    const batchExport = () => {
        if (selectedShipments.value.length === 0) {
            showToast('請先選擇出貨單', 'error');
            return;
        }

        try {
            // 建立 URL（使用 GET 參數傳遞 shipment_ids + nonce 驗證）
            const ids = selectedShipments.value.join(',');
            const url = `/wp-json/buygo-plus-one/v1/shipments/export?shipment_ids=${ids}&_wpnonce=${wpNonce}`;

            // 直接開啟 URL（瀏覽器會自動下載檔案）
            window.location.href = url;

            showToast(`正在匯出 ${selectedShipments.value.length} 個出貨單...`, 'info');
        } catch (err) {
            console.error('批次匯出失敗:', err);
            showToast('批次匯出失敗：' + err.message, 'error');
        }
    };

    // 查看詳情（改為使用子分頁）
    const viewDetail = (shipmentId) => {
        openShipmentDetail(shipmentId);
    };

    // 關閉詳情（改為使用子分頁）
    const closeDetailModal = () => {
        closeShipmentDetail();
    };

    // ============================================
    // 路由邏輯（子分頁切換）
    // ============================================

    // 檢查 URL 參數
    const checkUrlParams = () => {
        const params = window.BuyGoRouter.checkUrlParams();
        const { view, id } = params;

        if (view === 'detail' && id) {
            currentView.value = 'detail';
            currentShipmentId.value = id;
            loadShipmentDetail(id);
        } else if (view === 'shipment-mark' && id) {
            currentView.value = 'shipment-mark';
            currentShipmentId.value = id;
            loadMarkShippedData(id);
        } else {
            currentView.value = 'list';
            currentShipmentId.value = null;
        }
    };

    // 導航函數
    const navigateTo = (view, shipmentId = null, updateUrl = true) => {
        currentView.value = view;

        if (view === 'shipment-mark' && shipmentId) {
            currentShipmentId.value = shipmentId;
            loadMarkShippedData(shipmentId);

            if (updateUrl) {
                window.BuyGoRouter.navigateTo(view, shipmentId);
            }
        } else if (view === 'detail' && shipmentId) {
            currentShipmentId.value = shipmentId;
            loadShipmentDetail(shipmentId);

            if (updateUrl) {
                window.BuyGoRouter.navigateTo(view, shipmentId);
            }
        } else {
            currentShipmentId.value = null;
            detailModal.value = { show: false, shipment: null, items: [], total: 0 };
            markShippedData.value = { shipment: null, items: [], total: 0, estimated_delivery_date: '', loading: false };

            if (updateUrl) {
                window.BuyGoRouter.goToList();
            }
        }
    };

    // 載入出貨單詳情（供子分頁使用）
    const loadShipmentDetail = async (shipmentId) => {
        try {
            const url = `/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/detail`;
            // 使用 BuyGoCache 快取層，不再強制繞過瀏覽器快取
            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': wpNonce
                }
            });
            const result = await response.json();

            if (result.success) {
                // 將 estimated_delivery_at 轉換為 date input 格式
                const shipmentData = result.data.shipment;
                // 無論有無值都初始化，避免 v-model 綁定 undefined（null 時設空字串）
                shipmentData.estimated_delivery_date = shipmentData.estimated_delivery_at
                    ? formatDateForInput(shipmentData.estimated_delivery_at)
                    : '';

                detailModal.value = {
                    show: true,
                    shipment: shipmentData,
                    items: result.data.items,
                    total: result.data.items.reduce((sum, item) => sum + (item.quantity * item.price), 0)
                };
            } else {
                showToast('載入詳情失敗：' + result.message, 'error');
            }
        } catch (err) {
            console.error('載入詳情失敗:', err);
            showToast('載入詳情失敗', 'error');
        }
    };

    // 開啟出貨單詳情
    const openShipmentDetail = (shipmentId) => {
        navigateTo('detail', shipmentId);
    };

    // 關閉出貨單詳情
    const closeShipmentDetail = () => {
        navigateTo('list');
    };

    // 列印收據
    const printDetail = () => {
        window.print();
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

    // 取得今天日期（YYYY-MM-DD 格式，用於 date input 的 min 屬性）
    // 用本地時間而非 toISOString()（UTC），避免台灣時區在午夜前後顯示昨天
    const getTodayDate = () => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    };

    // 取得當前日期時間（用於出貨時間顯示）
    const getCurrentDateTime = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    };

    // 將 MySQL datetime 轉換為 date input 可用格式（YYYY-MM-DD）
    const formatDateForInput = (datetime) => {
        if (!datetime) return '';
        return datetime.split(' ')[0]; // 取 YYYY-MM-DD 部分
    };

    // 儲存出貨單的預計送達時間（供已出貨詳情頁面編輯使用）
    const saveShipment = async (shipmentId) => {
        if (!shipmentId || !detailModal.value.shipment) return;

        const estimatedDeliveryDate = detailModal.value.shipment.estimated_delivery_date || '';
        // 記錄儲存前的舊值，失敗時還原
        const previousDeliveryAt = detailModal.value.shipment.estimated_delivery_at;
        const previousDeliveryDate = estimatedDeliveryDate;

        try {
            const requestData = {};
            if (estimatedDeliveryDate) {
                requestData.estimated_delivery_at = estimatedDeliveryDate + ' 00:00:00';
            } else {
                requestData.estimated_delivery_at = null;
            }

            const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/${shipmentId}`, {
                method: 'PUT',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpNonce
                },
                body: JSON.stringify(requestData)
            });
            const result = await response.json();

            if (result.success) {
                showToast('預計送達時間已儲存');
                if (detailModal.value.shipment) {
                    detailModal.value.shipment.estimated_delivery_at = requestData.estimated_delivery_at;
                }
            } else {
                // 還原前端顯示，避免前後端不同步
                if (detailModal.value.shipment) {
                    detailModal.value.shipment.estimated_delivery_at = previousDeliveryAt;
                    detailModal.value.shipment.estimated_delivery_date = previousDeliveryDate;
                }
                showToast(result.message || '儲存失敗', 'error');
            }
        } catch (err) {
            // 網路錯誤也還原前端顯示
            if (detailModal.value.shipment) {
                detailModal.value.shipment.estimated_delivery_at = previousDeliveryAt;
                detailModal.value.shipment.estimated_delivery_date = previousDeliveryDate;
            }
            console.error('儲存出貨單失敗:', err);
            showToast('儲存失敗', 'error');
        }
    };

    // 物流下拉選單控制（智慧展開：判斷向上或向下）
    const toggleShippingMethodDropdown = (event) => {
        if (!showShippingMethodDropdown.value) {
            // 計算空間決定展開方向
            const button = event.currentTarget;
            const rect = button.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            const dropdownHeight = 8 * 48; // 8 個選項 × 每個約 48px

            // 決定展開方向：優先向下，空間不足才向上
            dropdownPosition.value = spaceBelow >= dropdownHeight ? 'bottom' : 'top';
        }
        showShippingMethodDropdown.value = !showShippingMethodDropdown.value;
    };

    const selectShippingMethod = (value) => {
        markShippedData.value.shipping_method = value;
        showShippingMethodDropdown.value = false;
    };

    const getShippingMethodColor = (method) => {
        const methodObj = shippingMethods.find(m => m.value === method);
        return methodObj ? methodObj.color : 'bg-slate-100 text-slate-800 border border-slate-300';
    };

    // 智慧搜尋處理
    const handleSearchInput = (query) => {
        // 本地搜尋處理函數（輸入時過濾列表）
        searchQuery.value = query;
        currentPage.value = 1;  // 重置到第一頁
        loadShipments();
    };

    const handleSearchSelect = (item) => {
        // 搜尋選中項目後的處理
        if (item && item.id) {
            viewDetail(item.id);
        }
    };

    const handleSearchClear = () => {
        // 清除搜尋後重新載入列表
        searchQuery.value = null;
        currentPage.value = 1;
        loadShipments();
    };

    // Header 幣別切換處理（避免 Vue 警告）
    const onCurrencyChange = (newCurrency) => {
        // 出貨頁面不需要幣別切換功能，此方法僅為滿足 header-component 需求
        console.log('Currency change event received:', newCurrency);
    };

    // 監聽分頁切換，清除勾選
    watch(() => activeTab.value, () => {
        selectedShipments.value = [];
        loadShipments();
    });

    // 監聽標記出貨頁面切換，初始化 Flatpickr
    watch(() => currentView.value, (newView) => {
        if (newView === 'shipment-mark') {
            // 延遲初始化，確保 DOM 已渲染
            nextTick(() => {
                // 銷毀舊的 Flatpickr 實例
                if (flatpickrInstance) {
                    flatpickrInstance.destroy();
                }

                // 初始化新的 Flatpickr 實例
                if (estimatedDeliveryInput.value && typeof flatpickr !== 'undefined') {
                    // 檢測是否為手機裝置
                    const isMobile = window.innerWidth < 768;

                    flatpickrInstance = flatpickr(estimatedDeliveryInput.value, {
                        dateFormat: "Y-m-d",
                        minDate: "today",
                        locale: typeof flatpickr.l10ns !== 'undefined' && flatpickr.l10ns.zh_tw ? flatpickr.l10ns.zh_tw : "default",
                        disableMobile: true,  // 關鍵：禁用原生日期選擇器，強制使用 Flatpickr
                        appendTo: document.body,  // 附加到 body，讓 CSS 生效
                        positionElement: isMobile ? undefined : estimatedDeliveryInput.value,  // 手機版不固定位置
                        onChange: (selectedDates, dateStr) => {
                            markShippedData.value.estimated_delivery_date = dateStr;
                        }
                    });
                }
            });
        } else {
            // 離開標記出貨頁面時銷毀 Flatpickr
            if (flatpickrInstance) {
                flatpickrInstance.destroy();
                flatpickrInstance = null;
            }
        }
    });
    
    // 預注入資料初始化（消除 Loading 畫面）
    const initFromPreloadedData = () => {
        const preloaded = window.buygoInitialData?.shipments;
        if (!preloaded || !preloaded.success || !preloaded.data) return false;

        // 出貨明細頁按 activeTab 過濾（預設 ready_to_ship）
        const filtered = preloaded.data.filter(s => s.status === activeTab.value);
        shipments.value = filtered;
        totalShipments.value = filtered.length;
        // 統計各狀態數量
        const allData = preloaded.data;
        stats.value = {
            ready_to_ship: allData.filter(s => s.status === 'ready_to_ship').length,
            shipped: allData.filter(s => s.status === 'shipped').length,
            archived: allData.filter(s => s.status === 'archived').length
        };
        loading.value = false;
        // 寫入快取，讓 preload 失敗時有 fallback
        if (window.BuyGoCache) { window.BuyGoCache.set('shipment-details', preloaded); }
        delete window.buygoInitialData?.shipments;
        return true;
    };

    // ========================================
    // 具名 Event Handler（供 onMounted/onUnmounted 配對使用）
    // ========================================
    let removePopstateListenerShipDetails = null;

    const handlePageshowShipDetails = (e) => {
        if (e.persisted) {
            loadShipments();
            loadStats();
        }
    };
    const handleVisibilityChangeShipDetails = () => {
        if (document.visibilityState === 'visible') {
            if (window.BuyGoCache && window.BuyGoCache.isFresh && window.BuyGoCache.isFresh('shipment-details')) {
                return;
            }
            loadShipments();
            loadStats();
        }
    };
    const handleDocClickShipDetails = (e) => {
        if (showShippingMethodDropdown.value && !e.target.closest('.relative')) {
            showShippingMethodDropdown.value = false;
        }
    };

    onMounted(() => {
        if (!initFromPreloadedData()) {
            // 快取 fallback：使用 sessionStorage 快取加速重複訪問
            const cached = window.BuyGoCache && window.BuyGoCache.get('shipment-details');
            if (cached && cached.success && cached.data) {
                const filtered = cached.data.filter(s => s.status === activeTab.value);
                shipments.value = filtered;
                totalShipments.value = filtered.length;
                const allData = cached.data;
                stats.value = {
                    ready_to_ship: allData.filter(s => s.status === 'ready_to_ship').length,
                    shipped: allData.filter(s => s.status === 'shipped').length,
                    archived: allData.filter(s => s.status === 'archived').length
                };
                loading.value = false;
                // 背景靜默刷新（silent 模式：不顯示 loading skeleton）
                if (!window.BuyGoCache || !window.BuyGoCache.isFresh('shipment-details')) {
                    loadShipments({ silent: true });
                }
                loadStats();
            } else {
                loadShipments();
                loadStats();
            }
        }

        // 檢查 URL 參數（支援直接訪問詳情頁）
        checkUrlParams();

        // 監聽瀏覽器上一頁/下一頁（儲存 cleanup 函式）
        removePopstateListenerShipDetails = window.BuyGoRouter.setupPopstateListener(checkUrlParams);

        // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
        window.addEventListener('pageshow', handlePageshowShipDetails);

        // 監聽頁面可見性變化
        // SWR 策略：快取新鮮時不重新載入，避免切分頁回來時 Loading 閃爍
        document.addEventListener('visibilitychange', handleVisibilityChangeShipDetails);

        // 點擊外部關閉物流下拉選單
        document.addEventListener('click', handleDocClickShipDetails);
    });

    // SPA 清理：移除所有 event listener + flatpickr，防止記憶體洩漏
    onUnmounted(() => {
        if (removePopstateListenerShipDetails) removePopstateListenerShipDetails();
        window.removeEventListener('pageshow', handlePageshowShipDetails);
        document.removeEventListener('visibilitychange', handleVisibilityChangeShipDetails);
        document.removeEventListener('click', handleDocClickShipDetails);
        // 清理 flatpickr instance
        if (flatpickrInstance) {
            flatpickrInstance.destroy();
            flatpickrInstance = null;
        }
    });

    return {
        activeTab,
        shipments,
        loading,
        stats,
        selectedShipments,
        isAllSelected,
        confirmModal,
        toastMessage,
        detailModal,
        markShippedData,
        showMarkShippedConfirm,
        loadMarkShippedData,
        confirmMarkShipped,
        markShipped,
        archiveShipment,
        viewDetail,
        closeConfirmModal,
        handleConfirm,
        formatDate,
        getTodayDate,
        getCurrentDateTime,
        formatDateForInput,
        toggleSelectAll,
        clearSelection,
        batchMarkShipped,
        batchArchive,
        closeDetailModal,
        formatPrice,
        printDetail,
        getCurrencySymbol,
        systemCurrency,
        // 搜尋相關
        searchQuery,
        globalSearchQuery: searchQuery,  // 別名給 template 使用
        handleSearchInput,
        handleGlobalSearch: handleSearchInput,  // 別名給 template 使用
        handleSearchSelect,
        handleSearchClear,
        showToast,
        // 匯出功能
        exportShipment,
        batchExport,
        // 分頁相關
        currentPage,
        perPage,
        totalShipments,
        totalPages,
        visiblePages,
        changePerPage,
        previousPage,
        nextPage,
        goToPage,
        // 路由相關（子分頁切換）
        currentView,
        currentShipmentId,
        navigateTo,
        checkUrlParams,
        openShipmentDetail,
        closeShipmentDetail,
        loadShipmentDetail,
        saveShipment,
        // 物流下拉選單相關
        showShippingMethodDropdown,
        dropdownPosition,
        shippingMethods,
        toggleShippingMethodDropdown,
        selectShippingMethod,
        getShippingMethodColor,
        // Flatpickr ref
        estimatedDeliveryInput,
        // 合併顯示
        mergeEnabled,
        mergedDetailItems,
        mergedMarkShippedItems,
        // Header 事件處理
        onCurrencyChange
    };
}
