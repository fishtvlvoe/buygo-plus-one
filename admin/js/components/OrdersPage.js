/**
 * Orders Page Component
 * BuyGo+1 Plugin
 *
 * 訂單管理頁面 Vue 組件
 *
 * Dependencies:
 * - Vue 3
 * - BuyGoRouter (global)
 * - useCurrency (composable)
 * - BuyGoSmartSearchBox (component)
 * - OrderDetailModal (component)
 *
 * Required window variables:
 * - window.buygoWpNonce: WordPress REST API nonce
 */

const OrdersPageComponent = {
    name: 'OrdersPage',
    components: {
        'order-detail-modal': OrderDetailModal,
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#orders-page-template',
    setup() {
        const { ref, computed, onMounted, watch } = Vue;

        // WordPress REST API nonce（用於 API 認證）
        const wpNonce = window.buygoWpNonce || '';

        // 使用 useCurrency Composable 處理幣別邏輯
        const {
            formatPrice: formatCurrency,
            formatPriceWithConversion,
            systemCurrency: systemCurrencyFromComposable,
            currencySymbols,
            exchangeRates
        } = useCurrency();

        // ============================================
        // 路由狀態（使用 BuyGoRouter 核心模組）
        // ============================================
        const currentView = ref('list');  // 'list' | 'detail'
        const currentOrderId = ref(null);

        // UI 狀態
        const showMobileSearch = ref(false);

        // 狀態變數
        const orders = ref([]);
        const loading = ref(false);
        const error = ref(null);

        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalOrders = ref(0);

        // 搜尋篩選狀態
        const searchFilter = ref(null);
        const searchFilterName = ref('');
        const searchQuery = ref('');

        // 狀態篩選（新增）
        const filterStatus = ref(null); // null (全部) | 'unshipped' | 'preparing' | 'shipped'
        const stats = ref({
            total: 0,
            unshipped: 0,
            preparing: 0,
            shipped: 0
        });

        // 幣別設定 - 使用 composable 的系統幣別
        const systemCurrency = systemCurrencyFromComposable; // 直接使用全域 ref
        const currentCurrency = ref(systemCurrencyFromComposable.value);

        // 監聽全域幣別變化
        watch(systemCurrency, (newCurrency) => {
            console.log('[OrdersPage] 偵測到幣別變化:', newCurrency);
            currentCurrency.value = newCurrency;
        });

        // 批次轉備貨
        const batchPrepare = async () => {
            if (selectedItems.value.length === 0) return;

            // 收集要處理的訂單（考慮父子訂單關係）
            // 如果父訂單有子訂單，應該處理子訂單而非父訂單
            const ordersToProcess = [];

            const skippedNoAllocation = []; // 記錄因無分配而跳過的訂單

            for (const orderId of selectedItems.value) {
                const order = orders.value.find(o => o.id === orderId);
                if (!order) continue;

                // 如果父訂單有子訂單，處理其下的未出貨子訂單
                if (order.children && order.children.length > 0) {
                    for (const child of order.children) {
                        // 只處理未出貨的子訂單
                        if (!child.shipping_status || child.shipping_status === 'unshipped') {
                            // 檢查子訂單是否有分配
                            if (hasAllocatedItems(child)) {
                                ordersToProcess.push({
                                    id: child.id,
                                    invoice_no: child.invoice_no,
                                    isChild: true,
                                    parentInvoice: order.invoice_no || order.id
                                });
                            } else {
                                skippedNoAllocation.push(child.invoice_no || child.id);
                            }
                        }
                    }
                } else {
                    // 沒有子訂單的父訂單，直接處理
                    if (!order.shipping_status || order.shipping_status === 'unshipped') {
                        // 檢查父訂單是否有分配
                        if (hasAllocatedItems(order)) {
                            ordersToProcess.push({
                                id: order.id,
                                invoice_no: order.invoice_no || order.id,
                                isChild: false
                            });
                        } else {
                            skippedNoAllocation.push(order.invoice_no || order.id);
                        }
                    }
                }
            }

            if (ordersToProcess.length === 0) {
                // 根據跳過原因顯示不同訊息
                if (skippedNoAllocation.length > 0) {
                    showToast('所選訂單尚未分配庫存，無法轉備貨', 'error');
                } else {
                    showToast('所選訂單都不是「未出貨」狀態，無法轉備貨', 'error');
                }
                return;
            }

            const childCount = ordersToProcess.filter(o => o.isChild).length;
            const parentCount = ordersToProcess.filter(o => !o.isChild).length;

            let confirmMessage = `確定要將 ${ordersToProcess.length} 筆訂單轉為備貨狀態嗎？`;
            if (childCount > 0 && parentCount > 0) {
                confirmMessage += `\n（包含 ${parentCount} 筆父訂單、${childCount} 筆子訂單）`;
            } else if (childCount > 0) {
                confirmMessage += `\n（${childCount} 筆子訂單）`;
            }

            // 如果有被跳過的訂單（因無分配），提示使用者
            if (skippedNoAllocation.length > 0) {
                confirmMessage += `\n\n注意：${skippedNoAllocation.length} 筆訂單因尚未分配庫存而跳過`;
            }

            showConfirm(
                '批次轉備貨',
                confirmMessage,
                async () => {
                    batchProcessing.value = true;
                    let successCount = 0;
                    let failCount = 0;

                    try {
                        // 逐一呼叫 prepare API
                        for (const order of ordersToProcess) {
                            try {
                                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${order.id}/prepare`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-WP-Nonce': wpNonce
                                    },
                                    credentials: 'include'
                                });

                                const result = await response.json();

                                if (result.success) {
                                    successCount++;
                                } else {
                                    failCount++;
                                    console.error(`訂單 #${order.invoice_no} 轉備貨失敗:`, result.message);
                                }
                            } catch (err) {
                                failCount++;
                                console.error(`訂單 #${order.invoice_no} 轉備貨錯誤:`, err);
                            }
                        }

                        // 顯示結果
                        if (failCount === 0) {
                            showToast(`成功將 ${successCount} 筆訂單轉為備貨狀態`, 'success');
                        } else {
                            showToast(`${successCount} 筆成功，${failCount} 筆失敗`, failCount > 0 ? 'error' : 'success');
                        }

                        // 清空選取並重新載入
                        selectedItems.value = [];
                        await loadOrders();

                    } catch (err) {
                        console.error('批次轉備貨錯誤:', err);
                        showToast('批次轉備貨失敗：' + err.message, 'error');
                    } finally {
                        batchProcessing.value = false;
                    }
                },
                { confirmText: '確認轉備貨', cancelText: '取消' }
            );
        };

        // 批次刪除
        const batchDelete = async () => {
            if(!confirm(`確認刪除 ${selectedItems.value.length} 項？`)) return;
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/orders/batch-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify({ ids: selectedItems.value })
                });
                const data = await res.json();
                if (data.success) {
                    orders.value = orders.value.filter(o => !selectedItems.value.includes(o.id));
                    selectedItems.value = [];
                    showToast('批次刪除成功');
                    loadOrders();
                } else {
                    showToast(data.message || '刪除失敗', 'error');
                }
            } catch(e) { 
                console.error(e); 
                showToast('刪除錯誤', 'error'); 
            }
        };
        
        // 切換幣別
        const toggleCurrency = () => {
            // 在系統幣別和台幣之間切換
            if (currentCurrency.value === 'TWD') {
                currentCurrency.value = systemCurrencyFromComposable.value;
                systemCurrency.value = systemCurrencyFromComposable.value;
                showToast(`已切換為 ${currencySymbols[systemCurrencyFromComposable.value]} ${systemCurrencyFromComposable.value}`);
            } else {
                currentCurrency.value = 'TWD';
                systemCurrency.value = 'TWD';
                showToast(`已切換為 ${currencySymbols['TWD']} TWD`);
            }
        };

        // Modal 狀態（保留向下相容）
        const showOrderModal = ref(false);
        const currentOrder = ref(null);
        const shipping = ref(false);

        // OrderDetailModal 狀態（已改為 URL 驅動）
        const showModal = ref(false);
        const selectedOrderId = ref(null);

        // 批次操作
        const selectedItems = ref([]);
        const batchProcessing = ref(false);

        // 展開狀態（用於商品列表展開）
        const expandedOrders = ref(new Set());

        // 子訂單折疊狀態（預設展開）
        const collapsedChildren = ref(new Set());

        // 狀態下拉選單狀態
        const openStatusDropdown = ref(null);
        const dropdownPosition = ref({ top: 0, left: 0 });
        
        // 確認 Modal 狀態
        const confirmModal = ref({
            show: false,
            title: '',
            message: '',
            confirmText: '確認',
            cancelText: '取消',
            onConfirm: null
        });
        
        // Toast 通知狀態
        const toastMessage = ref({
            show: false,
            message: '',
            type: 'success' // 'success' | 'error' | 'info'
        });
        
        // 顯示確認對話框
        const showConfirm = (title, message, onConfirm, options = {}) => {
            confirmModal.value = {
                show: true,
                title,
                message,
                confirmText: options.confirmText || '確認',
                cancelText: options.cancelText || '取消',
                onConfirm
            };
        };
        
        // 關閉確認對話框
        const closeConfirmModal = () => {
            confirmModal.value.show = false;
        };
        
        // 確認按鈕處理
        const handleConfirm = () => {
            if (confirmModal.value.onConfirm) {
                confirmModal.value.onConfirm();
            }
            closeConfirmModal();
        };
        
        // 顯示 Toast 訊息
        const showToast = (message, type = 'success') => {
            toastMessage.value = { show: true, message, type };
            setTimeout(() => {
                toastMessage.value.show = false;
            }, 3000);
        };

        // 格式化價格（使用當前顯示幣別，並做匯率轉換）
        const formatPrice = (amount, originalCurrency = null) => {
            if (amount == null) return '-';

            // 如果有原始幣別且與當前顯示幣別不同，需要做匯率轉換
            if (originalCurrency && originalCurrency !== currentCurrency.value) {
                return formatPriceWithConversion(amount, originalCurrency, currentCurrency.value);
            }

            // 否則直接格式化（不轉換）
            return formatCurrency(amount, currentCurrency.value);
        };

        // 搜尋處理函數
        const handleSearchInput = (e) => {
            const query = e.target ? e.target.value : e;
            searchQuery.value = query;
            // 如果搜尋框有內容，嘗試找到對應的訂單
            if (query && query.trim()) {
                // 可以選擇是否要自動篩選，這裡先簡單處理為全域搜尋
                currentPage.value = 1;
                loadOrders();
            } else {
                // 清除搜尋時重置
                handleSearchClear();
            }
        };

        const handleSearchSelect = (item) => {
            searchFilter.value = item.id;
            searchFilterName.value = item.invoice_no || item.customer_name || '';
            searchQuery.value = item.invoice_no || item.customer_name || '';
            currentPage.value = 1;
            loadOrders();
        };

        const handleSearchClear = () => {
            searchFilter.value = null;
            searchFilterName.value = '';
            searchQuery.value = '';
            currentPage.value = 1;
            loadOrders();
        };

        // 載入訂單
        // 更新統計資料（使用 API 返回的全域統計）
        const updateStats = (apiStats) => {
            if (apiStats) {
                stats.value = {
                    total: apiStats.total || 0,
                    unshipped: apiStats.unshipped || 0,
                    preparing: apiStats.preparing || 0,
                    shipped: apiStats.shipped || 0
                };
            }
        };

        // 狀態對應函數（與後端 incrementStatsByStatus 保持一致）
        const getStatusCategory = (status) => {
            if (!status || status === 'unshipped' || status === 'pending') {
                return 'unshipped';
            } else if (status === 'preparing') {
                return 'preparing';
            } else if (['shipped', 'completed', 'processing', 'ready_to_ship'].includes(status)) {
                return 'shipped';
            }
            return 'unshipped';
        };

        // 檢查訂單是否已分配庫存
        const hasAllocatedItems = (order) => {
            if (!order) {
                return false;
            }

            // 【關鍵邏輯】如果父訂單已有子訂單，父訂單不應顯示「轉備貨」按鈕
            // 使用者應該在子訂單上執行轉備貨操作
            if (order.children && order.children.length > 0) {
                return false;
            }

            // 優先檢查 has_allocation 欄位（如果 API 有提供）
            if (order.has_allocation === true) {
                return true;
            }

            // 如果沒有 has_allocation 欄位，檢查 items 中的 allocated_quantity
            if (!order.items || !Array.isArray(order.items) || order.items.length === 0) {
                return false;
            }

            // 檢查每個 item 的 allocated_quantity
            return order.items.some(item => {
                // 處理各種可能的資料類型：數字、字串、null、undefined
                const allocatedQty = item.allocated_quantity != null
                    ? parseInt(item.allocated_quantity, 10)
                    : 0;

                // 確保是有效數字
                const isValidNumber = !isNaN(allocatedQty) && isFinite(allocatedQty);
                return isValidNumber && allocatedQty > 0;
            });
        };

        // 檢查是否可以顯示「轉備貨」按鈕
        // 只有在未出貨狀態才顯示按鈕，已備貨或已出貨則顯示狀態標籤
        const canShowShipButton = (order) => {
            if (!order) return false;
            const status = order.shipping_status || 'unshipped';
            // 只有 unshipped 狀態才顯示轉備貨按鈕
            return status === 'unshipped' || status === '';
        };

        // 篩選後的訂單（根據狀態分類，不含分頁）
        // 邏輯（2026-01-31 更新）：
        // - 「全部」分頁：顯示所有訂單（父訂單+子訂單階層結構）
        // - 「轉備貨」分頁：只顯示「已分配庫存且未出貨」的訂單（可操作的）
        // - 其他分頁（備貨中/已出貨）：只顯示符合狀態的訂單
        //   - 有子訂單的父訂單：只顯示符合條件的子訂單（作為獨立項目，不帶父訂單）
        //   - 沒有子訂單的父訂單：根據父訂單自己的狀態判斷
        const allFilteredOrders = computed(() => {
            if (!filterStatus.value) {
                return orders.value; // 顯示全部（保持原始階層結構）
            }

            // 過濾訂單：只顯示可操作的訂單
            const result = [];

            // 「轉備貨」分頁特殊處理：只顯示已分配庫存的訂單
            const isUnshippedFilter = filterStatus.value === 'unshipped';

            for (const order of orders.value) {
                const children = order.children || [];

                if (children.length > 0) {
                    // 有子訂單：找出符合狀態的子訂單
                    const matchingChildren = children.filter(child => {
                        const childCategory = getStatusCategory(child.shipping_status);
                        if (childCategory !== filterStatus.value) {
                            return false;
                        }
                        // 「轉備貨」分頁：額外檢查是否已分配庫存
                        if (isUnshippedFilter) {
                            return hasAllocatedItems(child);
                        }
                        return true;
                    });

                    // 將每個符合條件的子訂單作為獨立項目加入結果
                    // 不再以父訂單包裹子訂單的形式顯示
                    for (const child of matchingChildren) {
                        // 【修復 2026-01-31】確保提取的子訂單有 items 資料
                        // 如果子訂單沒有 items，從父訂單的子訂單中繼承
                        const childWithItems = {
                            ...child,
                            items: child.items || [], // 確保 items 存在
                            _isExtractedChild: true, // 標記這是從父訂單提取出來的子訂單
                            _parentOrder: order // 保留父訂單參考（如需要顯示父訂單資訊）
                        };
                        result.push(childWithItems);
                    }
                } else {
                    // 沒有子訂單：檢查父訂單自己的狀態
                    const parentCategory = getStatusCategory(order.shipping_status);
                    if (parentCategory !== filterStatus.value) {
                        continue;
                    }
                    // 「轉備貨」分頁：額外檢查是否已分配庫存
                    if (isUnshippedFilter && !hasAllocatedItems(order)) {
                        continue;
                    }
                    result.push(order);
                }
            }

            return result;
        });

        // 【修復 2026-01-31】加入分頁邏輯的篩選訂單
        // filteredOrders 是分頁後的結果，用於模板渲染
        const filteredOrders = computed(() => {
            const all = allFilteredOrders.value;

            // 如果 perPage 為 -1，表示顯示全部
            if (perPage.value === -1) {
                return all;
            }

            // 計算分頁起始和結束索引
            const start = (currentPage.value - 1) * perPage.value;
            const end = start + perPage.value;

            return all.slice(start, end);
        });

        // 根據當前篩選狀態，取得子訂單列表（用於模板）
        // 邏輯：
        // - 無篩選（全部）：返回所有子訂單
        // - 有篩選：返回空陣列（因為子訂單已經被提取為獨立項目顯示）
        const getFilteredChildren = (order) => {
            if (!filterStatus.value) {
                return order.children || [];
            }
            // 篩選模式下，子訂單已經被提取為獨立項目，不需要在父訂單下再顯示
            return [];
        };

        // 計算每個分類的實際可操作數量（用於標籤頁顯示）
        // 這與 filteredOrders 使用相同的邏輯，確保數字與實際顯示內容一致
        const tabCounts = computed(() => {
            const counts = {
                total: 0,
                unshipped: 0,   // 轉備貨（需要有已分配庫存）
                preparing: 0,   // 備貨中
                shipped: 0      // 已出貨
            };

            for (const order of orders.value) {
                const children = order.children || [];

                if (children.length > 0) {
                    // 有子訂單：計算各狀態的子訂單數量
                    for (const child of children) {
                        const childCategory = getStatusCategory(child.shipping_status);

                        // 「轉備貨」需要額外檢查是否已分配庫存
                        if (childCategory === 'unshipped') {
                            if (hasAllocatedItems(child)) {
                                counts.unshipped++;
                            }
                        } else if (childCategory === 'preparing') {
                            counts.preparing++;
                        } else if (childCategory === 'shipped') {
                            counts.shipped++;
                        }
                    }
                    counts.total++; // 父訂單計入總數
                } else {
                    // 沒有子訂單：根據父訂單狀態計算
                    const parentCategory = getStatusCategory(order.shipping_status);

                    if (parentCategory === 'unshipped') {
                        if (hasAllocatedItems(order)) {
                            counts.unshipped++;
                        }
                    } else if (parentCategory === 'preparing') {
                        counts.preparing++;
                    } else if (parentCategory === 'shipped') {
                        counts.shipped++;
                    }
                    counts.total++;
                }
            }

            return counts;
        });

        const loadOrders = async () => {
            loading.value = true;
            error.value = null;

            try {
                // 始終請求所有資料（最多 100 筆），以便正確計算各分頁的數量
                // 因為前端需要完整資料來計算「轉備貨」等分頁的實際可操作數量
                const requestPerPage = 100;
                const requestPage = 1;

                // 加入時間戳記強制繞過所有快取
                let url = `/wp-json/buygo-plus-one/v1/orders?page=${requestPage}&per_page=${requestPerPage}&_t=${Date.now()}`;

                if (searchFilter.value) {
                    url += `&id=${searchFilter.value}`;
                } else if (searchQuery.value && searchQuery.value.trim()) {
                    // 如果沒有特定篩選，但有搜尋關鍵字，使用 search 參數
                    url += `&search=${encodeURIComponent(searchQuery.value.trim())}`;
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
                    // 為每個訂單加上 has_allocation 標記和確保 items 存在
                    orders.value = result.data.map(order => ({
                        ...order,
                        // 檢查是否有分配的商品
                        has_allocation: order.items && Array.isArray(order.items) && order.items.some(item => {
                            const allocatedQty = item.allocated_quantity != null
                                ? parseInt(item.allocated_quantity, 10)
                                : 0;
                            return !isNaN(allocatedQty) && isFinite(allocatedQty) && allocatedQty > 0;
                        }),
                        // 確保 items 陣列存在
                        items: order.items || []
                    }));
                    totalOrders.value = result.total || result.data.length;

                    // 更新統計資料（使用 API 返回的全域統計）
                    updateStats(result.stats);

                    // 預設折疊所有有子訂單的訂單
                    orders.value.forEach(order => {
                        if (order.children && order.children.length > 0) {
                            collapsedChildren.value.add(order.id);
                        }
                    });
                } else {
                    throw new Error(result.message || '載入訂單失敗');
                }
            } catch (err) {
                console.error('載入訂單錯誤:', err);
                error.value = err.message;
                
                // 記錄到除錯中心（透過 API）
                fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify({
                        module: 'Orders',
                        message: '載入訂單失敗',
                        level: 'error',
                        data: { error: err.message, url: url }
                    })
                }).catch(console.error);
                
                orders.value = [];
            } finally {
                loading.value = false;
            }
        };

        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 格式化商品列表顯示
        const formatItemsDisplay = (order, maxLength = 50) => {
            if (!order.items || !Array.isArray(order.items) || order.items.length === 0) {
                return `${order.total_items || 0} 件`;
            }

            // 【修復 2026-01-31】直接使用 quantity 顯示
            // 子訂單有自己正確的 quantity 值，不需要用 pending_quantity
            // pending_quantity 是「待分配數量」，對於已分配/已出貨的訂單會是 0
            const itemsText = order.items
                .map(item => {
                    const displayQty = item.quantity || 0;
                    return `${item.product_name || '未知商品'} x${displayQty}`;
                })
                .join(', ');

            // 如果文字太長，截斷並加上省略號
            if (itemsText.length > maxLength) {
                return itemsText.substring(0, maxLength) + '...';
            }

            return itemsText;
        };
        
        // 切換訂單展開狀態
        const toggleOrderExpand = async (orderId) => {
            if (expandedOrders.value.has(orderId)) {
                expandedOrders.value.delete(orderId);
            } else {
                expandedOrders.value.add(orderId);
                
                // 如果訂單沒有 items，載入詳細資料
                const order = orders.value.find(o => o.id === orderId);
                if (order && (!order.items || !Array.isArray(order.items) || order.items.length === 0)) {
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${orderId}`, {
                            credentials: 'include',
                            headers: { 'X-WP-Nonce': wpNonce }
                        });
                        
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.length > 0) {
                            const orderDetail = result.data[0];
                            // 更新 orders 陣列中的訂單資料
                            const index = orders.value.findIndex(o => o.id === orderId);
                            if (index !== -1) {
                                orders.value[index] = { ...orders.value[index], items: orderDetail.items };
                            }
                        }
                    } catch (err) {
                        console.error('載入訂單商品失敗:', err);
                    }
                }
            }
        };
        
        // 檢查訂單是否展開
        const isOrderExpanded = (orderId) => {
            return expandedOrders.value.has(orderId);
        };

        // 切換子訂單顯示/隱藏
        const toggleChildrenCollapse = (orderId) => {
            if (collapsedChildren.value.has(orderId)) {
                collapsedChildren.value.delete(orderId);
            } else {
                collapsedChildren.value.add(orderId);
            }
        };

        // 檢查子訂單是否已折疊
        const isChildrenCollapsed = (orderId) => {
            return collapsedChildren.value.has(orderId);
        };

        // 運送狀態選項（6個）
        const shippingStatuses = [
            { value: 'unshipped', label: '未出貨', color: 'bg-gray-100 text-gray-800 border border-gray-300' },
            { value: 'preparing', label: '備貨中', color: 'bg-yellow-100 text-yellow-800 border border-yellow-300' },
            { value: 'processing', label: '待出貨', color: 'bg-blue-100 text-blue-800 border border-blue-300' },
            { value: 'shipped', label: '已出貨', color: 'bg-purple-100 text-purple-800 border border-purple-300' },
            { value: 'completed', label: '交易完成', color: 'bg-green-100 text-green-800 border border-green-300' },
            { value: 'out_of_stock', label: '斷貨', color: 'bg-red-100 text-red-800 border border-red-300' }
        ];

        // 取得運送狀態樣式
        const getStatusClass = (status) => {
            const statusObj = shippingStatuses.find(s => s.value === status);
            return statusObj ? statusObj.color : 'bg-slate-100 text-slate-800 border border-slate-300';
        };

        // 取得運送狀態文字
        const getStatusText = (status) => {
            const statusObj = shippingStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : status;
        };

        // 切換狀態下拉選單
        const toggleStatusDropdown = (orderId, event) => {
            if (openStatusDropdown.value === orderId) {
                openStatusDropdown.value = null;
            } else {
                openStatusDropdown.value = orderId;

                // 計算下拉選單位置（fixed 定位）
                if (event && event.target) {
                    const button = event.target.closest('button');
                    if (button) {
                        const rect = button.getBoundingClientRect();
                        // 向上展開：選單底部對齊按鈕頂部
                        dropdownPosition.value = {
                            top: rect.top - 8, // 減去 mb-1 的間距
                            left: rect.left
                        };
                    }
                }
            }
        };

        // 檢查狀態下拉選單是否開啟
        const isStatusDropdownOpen = (orderId) => {
            return openStatusDropdown.value === orderId;
        };

        // 更新運送狀態
        const updateShippingStatus = async (orderId, newStatus) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderId}/shipping-status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({ status: newStatus })
                });

                const result = await response.json();

                if (result.success) {
                    // 更新本地訂單資料
                    const order = orders.value.find(o => o.id === orderId);
                    if (order) {
                        order.shipping_status = newStatus;
                    }
                    showToast('運送狀態已更新', 'success');
                } else {
                    showToast('更新失敗：' + (result.message || '未知錯誤'), 'error');
                }
            } catch (err) {
                console.error('更新運送狀態失敗:', err);
                showToast('更新失敗：' + err.message, 'error');
            } finally {
                // 關閉下拉選單
                openStatusDropdown.value = null;
            }
        };

        // 查看訂單詳情
        const viewOrderDetails = async (order) => {
            showOrderModal.value = true;
            // 重新載入訂單詳情以取得最新的 allocated_quantity
            await loadOrderDetail(order.id);
        };
        
        // 關閉訂單詳情 Modal
        const closeOrderModal = () => {
            showOrderModal.value = false;
            currentOrder.value = null;
        };
        
        // ============================================
        // 路由邏輯（使用 BuyGoRouter 核心模組）
        // ============================================
        const checkUrlParams = () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view === 'detail' && id) {
                currentView.value = 'detail';
                currentOrderId.value = id;
                selectedOrderId.value = id;
                loadOrderDetail(id);
            } else {
                currentView.value = 'list';
                currentOrderId.value = null;
            }
        };

        const navigateTo = (view, orderId = null, updateUrl = true) => {
            currentView.value = view;

            if (orderId) {
                currentOrderId.value = orderId;
                selectedOrderId.value = orderId;
                loadOrderDetail(orderId);

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo(view, orderId);
                }
            } else {
                currentOrderId.value = null;
                selectedOrderId.value = null;
                currentOrder.value = null;

                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        // 開啟訂單詳情（URL 驅動）
        const openOrderDetail = (orderId) => {
            navigateTo('detail', orderId);
        };

        // 關閉訂單詳情（返回列表）
        const closeOrderDetail = () => {
            navigateTo('list');
        };
        
        // 檢查訂單是否有可出貨的商品（用於父訂單）
        // 重要：如果父訂單已有子訂單（拆單），父訂單本身不應顯示「轉備貨」按鈕
        // 因為此時應該在子訂單上操作，而非父訂單
        // 執行訂單出貨
        const shipOrder = async (order) => {
            // 轉備貨：將訂單狀態更新為 'preparing'
            showConfirm(
                '確認轉備貨',
                `確定要將訂單 #${order.invoice_no || order.id} 轉為備貨狀態嗎？`,
                async () => {
                    shipping.value = true;

                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${order.id}/prepare`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpNonce
                            },
                            credentials: 'include'
                        });

                        const result = await response.json();

                        if (result.success) {
                            showToast('已轉為備貨狀態', 'success');
                            // 刷新列表
                            await loadOrders();
                        } else {
                            showToast('轉備貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('轉備貨失敗:', err);
                        showToast('轉備貨失敗：' + err.message, 'error');

                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '轉備貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: order.id }
                            })
                        }).catch(console.error);
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };
        
        // 執行出貨
        const shipOrderItem = (item) => {
            showConfirm(
                '確認出貨',
                `確定要出貨 ${item.allocated_quantity} 個「${item.product_name}」嗎？`,
                async () => {
                    shipping.value = true;
                    
                    try {
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${item.order_id}/ship`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpNonce
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
                            showToast(`出貨成功！出貨單號：SH-${result.shipment_id}`, 'success');
                            // 重新載入訂單詳情
                            await loadOrderDetail(item.order_id);
                        } else {
                            showToast('出貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('出貨失敗:', err);
                        showToast('出貨失敗：' + err.message, 'error');
                
                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '訂單商品出貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: item.order_id, item_id: item.id }
                            })
                        }).catch(console.error);
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };

        // 執行子訂單轉備貨（不是直接出貨）
        const shipChildOrder = async (childOrder, parentOrder) => {
            // 確認轉備貨
            showConfirm(
                '確認轉備貨',
                `確定要將指定單 #${childOrder.invoice_no} 轉為備貨狀態嗎？`,
                async () => {
                    shipping.value = true;

                    try {
                        // 呼叫 /prepare 端點，將狀態改為 'preparing'
                        const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${childOrder.id}/prepare`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpNonce
                            },
                            credentials: 'include'
                        });

                        const result = await response.json();

                        if (result.success) {
                            showToast('已轉為備貨狀態', 'success');
                            // 刷新列表
                            await loadOrders();
                        } else {
                            showToast('轉備貨失敗：' + result.message, 'error');
                        }
                    } catch (err) {
                        console.error('轉備貨失敗:', err);
                        showToast('轉備貨失敗：' + err.message, 'error');

                        // 記錄到除錯中心
                        fetch('/wp-json/buygo-plus-one/v1/debug/log', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                            credentials: 'include',
                            body: JSON.stringify({
                                module: 'Orders',
                                message: '子訂單轉備貨失敗',
                                level: 'error',
                                data: { error: err.message, order_id: childOrder.id }
                            })
                        });
                    } finally {
                        shipping.value = false;
                    }
                }
            );
        };

        // 載入訂單詳情
        const loadOrderDetail = async (orderId) => {
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders?id=${orderId}`, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': wpNonce }
                });

                const result = await response.json();

                if (result.success && result.data && result.data.length > 0) {
                    currentOrder.value = result.data[0];
                }
            } catch (err) {
                console.error('載入訂單詳情失敗:', err);
            }
        };
        

        // 是否全選（用於 checkbox 狀態）
        const isAllSelected = computed(() => {
            const visibleOrders = filteredOrders.value;
            if (visibleOrders.length === 0) return false;
            return visibleOrders.every(order => selectedItems.value.includes(order.id));
        });

        // 全選/取消全選
        const toggleSelectAll = (event) => {
            const visibleOrders = filteredOrders.value;
            if (event.target.checked) {
                // 選取當前篩選後的所有訂單
                const visibleIds = visibleOrders.map(o => o.id);
                selectedItems.value = [...new Set([...selectedItems.value, ...visibleIds])];
            } else {
                // 取消選取當前篩選後的所有訂單
                const visibleIds = new Set(visibleOrders.map(o => o.id));
                selectedItems.value = selectedItems.value.filter(id => !visibleIds.has(id));
            }
        };
        
        // 分頁（使用篩選後的總數而非 API 返回的總數）
        const totalPages = computed(() => {
            if (perPage.value === -1) return 1;
            // 【修復 2026-01-31】使用 allFilteredOrders 的長度計算總頁數
            // 這樣分頁才會正確反映當前篩選條件下的結果數量
            return Math.ceil(allFilteredOrders.value.length / perPage.value);
        });
        
        // 可見的頁碼（最多顯示 5 頁）
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
                loadOrders();
            }
        };
        
        const nextPage = () => {
            if (currentPage.value < totalPages.value) {
                currentPage.value++;
                loadOrders();
            }
        };
        
        const goToPage = (page) => {
            currentPage.value = page;
            loadOrders();
        };
        
        const changePerPage = () => {
            currentPage.value = 1;
            loadOrders();
        };
        
        // 監聽篩選狀態變化
        // 【修復 2026-01-31】切換分頁時不需要重新載入資料
        // 因為前端已經有所有資料，只需要重置到第一頁即可
        // Vue computed 會自動重新計算 filteredOrders
        watch(filterStatus, () => {
            currentPage.value = 1; // 重置到第一頁
            // 不再呼叫 loadOrders()，前端直接篩選
        });

        // 初始化
        onMounted(() => {
            loadOrders();
            // 檢查 URL 參數（使用 BuyGoRouter 核心模組）
            checkUrlParams();
            // 監聽瀏覽器上一頁/下一頁
            window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 點擊外部關閉狀態下拉選單
            document.addEventListener('click', () => {
                if (openStatusDropdown.value !== null) {
                    openStatusDropdown.value = null;
                }
            });

            // 監聽商品分配更新事件（同步執行出貨按鈕狀態）
            window.addEventListener('storage', (e) => {
                if (e.key === 'buygo_allocation_updated' && e.newValue) {
                    // 重新載入訂單列表以更新 allocated_quantity
                    loadOrders();
                    // 清除標記
                    localStorage.removeItem('buygo_allocation_updated');
                }
            });

            // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
            // 當使用者從其他頁面切換回來時，重新載入資料
            window.addEventListener('pageshow', (e) => {
                // persisted 表示頁面是從 bfcache 恢復的
                if (e.persisted) {
                    loadOrders();
                }
            });

            // 監聽頁面可見性變化（從其他標籤頁切換回來）
            // 只要頁面變為可見就重新載入，確保資料永遠是最新的
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    loadOrders();
                    // 清除可能的分配更新標記
                    localStorage.removeItem('buygo_allocation_updated');
                }
            });
        });

        // Smart Search Box 事件處理器
        const handleOrderSelect = (order) => {
            if (order && order.id) {
                openOrderDetail(order.id);
            }
        };

        // 本地搜尋處理函數(輸入時過濾列表)
        const handleOrderSearch = (query) => {
            searchQuery.value = query;
            currentPage.value = 1;  // 重置到第一頁
            loadOrders();
        };

        // 清除搜尋
        const handleOrderSearchClear = () => {
            searchQuery.value = '';
            searchFilter.value = null;
            searchFilterName.value = '';
            currentPage.value = 1;
            loadOrders();
        };

        // 幣別切換處理（Header 元件會呼叫此方法）
        const onCurrencyChange = (newCurrency) => {
            console.log('[OrdersPage] 幣別變更:', newCurrency);
            currentCurrency.value = newCurrency;
        };

        return {
            orders,
            loading,
            error,
            currentPage,
            perPage,
            totalOrders,
            totalPages,
            visiblePages,
            previousPage,
            nextPage,
            goToPage,
            changePerPage,
            formatPrice,
            formatDate,
            getStatusClass,
            getStatusText,
            viewOrderDetails,
            closeOrderModal,
            hasAllocatedItems,
            canShowShipButton,
            shipOrder,
            shipOrderItem,
            shipChildOrder,
            loadOrderDetail,
            shipping,
            handleSearchSelect,
            handleSearchInput,
            handleSearchClear,
            toggleSelectAll,
            isAllSelected,
            selectedItems,
            searchFilter,
            searchFilterName,
            searchQuery,
            systemCurrency,
            currentCurrency,
            showOrderModal,
            currentOrder,
            loadOrders,
            showModal,
            selectedOrderId,
            openOrderDetail,
            closeOrderDetail,
            formatItemsDisplay,
            toggleOrderExpand,
            isOrderExpanded,
            expandedOrders,
            toggleChildrenCollapse,
            isChildrenCollapsed,
            collapsedChildren,
            // 路由狀態（URL 驅動）
            currentView,
            currentOrderId,
            navigateTo,
            checkUrlParams,
            // 確認 Modal 和 Toast
            confirmModal,
            showConfirm,
            closeConfirmModal,
            handleConfirm,
            toastMessage,
            showToast,
            // UI 狀態
            showMobileSearch,
            // 新增方法
            batchPrepare,
            batchProcessing,
            batchDelete,
            toggleCurrency,
            // Smart Search Box
            handleOrderSelect,
            handleOrderSearch,
            handleOrderSearchClear,
            // 幣別切換
            onCurrencyChange,
            // 運送狀態相關
            shippingStatuses,
            toggleStatusDropdown,
            isStatusDropdownOpen,
            updateShippingStatus,
            openStatusDropdown,
            dropdownPosition,
            // 狀態篩選相關
            filterStatus,
            stats,
            filteredOrders,
            getFilteredChildren,
            getStatusCategory,
            tabCounts,
            // API 認證
            wpNonce
        };
    }
};
