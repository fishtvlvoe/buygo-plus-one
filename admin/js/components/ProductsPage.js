/**
 * Products Page Component
 * BuyGo+1 Plugin
 *
 * 商品管理頁面 Vue 組件
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

const ProductsPageComponent = {
    name: 'ProductsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox
    },
    template: '#products-page-template',
    setup() {
        const { ref, computed, watch, onMounted } = Vue;

        // WordPress REST API nonce（用於 API 認證）
        const wpNonce = window.buygoWpNonce || '';

        // 使用 useCurrency Composable 處理幣別邏輯
        const {
            formatPrice,
            convertCurrency,
            getCurrencySymbol,
            systemCurrency,
            currencySymbols,
            exchangeRates
        } = useCurrency();
        
        // --- Router & UI State ---
        const isSidebarCollapsed = ref(false);
        const showMobileMenu = ref(false);
        const showMobileSearch = ref(false);
        const currentTab = ref('products');
        const currentView = ref('list'); // 'list', 'edit', 'allocation', 'buyers'
        const currentId = ref(null);
        const viewMode = ref('table'); // 'table' or 'grid' - 商品列表顯示模式
        
        // --- Data Refs ---
        const products = ref([]);
        const selectedItems = ref([]);
        const loading = ref(true);
        const error = ref(null);
        const globalSearchQuery = ref('');

        // --- Seller Limit State (Phase 19) ---
        const sellerLimit = ref({ can_add: true, current: 0, limit: 0, message: '' });
        
        // --- Sub-page Data ---
        const editingProduct = ref({ id: '', name: '', price: 0, status: 'published', purchased: 0 }); // Initialize with defaults
        const selectedProduct = ref(null);
        
        // Buyers
        const buyers = ref([]);
        const buyersLoading = ref(false);
        const buyersProduct = ref(null);  // 商品資訊（名稱、圖片）
        const allocatingOrderItemId = ref(null);  // 改用 order_item_id
        const buyersSearch = ref('');  // 搜尋客戶名稱
        const buyersCurrentPage = ref(1);
        const buyersPerPage = ref(3);
        const buyersPerPageOptions = [
            { value: 3, label: '3 / 頁' },
            { value: 5, label: '5 / 頁' },
            { value: 20, label: '20 / 頁' },
            { value: 50, label: '50 / 頁' }
        ];

        // 過濾後的下單名單（根據搜尋關鍵字）
        const filteredBuyers = computed(() => {
            if (!buyersSearch.value.trim()) {
                return buyers.value;
            }
            const keyword = buyersSearch.value.toLowerCase().trim();
            return buyers.value.filter(order => {
                const customerName = String(order.customer_name || '').toLowerCase();
                const invoiceNo = String(order.invoice_no || '').toLowerCase();
                return customerName.includes(keyword) || invoiceNo.includes(keyword);
            });
        });

        // 分頁後的下單名單
        const paginatedBuyers = computed(() => {
            const start = (buyersCurrentPage.value - 1) * buyersPerPage.value;
            const end = start + buyersPerPage.value;
            return filteredBuyers.value.slice(start, end);
        });

        // 下單名單總頁數
        const buyersTotalPages = computed(() => {
            return Math.ceil(filteredBuyers.value.length / buyersPerPage.value) || 1;
        });

        // 下單名單分頁資訊
        const buyersStartIndex = computed(() => {
            if (filteredBuyers.value.length === 0) return 0;
            return (buyersCurrentPage.value - 1) * buyersPerPage.value + 1;
        });

        const buyersEndIndex = computed(() => {
            return Math.min(buyersCurrentPage.value * buyersPerPage.value, filteredBuyers.value.length);
        });

        // 下單名單可見頁碼
        const buyersVisiblePages = computed(() => {
            const pages = [];
            const maxPages = Math.min(5, buyersTotalPages.value);
            let startPage = Math.max(1, buyersCurrentPage.value - Math.floor(maxPages / 2));
            let endPage = startPage + maxPages - 1;

            if (endPage > buyersTotalPages.value) {
                endPage = buyersTotalPages.value;
                startPage = Math.max(1, endPage - maxPages + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                pages.push(i);
            }
            return pages;
        });

        // 下單名單分頁方法
        const buyersGoToPage = (page) => {
            if (page < 1 || page > buyersTotalPages.value) return;
            buyersCurrentPage.value = page;
        };

        const buyersHandlePerPageChange = () => {
            buyersCurrentPage.value = 1;
        };

        // 監聽搜尋變化，重置分頁
        watch(buyersSearch, () => {
            buyersCurrentPage.value = 1;
        });

        // 跳轉到訂單詳情頁
        const goToOrderDetail = (orderId) => {
            window.location.href = `/buygo-portal/orders/?view=detail&id=${orderId}`;
        };

        // 統計摘要
        const buyersSummary = computed(() => {
            const summary = {
                totalQuantity: 0,
                totalAllocated: 0,
                totalPending: 0,
                totalShipped: 0
            };
            buyers.value.forEach(order => {
                summary.totalQuantity += order.quantity || 0;
                summary.totalAllocated += order.allocated_quantity || 0;
                summary.totalPending += order.pending_quantity || 0;
                summary.totalShipped += order.shipped_quantity || 0;
            });
            return summary;
        });

        // Allocation
        const productOrders = ref([]);
        const allocationLoading = ref(false);
        const allocationSearch = ref('');

        // 過濾後的訂單列表（根據搜尋關鍵字）
        const filteredProductOrders = computed(() => {
            if (!allocationSearch.value.trim()) {
                return productOrders.value;
            }
            const keyword = allocationSearch.value.toLowerCase().trim();
            return productOrders.value.filter(order => {
                const orderId = String(order.order_id || '').toLowerCase();
                const customer = String(order.customer || '').toLowerCase();
                return orderId.includes(keyword) || customer.includes(keyword);
            });
        });

        // 總分配數量（用於顯示浮動按鈕）
        const totalAllocation = computed(() => {
            return productOrders.value.reduce((acc, o) => acc + (o.allocated || 0), 0);
        });

        // 分配頁面分頁
        const allocationCurrentPage = ref(1);
        const allocationPerPage = ref(3);
        const allocationPerPageOptions = [
            { value: 3, label: '3 / 頁' },
            { value: 5, label: '5 / 頁' },
            { value: 20, label: '20 / 頁' },
            { value: 50, label: '50 / 頁' }
        ];

        // 分頁後的分配訂單列表
        const paginatedProductOrders = computed(() => {
            const start = (allocationCurrentPage.value - 1) * allocationPerPage.value;
            const end = start + allocationPerPage.value;
            return filteredProductOrders.value.slice(start, end);
        });

        // 分配頁面總頁數
        const allocationTotalPages = computed(() => {
            return Math.ceil(filteredProductOrders.value.length / allocationPerPage.value) || 1;
        });

        // 分配頁面分頁資訊
        const allocationStartIndex = computed(() => {
            if (filteredProductOrders.value.length === 0) return 0;
            return (allocationCurrentPage.value - 1) * allocationPerPage.value + 1;
        });

        const allocationEndIndex = computed(() => {
            return Math.min(allocationCurrentPage.value * allocationPerPage.value, filteredProductOrders.value.length);
        });

        // 分配頁面可見頁碼
        const allocationVisiblePages = computed(() => {
            const pages = [];
            const maxPages = Math.min(5, allocationTotalPages.value);
            let startPage = Math.max(1, allocationCurrentPage.value - Math.floor(maxPages / 2));
            let endPage = startPage + maxPages - 1;

            if (endPage > allocationTotalPages.value) {
                endPage = allocationTotalPages.value;
                startPage = Math.max(1, endPage - maxPages + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                pages.push(i);
            }
            return pages;
        });

        // 分配頁面分頁方法
        const allocationGoToPage = (page) => {
            if (page < 1 || page > allocationTotalPages.value) return;
            allocationCurrentPage.value = page;
        };

        const allocationHandlePerPageChange = () => {
            allocationCurrentPage.value = 1;
        };

        // 監聽分配搜尋變化，重置分頁
        watch(allocationSearch, () => {
            allocationCurrentPage.value = 1;
        });

        // Image Modal
        const showImageModal = ref(false);
        const currentImage = ref(null);
        const imageError = ref(null);
        const imageUploading = ref(false); // 圖片上傳中狀態
        const notification = ref(null);
        const fileInput = ref(null);
        const currentProduct = ref(null); // Ensure this is defined once
        
        // Ensure editingProduct has default structure
        // const editingProduct = ref(...); // Already defined above
        
        // Toast
        const toastMessage = ref({ show: false, message: '', type: 'success' });
        
        // Pagination
        const currentPage = ref(1);
        const perPage = ref(5);
        const totalProducts = ref(0);

        // 當前顯示幣別（監聽全域幣別變化）
        const currentCurrency = ref(systemCurrency.value);

        // 監聽全域幣別變化
        watch(systemCurrency, (newCurrency) => {
            console.log('[ProductsPage] 偵測到幣別變化:', newCurrency);
            currentCurrency.value = newCurrency;
        });

        // --- Router Logic (使用 BuyGoRouter 核心模組) ---
        const checkUrlParams = async () => {
            const params = window.BuyGoRouter.checkUrlParams();
            const { view, id } = params;

            if (view && view !== 'list' && id) {
                // 先嘗試在已載入的列表中找
                let product = products.value.find(p => p.id == id);

                // 如果列表中沒有，透過 API 取得單一商品
                if (!product) {
                    try {
                        const res = await fetch(`/wp-json/buygo-plus-one/v1/products?id=${id}`, {
                            credentials: 'include',
                            headers: { 'X-WP-Nonce': wpNonce }
                        });
                        const data = await res.json();
                        if (data.success && data.data && data.data.length > 0) {
                            product = data.data[0];
                        }
                    } catch (e) {
                        console.error('Failed to fetch product:', e);
                    }
                }

                if (product) {
                    handleNavigation(view, product, false);
                } else if (!loading.value) {
                    handleNavigation('list', null, false);
                }
            } else {
                currentView.value = 'list';
            }
        };

        const navigateTo = async (view, product = null, updateUrl = true) => {
            await handleNavigation(view, product, updateUrl);
        };

        const handleNavigation = async (view, product = null, updateUrl = true) => {
            currentView.value = view;

            if (product) {
                currentId.value = product.id;
                selectedProduct.value = product;

                if (updateUrl) {
                    window.BuyGoRouter.navigateTo(view, product.id);
                }

                // Load Data for Sub-pages
                if (view === 'edit') {
                    editingProduct.value = { ...product };
                } else if (view === 'allocation') {
                    await loadProductOrders(product.id);
                } else if (view === 'buyers') {
                    await loadBuyers(product.id);
                }
            } else {
                currentId.value = null;
                selectedProduct.value = null;
                if (updateUrl) {
                    window.BuyGoRouter.goToList();
                }
            }
        };

        const getSubPageTitle = computed(() => {
            if (currentView.value === 'edit') return '編輯商品';
            if (currentView.value === 'allocation') return '庫存分配';
            if (currentView.value === 'buyers') return '下單名單';
            return '';
        });
        
        const isAllSelected = computed(() => {
            return products.value.length > 0 && selectedItems.value.length === products.value.length;
        });

        // 訂單狀態樣式
        const getStatusClass = (status) => {
            const classes = {
                'pending': 'bg-amber-100 text-amber-700',
                'partial': 'bg-blue-100 text-blue-700',
                'allocated': 'bg-green-100 text-green-700',
                'shipped': 'bg-slate-100 text-slate-600'
            };
            return classes[status] || 'bg-slate-100 text-slate-600';
        };

        // 訂單狀態文字
        const getStatusText = (status) => {
            const texts = {
                'pending': '待分配',
                'partial': '部分處理',
                'allocated': '已分配',
                'shipped': '已出貨'
            };
            return texts[status] || '未知';
        };

        // --- Seller Limit Check (Phase 19) ---
        const checkSellerLimit = async () => {
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/limit-check', {
                    credentials: 'include',
                    headers: {
                        'X-WP-Nonce': wpNonce
                    }
                });
                const data = await res.json();
                if (data.success) {
                    sellerLimit.value = data.data;
                }
            } catch (e) {
                console.error('檢查賣家限制失敗:', e);
            }
        };

        // --- API Methods ---
        const loadProducts = async () => {
            loading.value = true;
            try {
                // 加入時間戳記強制繞過所有快取
                let url = `/wp-json/buygo-plus-one/v1/products?page=${currentPage.value}&per_page=${perPage.value}&_t=${Date.now()}`;
                if (globalSearchQuery.value) {
                    url += `&search=${encodeURIComponent(globalSearchQuery.value)}`;
                }
                const res = await fetch(url, {
                    cache: 'no-store',
                    credentials: 'include',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache',
                        'X-WP-Nonce': wpNonce
                    }
                });
                const data = await res.json();
                if (data.success) {
                    // 初始化 Variation 顯示邏輯
                    products.value = data.data
                        .filter(product => product !== null && product !== undefined)
                        .map(product => {
                            // 如果是多樣式商品，設定預設選中的 variation
                            if (product.has_variations && product.default_variation) {
                                product.selected_variation_id = product.default_variation.id;
                                product.selected_variation = product.default_variation;
                            }
                            return product;
                        });
                    // 【修復】使用 API 回傳的總數，而非當前頁的商品數
                    totalProducts.value = data.total || products.value.length;
                    // 並行執行 URL 參數檢查和賣家限制檢查，減少載入時間
                    await Promise.all([
                        checkUrlParams(),
                        checkSellerLimit()
                    ]);
                } else {
                    products.value = [];
                    totalProducts.value = 0;
                    showToast(data.message || '載入失敗', 'error');
                }
            } catch (e) {
                error.value = e.message;
            } finally {
                loading.value = false;
            }
        };

        const loadBuyers = async (id) => {
            buyersLoading.value = true;
            buyersProduct.value = null;
            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${id}/buyers?_t=${Date.now()}`, {
                    cache: 'no-store',
                    credentials: 'include',
                    headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache', 'X-WP-Nonce': wpNonce }
                });
                const data = await res.json();
                if (data.success) {
                    buyers.value = data.data;
                    // 儲存商品資訊
                    if (data.product) {
                        buyersProduct.value = data.product;
                    }
                }
            } catch(e) { console.error(e); }
            finally { buyersLoading.value = false; }
        };

        // 切換到下單名單檢視
        const viewBuyers = (product) => {
            handleNavigation('buyers', product, true);
        };

        // 一鍵分配：將單筆訂單分配
        const allocateOrder = async (order) => {
            if (!currentId.value || !order.order_item_id) return;

            allocatingOrderItemId.value = order.order_item_id;

            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${currentId.value}/allocate-all`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        order_item_id: order.order_item_id
                    })
                });

                const data = await res.json();

                if (data.success) {
                    showToast(`已分配 ${data.total_allocated} 個商品給 ${order.customer_name}`, 'success');
                    // 標記該訂單為已分配
                    order.is_allocated = true;
                    order.allocated_quantity = order.quantity;
                    // 重新載入購買名單以更新狀態
                    await loadBuyers(currentId.value);
                    // 重新載入商品列表以更新已分配數量
                    await loadProducts();
                } else {
                    showToast(data.message || '分配失敗', 'error');
                }
            } catch (e) {
                console.error('一鍵分配錯誤:', e);
                showToast('分配時發生錯誤', 'error');
            } finally {
                allocatingOrderItemId.value = null;
            }
        };

        // 日期格式化（後端已格式化，直接返回；若需解析則處理）
        const formatDate = (dateString) => {
            if (!dateString) return '';
            // 如果已經是 YYYY/MM/DD 格式，直接返回
            if (/^\d{4}\/\d{2}\/\d{2}$/.test(dateString)) {
                return dateString;
            }
            // 嘗試解析日期
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString; // 無法解析則原樣返回
            return `${date.getFullYear()}/${String(date.getMonth() + 1).padStart(2, '0')}/${String(date.getDate()).padStart(2, '0')}`;
        };

        const loadProductOrders = async (id) => {
            allocationLoading.value = true;
             try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${id}/orders`, {
                    cache: 'no-store',
                    credentials: 'include',
                    headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache', 'X-WP-Nonce': wpNonce }
                });
                const data = await res.json();
                // Adapter for old API response structure if needed
                if (data.success) productOrders.value = data.data;
            } catch(e) { console.error(e); }
            finally { allocationLoading.value = false; }
        };

        const saveProduct = async () => {
            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${editingProduct.value.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify(editingProduct.value)
                });
                const data = await res.json();
                if (data.success) {
                    const idx = products.value.findIndex(p => p.id === editingProduct.value.id);
                    if (idx !== -1) products.value[idx] = { ...products.value[idx], ...editingProduct.value };
                    showToast('儲存成功');
                    loadProducts(); // Refresh list
                    navigateTo('list');
                } else {
                    showToast(data.message || '儲存失敗', 'error');
                }
            } catch(e) { showToast('儲存失敗', 'error'); }
        };
        
        const savePurchased = async (product) => {
             // Reuse logic from saveProduct or dedicated endpoint
             try {
                // 如果是多樣式商品，更新選中的 variation 的採購數量
                if (product.has_variations && product.selected_variation_id) {
                    await fetch(`/wp-json/buygo-plus-one/v1/variations/${product.selected_variation_id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                        credentials: 'include',
                        body: JSON.stringify({ purchased: product.purchased })
                    });
                } else {
                    // 單一商品，更新商品本身的採購數量
                    await fetch(`/wp-json/buygo-plus-one/v1/products/${product.id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                        credentials: 'include',
                        body: JSON.stringify({ purchased: product.purchased })
                    });
                }
                showToast('已更新採購數量');
             } catch(e) { console.error(e); }
        };

        const toggleStatus = async (product) => {
            const newStatus = product.status === 'published' ? 'private' : 'published';
             try {
                await fetch(`/wp-json/buygo-plus-one/v1/products/${product.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify({ status: newStatus })
                });
                product.status = newStatus;
             } catch(e) { console.error(e); }
        };

        const deleteProduct = async (id) => {
            if(!window.confirm('確定要刪除此商品嗎？此動作無法復原。')) return;
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/batch-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpNonce },
                    credentials: 'include',
                    body: JSON.stringify({ ids: [id] })
                });
                const data = await res.json();
                
                if (data.success) {
                     products.value = products.value.filter(p => p.id !== id);
                     showToast('已刪除');
                     loadProducts();
                } else {
                     showToast(data.message || '刪除失敗', 'error');
                }
            } catch(e) { console.error(e); showToast('刪除錯誤', 'error'); }
        };
        
        const batchDelete = async () => {
             if(!confirm(`確認刪除 ${selectedItems.value.length} 項？`)) return;
             // Implement batch delete API call
             products.value = products.value.filter(p => !selectedItems.value.includes(p.id));
             selectedItems.value = [];
             showToast('批次刪除成功');
        };

        // SubPage Save Handler
        const handleSubPageSave = async () => {
            if (currentView.value === 'edit') {
                saveProduct();
            } else if (currentView.value === 'allocation') {
                await handleAllocation();
            }
        };
        
        // 處理分配功能
        const handleAllocation = async () => {
            if (!selectedProduct.value) {
                showToast('請選擇商品', 'error');
                return;
            }

            // 準備分配資料
            // 【增量模式】只傳送「本次新分配」的數量，後端會建立新的子訂單
            const allocationData = productOrders.value
                .filter(order => order.allocated && order.allocated > 0)
                .map(order => ({
                    order_id: order.order_id,
                    order_item_id: order.order_item_id || order.id,
                    quantity: order.allocated  // 只傳本次新分配的數量
                }));
            
            if (allocationData.length === 0) {
                showToast('請至少分配一個訂單', 'error');
                return;
            }
            
            try {
                const res = await fetch('/wp-json/buygo-plus-one/v1/products/allocate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpNonce
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        product_id: selectedProduct.value.id,
                        allocations: allocationData
                    })
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showToast('分配成功', 'success');

                    // 計算總分配數量
                    const totalAllocated = allocationData.reduce((sum, alloc) => sum + alloc.quantity, 0);

                    // 立即更新本地商品資料的 allocated 欄位
                    const productIndex = products.value.findIndex(p => p.id === selectedProduct.value.id);
                    if (productIndex !== -1) {
                        products.value[productIndex].allocated = (products.value[productIndex].allocated || 0) + totalAllocated;
                    }

                    // 如果正在編輯的商品是同一個，也更新編輯中的商品
                    if (editingProduct.value && editingProduct.value.id === selectedProduct.value.id) {
                        editingProduct.value.allocated = (editingProduct.value.allocated || 0) + totalAllocated;
                    }

                    // 更新 selectedProduct
                    if (selectedProduct.value) {
                        selectedProduct.value.allocated = (selectedProduct.value.allocated || 0) + totalAllocated;
                    }

                    // 重新載入商品列表（確保資料同步）
                    await loadProducts();
                    // 重新載入訂單資料
                    await loadProductOrders(selectedProduct.value.id);

                    // 通知訂單頁面需要重新載入（用於同步執行出貨按鈕狀態）
                    localStorage.setItem('buygo_allocation_updated', Date.now().toString());

                    // 返回列表
                    navigateTo('list');
                } else {
                    showToast(data.message || '分配失敗', 'error');
                }
            } catch (e) {
                console.error('分配失敗:', e);
                showToast('分配失敗：' + e.message, 'error');
            }
        };
        
        // Image Handling
        const openImageModal = (p) => { currentProduct.value = p; currentImage.value = p.image; showImageModal.value = true; };
        const closeImageModal = () => { showImageModal.value = false; currentProduct.value = null; };
        const triggerFileInput = () => fileInput.value.click();
        const handleFileSelect = async (e) => {
            const file = e.target.files[0];
            if(file) {
                 imageUploading.value = true; // 開始上傳
                 imageError.value = null; // 清除錯誤
                 const formData = new FormData();
                 formData.append('image', file);
                 try {
                     const res = await fetch(`/wp-json/buygo-plus-one/v1/products/${currentProduct.value.id}/image`, {
                         method: 'POST',
                         headers: { 'X-WP-Nonce': wpNonce },
                         credentials: 'include',
                         body: formData
                     });
                     const data = await res.json();
                     if (data.success) {
                         currentImage.value = data.data.image_url;
                         currentProduct.value.image = data.data.image_url;
                         if (editingProduct.value && editingProduct.value.id === currentProduct.value.id) {
                             editingProduct.value.image = data.data.image_url;
                         }
                         showToast('圖片上傳成功');
                         // 上傳成功後自動關閉 Modal
                         setTimeout(() => closeImageModal(), 500);
                     } else {
                         imageError.value = data.message || '上傳失敗';
                     }
                 } catch(err) {
                    imageError.value = '上傳錯誤，請稍後再試';
                 } finally {
                    imageUploading.value = false; // 結束上傳
                    e.target.value = ''; // 清除 file input，允許重新選擇同一檔案
                 }
            }
        };

        // Helpers
        const toggleSelectAll = () => {
            if (isAllSelected.value) selectedItems.value = [];
            else selectedItems.value = products.value.map(p => p.id);
        };

        // 格式化價格（根據 currentCurrency 顯示）
        const formatPriceDisplay = (price, productCurrency = null) => {
            const safePrice = price ?? 0;
            const sourceCurrency = productCurrency || systemCurrency.value;

            // 如果當前顯示幣別與商品幣別相同,直接格式化
            if (currentCurrency.value === sourceCurrency) {
                return formatPrice(safePrice, sourceCurrency);
            }

            // 否則進行匯率轉換
            const convertedPrice = convertCurrency(safePrice, sourceCurrency, currentCurrency.value);
            return formatPrice(convertedPrice, currentCurrency.value);
        };

        // 計算台幣轉換價格（用於顯示參考價格）
        const getTWDPrice = (price, currency) => {
            const safePrice = price ?? 0;
            const rates = exchangeRates.value;
            const rate = rates[currency] || 1;
            return Math.round(safePrice * rate);
        };

        const calculateReserved = (p) => Math.max(0, (p.ordered || 0) - (p.purchased || 0));
        const showToast = (msg, type='success') => { toastMessage.value = { show: true, message: msg, type }; setTimeout(()=> toastMessage.value.show=false, 3000); };

        // Variation 相關方法
        const getDisplayTitle = (product) => {
            if (!product) return '';
            if (product.has_variations && product.selected_variation) {
                const varTitle = product.selected_variation.variation_title;
                // 排除「預設」這個無意義的 variation_title
                return (varTitle && varTitle !== '預設') ? varTitle : product.name;
            }
            const varTitle = product.variation_title;
            // 排除「預設」這個無意義的 variation_title
            return (varTitle && varTitle !== '預設') ? varTitle : product.name;
        };

        const getDisplayPrice = (product) => {
            if (!product) return 0;
            if (product.has_variations && product.selected_variation) {
                return product.selected_variation.price;
            }
            return product.price;
        };

        // 取得顯示用的圖片 URL（優先顯示已選變體的圖片）
        const getDisplayImage = (product) => {
            if (!product) return null;
            if (product.has_variations && product.selected_variation && product.selected_variation.image) {
                return product.selected_variation.image;
            }
            return product.image;
        };

        const onVariationChange = async (product) => {
            if (!product.has_variations || !product.selected_variation_id) return;

            // 找到選中的 variation
            const variation = product.variations.find(v => v.id === product.selected_variation_id);
            if (!variation) return;

            product.selected_variation = variation;

            // 取得該 variation 的統計資料
            try {
                const res = await fetch(`/wp-json/buygo-plus-one/v1/variations/${variation.id}/stats?_t=${Date.now()}`, {
                    cache: 'no-store',
                    credentials: 'include',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache',
                        'X-WP-Nonce': wpNonce
                    }
                });
                const data = await res.json();
                if (data.success) {
                    // 更新商品的統計資料
                    product.ordered = data.data.ordered || 0;
                    product.allocated = data.data.allocated || 0;
                    product.shipped = data.data.shipped || 0;
                    product.purchased = data.data.purchased || 0;
                    product.pending = data.data.pending || 0;
                    product.reserved = data.data.reserved || 0;
                }
            } catch (e) {
                console.error('載入 Variation 統計失敗:', e);
            }
        };

        // Smart Search Box 處理函數
        const handleProductSelect = (product) => {
            if (product && product.id) {
                // 導航到商品編輯頁面
                navigateTo('edit', product);
            }
        };

        // 本地搜尋處理函數(輸入時過濾列表)
        const handleProductSearch = (query) => {
            globalSearchQuery.value = query;
            currentPage.value = 1;  // 重置到第一頁
            loadProducts();
        };

        // 清除搜尋
        const handleProductSearchClear = () => {
            globalSearchQuery.value = '';
            currentPage.value = 1;
            loadProducts();
        };

        onMounted(() => {
            loadProducts();
            // 使用 BuyGoRouter 核心模組的 popstate 監聯
            window.BuyGoRouter.setupPopstateListener(checkUrlParams);

            // 監聽頁面顯示事件（處理 bfcache 和頁面切換）
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) {
                    loadProducts();
                }
            });

            // 監聽頁面可見性變化（從其他標籤頁切換回來）
            // 只要頁面變為可見就重新載入，確保資料永遠是最新的
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    loadProducts();
                }
            });
        });

        return {
            // State
            isSidebarCollapsed, showMobileMenu, showMobileSearch, currentTab, currentView, currentId, viewMode,
            products, selectedItems, loading, error, globalSearchQuery, sellerLimit,
            editingProduct, selectedProduct, buyers, buyersLoading, buyersProduct, buyersSummary, allocatingOrderItemId, productOrders, allocationLoading, allocationSearch, filteredProductOrders, totalAllocation,
            buyersSearch, buyersCurrentPage, buyersPerPage, buyersPerPageOptions, filteredBuyers, paginatedBuyers, buyersTotalPages, buyersStartIndex, buyersEndIndex, buyersVisiblePages, buyersGoToPage, buyersHandlePerPageChange, goToOrderDetail,
            allocationCurrentPage, allocationPerPage, allocationPerPageOptions, paginatedProductOrders, allocationTotalPages, allocationStartIndex, allocationEndIndex, allocationVisiblePages, allocationGoToPage, allocationHandlePerPageChange,
            showImageModal, currentImage, imageUploading, imageError, toastMessage,
            currentPage, perPage, totalProducts, menuItems: [
                { id: 'products', label: '商品管理', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>' },
                { id: 'orders', label: '訂單管理', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>' },
                 { id: 'settings', label: '系統設定', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>' },
            ],

            // Methods
            navigateTo, checkUrlParams, getSubPageTitle, isAllSelected,
            loadProducts, saveProduct, savePurchased, toggleStatus, deleteProduct, batchDelete, allocateOrder, viewBuyers, formatDate,
            getStatusClass, getStatusText,
            handleSubPageSave, openImageModal, closeImageModal, triggerFileInput, handleFileSelect,
            toggleSelectAll, formatPriceDisplay, getTWDPrice, calculateReserved, handleSearchInput: (e) => { globalSearchQuery.value = e.target.value; loadProducts(); },
            handleProductSelect,
            handleProductSearch,
            handleProductSearchClear,
            // Variation 方法
            getDisplayTitle,
            getDisplayPrice,
            getDisplayImage,
            onVariationChange,
            fileInput,
             handleTabClick: (id) => {
                 currentTab.value = id;
                 if (id === 'products') navigateTo('list');
             },
             currentCurrency,
             systemCurrency,
             currencySymbols,
             toggleCurrency: () => {
                 // 在系統幣別和台幣之間切換
                 if (currentCurrency.value === 'TWD') {
                     currentCurrency.value = systemCurrency.value;
                     showToast(`已切換為 ${currencySymbols[systemCurrency.value]} ${systemCurrency.value}`);
                 } else {
                     currentCurrency.value = 'TWD';
                     showToast(`已切換為 ${currencySymbols['TWD']} TWD`);
                 }
             }
        };
    }
};
// 注意：不再自行掛載，由 template.php 統一管理 Vue app
