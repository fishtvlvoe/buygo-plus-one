/**
 * useBatchCreate Composable - 批量上架邏輯
 *
 * 功能:
 * - 數量選擇（快選按鈕 5/10/15/20 + 自訂輸入）
 * - 配額查詢（呼叫 /products/limit-check API）
 * - 超額檢查與 CTA 按鈕狀態控制
 * - SPA 導航（返回商品列表 / 進入下一步）
 *
 * 步驟控制:
 * - 'select' = 數量選擇（Phase 57 實作）
 * - 'form'   = 表單填寫（Phase 58 實作）
 *
 * 使用方式:
 * const { quantity, selectPreset, canProceed, ... } = useBatchCreate();
 *
 * @version 1.0.0
 * @date 2026-03-02
 */

// 注意: 全域函式，不使用 ES6 import/export（WordPress 環境相容）
function useBatchCreate() {
    const { ref, computed, onMounted } = Vue;
    const { get } = useApi();

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
     * Phase 58 實作前暫時只記錄 log
     */
    const startFilling = () => {
        if (!canProceed.value) return;
        // Phase 58 實作：step.value = 'form';
        console.log('開始填寫', quantity.value, '個商品');
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
        // 導航
        goBack,
        startFilling
    };
}
