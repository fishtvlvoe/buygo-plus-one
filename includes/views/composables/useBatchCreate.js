/**
 * useBatchCreate Composable - 批量上架邏輯
 *
 * 功能:
 * - 數量選擇（快選按鈕 5/10/15/20 + 自訂輸入）
 * - 配額查詢（呼叫 /products/limit-check API）
 * - 超額檢查與 CTA 按鈕狀態控制
 * - SPA 導航（返回商品列表 / 進入下一步）
 * - 表單狀態管理（items CRUD + 配額進度）
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
        // 導航
        goBack,
        startFilling
    };
}
