/**
 * Shipment Details Page Component
 * BuyGo+1 Plugin
 *
 * 出貨明細頁面 Vue 組件
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useCurrency (composable)
 * - BuyGoSmartSearchBox (component)
 *
 * Required window variables:
 * - window.buygoWpNonce: WordPress REST API nonce
 */

const ShipmentDetailsPageComponent = {
    name: 'ShipmentDetailsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#shipment-details-page-template',
    setup() {
        const { ref, computed, watch, onMounted } = Vue;

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

        // 載入出貨單列表
        const loadShipments = async () => {
            loading.value = true;
            try {
                // 加入時間戳記強制繞過所有快取
                let url = `/wp-json/buygo-plus-one/v1/shipments?status=${activeTab.value}&page=${currentPage.value}&per_page=${perPage.value}&_t=${Date.now()}`;

                // 加入搜尋參數
                if (searchQuery.value) {
                    url += `&search=${encodeURIComponent(searchQuery.value)}`;
                }

                const response = await fetch(url, {
                    credentials: 'include',
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache',
                        'X-WP-Nonce': wpNonce
                    }
                });
                const result = await response.json();

                if (result.success) {
                    shipments.value = result.data || [];
                    totalShipments.value = result.total || result.data.length;
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
        
        // 顯示標記已出貨確認對話框（含出貨單詳細資訊）
        const showMarkShippedConfirm = (shipment) => {
            const formattedDate = formatDate(shipment.created_at || shipment.shipped_at);
            const message = `
                出貨單號: ${shipment.shipment_number}
                客戶姓名: ${shipment.customer_name || '未知客戶'}
                商品數量: ${shipment.total_quantity || 0} 件
                出貨日期: ${formattedDate}

                ⚠️ 此操作無法撤銷
            `;

            showConfirm(
                '⚠️ 確認出貨',
                message.trim(),
                async () => {
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/shipments/batch-mark-shipped`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpNonce
                            },
                            credentials: 'include',
                            body: JSON.stringify({ shipment_ids: [shipment.id] })
                        });
                        const result = await response.json();

                        if (result.success) {
                            showToast('✓ 出貨單已標記為已出貨', 'success');
                            selectedShipments.value = [];
                            await loadShipments();
                            await loadStats();
                        } else {
                            showToast('✗ 操作失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        showToast('✗ 操作失敗，請稍後再試', 'error');
                    }
                }
            );
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
            } else {
                currentView.value = 'list';
                currentShipmentId.value = null;
            }
        };

        // 導航函數
        const navigateTo = (view, shipmentId = null, updateUrl = true) => {
            currentView.value = view;

            if (shipmentId) {
                currentShipmentId.value = shipmentId;
                loadShipmentDetail(shipmentId);

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo(view, shipmentId);
                }
            } else {
                currentShipmentId.value = null;
                detailModal.value = { show: false, shipment: null, items: [], total: 0 };

                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        // 載入出貨單詳情（供子分頁使用）
        const loadShipmentDetail = async (shipmentId) => {
            try {
                const url = `/wp-json/buygo-plus-one/v1/shipments/${shipmentId}/detail`;
                const response = await fetch(url, {
                    credentials: 'include',
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache',
                        'X-WP-Nonce': wpNonce
                    }
                });
                const result = await response.json();

                if (result.success) {
                    detailModal.value = {
                        show: true,
                        shipment: result.data.shipment,
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

        // 監聽分頁切換，清除勾選
        watch(() => activeTab.value, () => {
            selectedShipments.value = [];
            loadShipments();
        });
        
        onMounted(() => {
            loadShipments();
            loadStats();

            // 檢查 URL 參數（支援直接訪問詳情頁）
            checkUrlParams();

            // 監聽瀏覽器上一頁/下一頁
            window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) {
                    loadShipments();
                    loadStats();
                }
            });

            // 監聽頁面可見性變化
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    loadShipments();
                    loadStats();
                }
            });
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
            showMarkShippedConfirm,
            markShipped,
            archiveShipment,
            viewDetail,
            closeConfirmModal,
            handleConfirm,
            formatDate,
            toggleSelectAll,
            clearSelection,
            batchMarkShipped,
            batchArchive,
            closeDetailModal,
            formatPrice,
            printDetail,
            getCurrencySymbol,
            systemCurrency,
            handleSearchInput,
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
            loadShipmentDetail
        };
    }
};
