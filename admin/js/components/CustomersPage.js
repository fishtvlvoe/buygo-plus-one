/**
 * Customers Page Component
 * BuyGo+1 Plugin
 *
 * 客戶管理頁面 Vue 組件
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

const CustomersPageComponent = {
    name: 'CustomersPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#customers-page-template',
    setup() {
        const { ref, computed, onMounted } = Vue;

        // WordPress REST API nonce（用於 API 認證）
        const wpNonce = window.buygoWpNonce || '';

        // 使用 useCurrency Composable 處理幣別邏輯
        const {
            formatPrice: formatCurrency,
            formatPriceWithConversion,
            systemCurrency,
            currencySymbols,
            exchangeRates
        } = useCurrency();

        // 狀態變數
        const customers = ref([]);
        const loading = ref(false);
        const error = ref(null);

        // 分頁狀態
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalCustomers = ref(0);

        // 搜尋篩選狀態
        const searchFilter = ref(null);
        const searchFilterName = ref('');

        // ========== 路由狀態（新增）==========
        const currentView = ref('list');  // 'list' | 'detail'
        const currentCustomerId = ref(null);
        const selectedCustomer = ref(null);
        const detailLoading = ref(false);

        // UI 狀態（新增）
        const showMobileSearch = ref(false);
        const globalSearchQuery = ref('');

        // 幣別切換狀態
        const displayCurrency = ref(systemCurrency.value);
        const currentCurrency = ref(systemCurrency.value);

        // 訂單搜尋（子頁面內用）
        const orderSearchQuery = ref('');

        // Tab 分頁狀態
        const activeTab = ref('orders');

        // 備註狀態
        const customerNote = ref('');
        const noteSaving = ref(false);
        const noteSaved = ref(false);

        // 訂單展開狀態
        const expandedOrderId = ref(null);
        const orderItems = ref({});
        const loadingOrderItems = ref(false);

        // 批次操作
        const selectedItems = ref([]);

        // Toast 通知狀態
        const toastMessage = ref({
            show: false,
            message: '',
            type: 'success'
        });
        
        // 顯示 Toast 訊息
        const showToast = (message, type = 'success') => {
            toastMessage.value = { show: true, message, type };
            setTimeout(() => {
                toastMessage.value.show = false;
            }, 3000);
        };
        
        // 載入客戶列表
        const loadCustomers = async () => {
            loading.value = true;
            error.value = null;
            
            try {
                let url = `/wp-json/buygo-plus-one/v1/customers?page=${currentPage.value}&per_page=${perPage.value}`;
                
                if (searchFilter.value) {
                    url += `&search=${encodeURIComponent(searchFilter.value)}`;
                }
                
                const response = await fetch(url, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': wpNonce }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    customers.value = result.data;
                    totalCustomers.value = result.total || result.data.length;
                } else {
                    throw new Error(result.message || '載入客戶列表失敗');
                }
            } catch (err) {
                console.error('載入客戶錯誤:', err);
                error.value = err.message;
                customers.value = [];
            } finally {
                loading.value = false;
            }
        };
        
        // ========== 路由邏輯（新增）==========

        // 檢查 URL 參數
        const checkUrlParams = () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view === 'detail' && id) {
                currentView.value = 'detail';
                currentCustomerId.value = id;
                loadCustomerDetail(id);
            } else {
                currentView.value = 'list';
                currentCustomerId.value = null;
                selectedCustomer.value = null;
            }
        };

        // 導航函數
        const navigateTo = (view, customerId = null, updateUrl = true) => {
            currentView.value = view;

            if (view === 'detail' && customerId) {
                currentCustomerId.value = customerId;
                loadCustomerDetail(customerId);

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo('detail', customerId);
                }
            } else {
                // 返回列表
                currentCustomerId.value = null;
                selectedCustomer.value = null;
                activeTab.value = 'orders';
                orderSearchQuery.value = '';
                expandedOrderId.value = null;
                orderItems.value = {};

                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        // 載入客戶詳情
        const loadCustomerDetail = async (customerId) => {
            detailLoading.value = true;
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/customers/${customerId}`, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': wpNonce }
                });

                const result = await response.json();

                if (result.success && result.data) {
                    selectedCustomer.value = result.data;
                    customerNote.value = result.data.note || '';
                    noteSaved.value = false;

                    // 設定幣別（從客戶第一筆訂單讀取）
                    if (result.data.orders && result.data.orders.length > 0) {
                        displayCurrency.value = result.data.orders[0].currency || 'JPY';
                    }
                } else {
                    showToast('載入客戶詳情失敗', 'error');
                    navigateTo('list');
                }
            } catch (err) {
                console.error('載入客戶詳情錯誤:', err);
                showToast('載入客戶詳情失敗', 'error');
                navigateTo('list');
            } finally {
                detailLoading.value = false;
            }
        };

        // 全域搜尋處理
        const handleGlobalSearch = (event) => {
            // 可以在這裡實作全域搜尋邏輯
            // TODO: 實作全域搜尋功能
        };

        // 幣別切換
        const toggleCurrency = () => {
            // 在系統幣別和台幣之間切換
            if (currentCurrency.value === 'TWD') {
                currentCurrency.value = systemCurrency.value;
                displayCurrency.value = systemCurrency.value;
                showToast(`已切換為 ${currencySymbols[systemCurrency.value]} ${systemCurrency.value}`);
            } else {
                currentCurrency.value = 'TWD';
                displayCurrency.value = 'TWD';
                showToast(`已切換為 ${currencySymbols['TWD']} TWD`);
            }
        };

        // 跳轉到訂單管理頁面（更新：使用 Deep Link）
        const navigateToOrder = (orderId) => {
            window.location.href = `/buygo-portal/orders/?view=detail&id=${orderId}`;
        };

        // 格式化金額（amount 單位為分，除以 100 顯示；currency 可選，缺則用 currentCurrency）
        const formatPrice = (amount, currency = null) => {
            if (amount !== 0 && !amount) return '-';

            const value = amount / 100;

            // 如果有原始幣別且與當前顯示幣別不同，需要做匯率轉換
            if (currency && currency !== currentCurrency.value) {
                return formatPriceWithConversion(value, currency, currentCurrency.value);
            }

            // 否則直接格式化（不轉換）
            return formatCurrency(value, currentCurrency.value);
        };

        // 格式化日期
        const formatDate = (dateString) => {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW');
        };
        
        // 格式化短日期（月/日）
        const formatShortDate = (dateString) => {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const month = date.getMonth() + 1;
            const day = date.getDate();
            return `${month}/${day}`;
        };

        // 儲存備註
        const saveNote = async () => {
            if (!selectedCustomer.value) return;
            
            noteSaving.value = true;
            noteSaved.value = false;
            
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/customers/${selectedCustomer.value.id}/note`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({ note: customerNote.value })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    noteSaved.value = true;
                    showToast('備註已儲存', 'success');
                    setTimeout(() => {
                        noteSaved.value = false;
                    }, 2000);
                } else {
                    showToast('儲存備註失敗：' + result.message, 'error');
                }
            } catch (err) {
                console.error('儲存備註錯誤:', err);
                showToast('儲存備註失敗', 'error');
            } finally {
                noteSaving.value = false;
            }
        };
        
        // 取得訂單狀態樣式
        const getOrderStatusClass = (status) => {
            const statusClasses = {
                'pending': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                'processing': 'bg-blue-100 text-blue-800 border border-blue-200',
                'shipped': 'bg-purple-100 text-purple-800 border border-purple-200',
                'completed': 'bg-green-100 text-green-800 border border-green-200',
                'cancelled': 'bg-red-100 text-red-800 border border-red-200'
            };
            return statusClasses[status] || 'bg-slate-100 text-slate-800';
        };
        
        // 取得訂單狀態文字
        const getOrderStatusText = (status) => {
            const statusTexts = {
                'pending': '待處理',
                'processing': '處理中',
                'shipped': '已出貨',
                'completed': '已完成',
                'cancelled': '已取消'
            };
            return statusTexts[status] || status;
        };
        
        // 過濾訂單列表
        const filteredOrders = computed(() => {
            if (!selectedCustomer.value || !selectedCustomer.value.orders) {
                return [];
            }

            const query = orderSearchQuery.value.toLowerCase().trim();
            if (!query) {
                return selectedCustomer.value.orders;
            }

            return selectedCustomer.value.orders.filter(order => {
                const orderNumber = (order.order_number || order.id || '').toString().toLowerCase();
                const orderStatus = (order.order_status || '').toLowerCase();
                const statusText = getOrderStatusText(order.order_status).toLowerCase();

                return orderNumber.includes(query) ||
                       orderStatus.includes(query) ||
                       statusText.includes(query);
            });
        });
        
        // 切換訂單展開
        const toggleOrderExpand = async (orderId) => {
            if (expandedOrderId.value === orderId) {
                expandedOrderId.value = null;
                return;
            }
            expandedOrderId.value = orderId;
            if (!orderItems.value[orderId]) {
                await loadOrderItems(orderId);
            }
        };

        // 載入訂單商品（GET /orders/{id} 的 data.items，price/total 已為元）
        const loadOrderItems = async (orderId) => {
            loadingOrderItems.value = true;
            try {
                const response = await fetch(`/wp-json/buygo-plus-one/v1/orders/${orderId}`, {
                    credentials: 'include',
                    headers: { 'X-WP-Nonce': wpNonce }
                });
                if (!response.ok) throw new Error('Failed to load order items');
                const result = await response.json();
                if (result.success && result.data) {
                    orderItems.value[orderId] = result.data.items || [];
                } else {
                    orderItems.value[orderId] = [];
                }
            } catch (e) {
                console.error('Failed to load order items:', e);
                orderItems.value[orderId] = [];
            } finally {
                loadingOrderItems.value = false;
            }
        };

        
        // 搜尋處理
        const handleSearchSelect = async (item) => {
            searchFilter.value = item.full_name || item.phone || item.email;
            searchFilterName.value = item.full_name || item.phone || item.email;
            currentPage.value = 1;
            await loadCustomers();
        };
        
        const handleSearchInput = (query) => {
            // 搜尋輸入處理（目前無額外邏輯）
        };
        
        const handleSearchClear = () => {
            searchFilter.value = null;
            searchFilterName.value = '';
            currentPage.value = 1;
            loadCustomers();
        };
        
        // 分頁
        const totalPages = computed(() => {
            if (perPage.value === -1) return 1;
            return Math.ceil(totalCustomers.value / perPage.value);
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
                loadCustomers();
            }
        };
        
        const nextPage = () => {
            if (currentPage.value < totalPages.value) {
                currentPage.value++;
                loadCustomers();
            }
        };
        
        const goToPage = (page) => {
            currentPage.value = page;
            loadCustomers();
        };
        
        const changePerPage = () => {
            currentPage.value = 1;
            loadCustomers();
        };

        // 全選/取消全選
        const toggleSelectAll = (event) => {
            if (event.target.checked) {
                selectedItems.value = customers.value.map(c => c.id);
            } else {
                selectedItems.value = [];
            }
        };

        // 檢查是否全選
        const isAllSelected = computed(() => {
            return customers.value.length > 0 && selectedItems.value.length === customers.value.length;
        });

        // 處理客戶選擇（從 smart-search-box 選擇客戶）
        const handleCustomerSelect = (customer) => {
            if (customer && customer.id) {
                navigateTo('detail', customer.id);
            }
        };

        // 初始化
        onMounted(() => {
            loadCustomers();

            // 檢查 URL 參數並設置監聽
            checkUrlParams();
            window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 從 localStorage 讀取使用者幣別偏好
            const savedCurrency = localStorage.getItem('buygo_display_currency');
            if (savedCurrency) {
                displayCurrency.value = savedCurrency;
            }
        });
        
        return {
            customers,
            loading,
            error,
            currentPage,
            perPage,
            totalCustomers,
            totalPages,
            visiblePages,
            previousPage,
            nextPage,
            goToPage,
            changePerPage,
            formatPrice,
            formatDate,
            formatShortDate,
            activeTab,
            filteredOrders,
            customerNote,
            noteSaving,
            noteSaved,
            saveNote,
            getOrderStatusClass,
            getOrderStatusText,
            expandedOrderId,
            orderItems,
            loadingOrderItems,
            toggleOrderExpand,
            systemCurrency,
            currentCurrency,
            handleSearchSelect,
            handleSearchInput,
            handleSearchClear,
            handleCustomerSelect,
            searchFilter,
            searchFilterName,
            loadCustomers,
            toastMessage,
            showToast,
            selectedItems,
            toggleSelectAll,
            isAllSelected,
            // 新增路由相關
            currentView,
            currentCustomerId,
            selectedCustomer,
            detailLoading,
            showMobileSearch,
            globalSearchQuery,
            displayCurrency,
            orderSearchQuery,
            // 新增方法
            navigateTo,
            checkUrlParams,
            loadCustomerDetail,
            handleGlobalSearch,
            toggleCurrency,
            navigateToOrder
        };
    }
};
