/**
 * useBatchCreate Composable - 批量上架邏輯
 *
 * 功能:
 * - 數量選擇（快選按鈕 5/10/15/20 + 自訂輸入）
 * - 配額查詢（呼叫 /products/limit-check API）
 * - 超額檢查與 CTA 按鈕狀態控制
 * - SPA 導航（返回商品列表 / 進入下一步）
 * - 表單狀態管理（items CRUD + 配額進度）
 * - CSV 匯入（前端解析 + 模式切換）
 *
 * 步驟控制:
 * - 'select' = 數量選擇（Phase 57 實作）
 * - 'form'   = 表單填寫（Phase 58 實作 ✓）
 *
 * 使用方式:
 * const { quantity, selectPreset, canProceed, ... } = useBatchCreate();
 *
 * @version 1.1.0
 * @date 2026-03-02
 */

// 注意: 全域函式，不使用 ES6 import/export（WordPress 環境相容）
function useBatchCreate() {
    const { ref, computed, onMounted } = Vue;
    const { get, post } = useApi();

    // ========== 步驟控制 ==========
    // 'select' = 數量選擇（Phase 57）
    // 'form'   = 表單填寫（Phase 58 實作）
    const step = ref('select');

    // ========== 數量選擇狀態 ==========
    const selectedPreset = ref(null);  // 5, 10, 15, 20 或 null
    const customQuantity = ref('');    // 自訂輸入值（字串，避免 number input 的 0 預設值問題）
    const presetOptions = [5, 10, 15, 20];

    /**
     * 當前選擇的數量
     * 快選按鈕優先，次之為自訂輸入
     */
    const quantity = computed(() => {
        if (selectedPreset.value !== null) return selectedPreset.value;
        const custom = parseInt(customQuantity.value);
        if (!isNaN(custom) && custom >= 1 && custom <= 20) return custom;
        return 0;
    });

    /**
     * 選擇快選按鈕
     * 同時清空自訂輸入
     */
    const selectPreset = (num) => {
        selectedPreset.value = num;
        customQuantity.value = '';
    };

    /**
     * 自訂輸入時取消快選按鈕狀態
     */
    const onCustomInput = () => {
        selectedPreset.value = null;
    };

    // ========== 配額狀態 ==========
    const quota = ref({ can_add: true, current: 0, limit: 0, message: '' });
    const quotaLoading = ref(true);

    /**
     * 剩餘配額數量
     * limit === 0 表示無限制，回傳 Infinity
     */
    const remaining = computed(() => {
        if (quota.value.limit === 0) return Infinity;
        return Math.max(0, quota.value.limit - quota.value.current);
    });

    /**
     * 是否超過配額
     * limit === 0（無限制）時永遠回傳 false
     */
    const isOverQuota = computed(() => {
        if (quota.value.limit === 0) return false;
        return quantity.value > remaining.value;
    });

    /**
     * 「開始填寫」按鈕是否可用
     * 需同時滿足：已選擇數量 + 未超過配額
     */
    const canProceed = computed(() => {
        return quantity.value > 0 && !isOverQuota.value;
    });

    /**
     * 載入配額資訊
     * 呼叫 /wp-json/buygo-plus-one/v1/products/limit-check
     */
    const loadQuota = async () => {
        quotaLoading.value = true;
        try {
            const res = await get('/wp-json/buygo-plus-one/v1/products/limit-check', {
                showError: false
            });
            if (res && res.data) {
                quota.value = res.data;
            }
        } catch (e) {
            console.error('載入配額失敗:', e);
        } finally {
            quotaLoading.value = false;
        }
    };

    // ========== 表單狀態（Phase 58） ==========

    /**
     * 商品表單項目陣列
     * 每個 item: { id: number, name: '', price: '', quantity: '0', description: '' }
     * id 用於 Vue v-for :key 追蹤
     */
    const items = ref([]);
    let nextId = 1;

    /**
     * 建立單一空白商品物件
     */
    const createEmptyItem = () => ({
        id: nextId++,
        name: '',
        price: '',
        quantity: '0',
        description: ''
    });

    /**
     * 初始化 N 個空白商品
     * 由 startFilling() 呼叫
     */
    const initItems = (count) => {
        items.value = [];
        nextId = 1;
        for (let i = 0; i < count; i++) {
            items.value.push(createEmptyItem());
        }
    };

    /**
     * 新增一個空白商品
     */
    const addItem = () => {
        items.value.push(createEmptyItem());
    };

    /**
     * 刪除指定商品（至少保留 1 個）
     */
    const removeItem = (id) => {
        if (items.value.length <= 1) return;
        items.value = items.value.filter(item => item.id !== id);
    };

    /**
     * 目前表單中的商品數量
     */
    const itemCount = computed(() => items.value.length);

    /**
     * 配額進度（已用 + 目前表單中的商品數量）
     * 用於頂部進度條顯示
     */
    const quotaUsed = computed(() => quota.value.current + itemCount.value);

    /**
     * 配額進度百分比（0-100）
     * limit === 0 表示無限制，回傳 0（不顯示進度條）
     */
    const quotaPercent = computed(() => {
        if (quota.value.limit === 0) return 0;
        return Math.min(100, Math.round((quotaUsed.value / quota.value.limit) * 100));
    });

    /**
     * 表單階段的「超額」判斷
     * 和數量選擇階段不同 — 這裡用 itemCount 而非 quantity
     */
    const isFormOverQuota = computed(() => {
        if (quota.value.limit === 0) return false;
        return quotaUsed.value > quota.value.limit;
    });

    // ========== 模式切換 + CSV 匯入（Phase 58 Plan 02） ==========

    /**
     * 表單模式：'manual' = 手動輸入, 'csv' = CSV 匯入
     * 切換時保留已填寫的手動資料
     */
    const formMode = ref('manual');

    /**
     * CSV 匯入相關狀態
     */
    const csvError = ref('');           // CSV 解析錯誤訊息
    const csvSuccessMsg = ref('');      // CSV 匯入成功提示（例如「成功匯入 8 筆」）
    const csvUploading = ref(false);    // 上傳中狀態

    /**
     * 切換表單模式
     * 切換到 csv 模式時保留 items（不清空）
     */
    const setFormMode = (mode) => {
        formMode.value = mode;
        csvError.value = '';
        csvSuccessMsg.value = '';
    };

    /**
     * 解析 CSV 文字內容
     * 支援中英文表頭，自動對應欄位
     * @param {string} text - CSV 原始文字
     * @returns {{ data: Array, error: string }}
     */
    const parseCSV = (text) => {
        const lines = text.trim().split(/\r?\n/);
        if (lines.length < 2) {
            return { data: [], error: 'CSV 至少需要表頭和一行資料' };
        }

        // 解析表頭 — 支援中英文欄位名
        const headerLine = lines[0];
        const headers = headerLine.split(',').map(h => h.trim().replace(/^["']|["']$/g, '').toLowerCase());

        // 欄位對照表
        const fieldMap = {};
        const nameAliases = ['名稱', 'name', '商品名稱', '品名'];
        const priceAliases = ['售價', 'price', '價格', '單價'];
        const qtyAliases = ['數量', 'quantity', 'qty', '庫存'];
        const descAliases = ['描述', 'description', 'desc', '說明'];

        headers.forEach((h, i) => {
            if (nameAliases.includes(h)) fieldMap.name = i;
            else if (priceAliases.includes(h)) fieldMap.price = i;
            else if (qtyAliases.includes(h)) fieldMap.quantity = i;
            else if (descAliases.includes(h)) fieldMap.description = i;
        });

        // 驗證必要欄位
        if (fieldMap.name === undefined) {
            return { data: [], error: 'CSV 缺少「名稱」欄位（支援：名稱、name、商品名稱、品名）' };
        }
        if (fieldMap.price === undefined) {
            return { data: [], error: 'CSV 缺少「售價」欄位（支援：售價、price、價格、單價）' };
        }

        // 解析資料行
        const data = [];
        const errors = [];

        for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;  // 跳過空行

            const cols = line.split(',').map(c => c.trim().replace(/^["']|["']$/g, ''));

            const name = cols[fieldMap.name] || '';
            const price = cols[fieldMap.price] || '';
            const qty = fieldMap.quantity !== undefined ? cols[fieldMap.quantity] : '';
            const desc = fieldMap.description !== undefined ? cols[fieldMap.description] : '';

            // 驗證每行必填
            if (!name) {
                errors.push('第 ' + (i + 1) + ' 行缺少商品名稱');
                continue;
            }
            if (!price || isNaN(Number(price))) {
                errors.push('第 ' + (i + 1) + ' 行售價無效');
                continue;
            }

            // 數量缺失或非數字 → 預設 0（無限）
            const parsedQty = qty && !isNaN(Number(qty)) ? String(Math.max(0, Math.floor(Number(qty)))) : '0';

            data.push({
                name: name,
                price: String(price),
                quantity: parsedQty,
                description: desc
            });
        }

        if (data.length === 0) {
            const errMsg = errors.length > 0
                ? '無有效資料。' + errors.join('；')
                : '無有效資料行';
            return { data: [], error: errMsg };
        }

        return {
            data: data,
            error: errors.length > 0 ? '部分行有錯誤（已跳過）：' + errors.join('；') : ''
        };
    };

    /**
     * 處理 CSV 檔案上傳
     * 前端解析 CSV → 填入 items 陣列
     * @param {Event} event - file input change event
     */
    const handleCsvUpload = (event) => {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        csvError.value = '';
        csvSuccessMsg.value = '';
        csvUploading.value = true;

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const text = e.target.result;
                const result = parseCSV(text);

                if (result.error && result.data.length === 0) {
                    // 完全失敗
                    csvError.value = result.error;
                    csvUploading.value = false;
                    return;
                }

                // 將解析結果填入 items
                // 策略：保留已填寫的手動資料 + 追加 CSV 資料
                const newItems = result.data.map(d => ({
                    id: nextId++,
                    name: d.name,
                    price: d.price,
                    quantity: d.quantity,
                    description: d.description
                }));

                // 保留已有手動填寫資料的 items，只替換空白的
                const filledItems = items.value.filter(item =>
                    item.name.trim() !== '' || String(item.price).trim() !== ''
                );

                items.value = [...filledItems, ...newItems];

                // 如果合併後沒有項目，至少保留 CSV 的
                if (items.value.length === 0 && newItems.length > 0) {
                    items.value = newItems;
                }

                csvSuccessMsg.value = '成功匯入 ' + result.data.length + ' 筆商品';
                if (result.error) {
                    csvSuccessMsg.value += '（' + result.error + '）';
                }

                // 切回手動模式讓使用者編輯
                formMode.value = 'manual';
            } catch (err) {
                csvError.value = '檔案讀取失敗：' + err.message;
            } finally {
                csvUploading.value = false;
                // 重置 file input，允許重複上傳同一檔案
                event.target.value = '';
            }
        };
        reader.onerror = () => {
            csvError.value = '檔案讀取失敗';
            csvUploading.value = false;
        };
        reader.readAsText(file);
    };

    /**
     * 拖放狀態（控制上傳區的 .dragging 視覺回饋）
     */
    const isDragging = ref(false);

    /**
     * 處理拖放上傳
     * 從 dataTransfer 取得檔案，走和 handleCsvUpload 相同的 FileReader 路徑
     * @param {DragEvent} event - drop event
     */
    const handleDrop = (event) => {
        isDragging.value = false;
        const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
        if (!file) return;
        if (!file.name.endsWith('.csv')) {
            csvError.value = '請上傳 .csv 格式的檔案';
            return;
        }

        csvError.value = '';
        csvSuccessMsg.value = '';
        csvUploading.value = true;

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const text = e.target.result;
                const result = parseCSV(text);

                if (result.error && result.data.length === 0) {
                    csvError.value = result.error;
                    csvUploading.value = false;
                    return;
                }

                const newItems = result.data.map(d => ({
                    id: nextId++,
                    name: d.name,
                    price: d.price,
                    quantity: d.quantity,
                    description: d.description
                }));

                const filledItems = items.value.filter(item =>
                    item.name.trim() !== '' || String(item.price).trim() !== ''
                );

                items.value = [...filledItems, ...newItems];

                if (items.value.length === 0 && newItems.length > 0) {
                    items.value = newItems;
                }

                csvSuccessMsg.value = '成功匯入 ' + result.data.length + ' 筆商品';
                if (result.error) {
                    csvSuccessMsg.value += '（' + result.error + '）';
                }

                formMode.value = 'manual';
            } catch (err) {
                csvError.value = '檔案讀取失敗：' + err.message;
            } finally {
                csvUploading.value = false;
            }
        };
        reader.onerror = () => {
            csvError.value = '檔案讀取失敗';
            csvUploading.value = false;
        };
        reader.readAsText(file);
    };

    /**
     * 清除 CSV 提示訊息
     */
    const clearCsvMessages = () => {
        csvError.value = '';
        csvSuccessMsg.value = '';
    };

    // ========== 提交與結果（Phase 59） ==========

    /**
     * 提交中狀態（控制按鈕 disabled + spinner）
     */
    const submitting = ref(false);

    /**
     * 全局提交錯誤訊息（網路錯誤等）
     */
    const submitError = ref('');

    /**
     * 有效商品：name 和 price 都已填寫且 price > 0
     */
    const validItems = computed(() => {
        return items.value.filter(item =>
            item.name.trim() !== '' &&
            String(item.price).trim() !== '' &&
            Number(item.price) > 0
        );
    });

    /**
     * 有效商品數量
     */
    const validItemCount = computed(() => validItems.value.length);

    /**
     * 清除所有 items 的 _error 屬性
     * 在重新提交前呼叫
     */
    const clearItemErrors = () => {
        items.value.forEach(item => {
            delete item._error;
        });
    };

    /**
     * 提交批量上架
     * 呼叫 POST /products/batch-create API
     * 處理三種結果：全部成功 / 部分失敗 / 全部失敗
     */
    const submitBatch = async () => {
        // 防重複
        if (submitting.value) return;
        // 前置驗證
        if (validItemCount.value === 0) return;

        submitting.value = true;
        submitError.value = '';
        clearItemErrors();

        // 構建 payload（後端 API 欄位名稱為 title，非 name）
        const payload = {
            items: validItems.value.map(item => ({
                title: item.name.trim(),
                price: String(item.price).trim(),
                quantity: item.quantity || '0',
                description: item.description.trim()
            }))
        };

        try {
            // 直接用 fetch 繞過 useApi 的 success 檢查
            // 因為 batch-create 回傳的 success 欄位需要在這裡自行處理三種結果
            const response = await window.fetch('/wp-json/buygo-plus-one/v1/products/batch-create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.buygoWpNonce || '',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'include',
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errBody = await response.json().catch(() => ({}));
                throw new Error(errBody.message || errBody.error || 'HTTP ' + response.status);
            }

            const res = await response.json();

            if (res.failed === 0 && res.created > 0) {
                // 全部成功 — toast + 跳回商品列表
                if (window.showToast) {
                    window.showToast('成功上架 ' + res.created + ' 個商品', 'success');
                }
                setTimeout(() => {
                    goBack();
                }, 800);
            } else if (res.created > 0 && res.failed > 0) {
                // 部分失敗 — 移除成功的，標記失敗的
                if (window.showToast) {
                    window.showToast('成功 ' + res.created + ' 個，失敗 ' + res.failed + ' 個', 'error');
                }

                // 建立 validItems 到原始 items 的對應
                const validItemIds = validItems.value.map(item => item.id);
                const failedIndices = new Set();
                const failedErrors = {};

                if (res.results) {
                    res.results.forEach(result => {
                        if (!result.success) {
                            // result.index 對應 payload.items 的 index，也就是 validItems 的 index
                            const itemId = validItemIds[result.index];
                            failedIndices.add(itemId);
                            failedErrors[itemId] = result.error || '上架失敗';
                        }
                    });
                }

                // 只保留失敗的 + 無效的（未提交的）
                items.value = items.value.filter(item => {
                    // 保留無效商品（不在 validItems 中的）
                    if (!validItemIds.includes(item.id)) return true;
                    // 保留失敗的
                    return failedIndices.has(item.id);
                });

                // 標記錯誤
                items.value.forEach(item => {
                    if (failedErrors[item.id]) {
                        item._error = failedErrors[item.id];
                    }
                });
            } else {
                // 全部失敗（created === 0）
                const errorMsg = (res.results && res.results[0] && res.results[0].error)
                    || res.error || '請檢查商品資料';
                if (window.showToast) {
                    window.showToast('上架失敗：' + errorMsg, 'error');
                }
                // 標記所有 items 的 _error
                if (res.results) {
                    const validItemIds = validItems.value.map(item => item.id);
                    res.results.forEach(result => {
                        if (!result.success && validItemIds[result.index]) {
                            const targetItem = items.value.find(item => item.id === validItemIds[result.index]);
                            if (targetItem) {
                                targetItem._error = result.error || '上架失敗';
                            }
                        }
                    });
                }
            }
        } catch (err) {
            // 網路錯誤 / API 完全無回應
            submitError.value = err.message || '網路錯誤，請稍後重試';
            if (window.showToast) {
                window.showToast('上架失敗：' + (err.message || '網路錯誤，請稍後重試'), 'error');
            }
        } finally {
            submitting.value = false;
        }
    };

    // ========== 導航 ==========

    /**
     * 返回商品列表頁（SPA 導航）
     */
    const goBack = () => {
        if (window.BuyGoRouter) {
            window.BuyGoRouter.spaNavigate('products');
        }
    };

    /**
     * 開始填寫（進入 Phase 58 表單步驟）
     */
    const startFilling = () => {
        if (!canProceed.value) return;
        step.value = 'form';
        initItems(quantity.value);
    };

    // ========== 生命週期 ==========
    onMounted(() => {
        loadQuota();
    });

    return {
        // 步驟
        step,
        // 數量選擇
        selectedPreset,
        customQuantity,
        presetOptions,
        quantity,
        selectPreset,
        onCustomInput,
        // 配額
        quota,
        quotaLoading,
        remaining,
        isOverQuota,
        canProceed,
        loadQuota,
        // 表單（Phase 58）
        items, createEmptyItem, initItems, addItem, removeItem,
        itemCount, quotaUsed, quotaPercent, isFormOverQuota,
        // CSV 匯入（Phase 58 Plan 02）
        formMode, csvError, csvSuccessMsg, csvUploading, isDragging,
        setFormMode, parseCSV, handleCsvUpload, handleDrop, clearCsvMessages,
        // 提交（Phase 59）
        submitting, submitError, validItems, validItemCount,
        submitBatch, clearItemErrors,
        // 導航
        goBack,
        startFilling
    };
}
