## Context

庫存分配頁面（`currentView === 'allocation'`）的 variation 篩選下拉選單（`v-model="allocationSelectedVariant"`，`products.php` L998）切換時，只有下方訂單列表跟著過濾（透過 `filteredProductOrdersByVariant` computed），但上方統計數字（L972-989）始終綁定 `selectedProduct?.ordered/purchased/allocated`——這是父商品層級的總計，不會隨 variation 切換而改變。

`watch([allocationSearch, allocationSelectedVariant])` 在 `useProducts.js` L276 只重置分頁，完全沒有更新統計的邏輯。

## Goals / Non-Goals

**Goals:**
- 選「全部」：統計數字顯示所有 variation 的加總（現有 `selectedProduct` 的總計）
- 選特定 variation（如 1號）：統計數字顯示該 variation 的個別數字
- 「已下單」顯示購買件數（quantity 加總），不是訂單筆數

**Non-Goals:**
- 不修改後端 API
- 不修改訂單列表的過濾邏輯（已正確運作）
- 不影響分配操作（allocate/deallocate）的邏輯

## Decisions

### 新增 allocationPageStats computed，綁定上方統計數字

**決定**：在 `useProducts.js` 新增 `allocationPageStats` computed ref，根據 `allocationSelectedVariant` 的值決定顯示全部還是單一 variation 的統計：

```js
// 新增位置：useProducts.js allocationVariants computed 附近
const allocationPageStats = computed(() => {
    // 選「全部」：回傳父商品總計
    if (!allocationSelectedVariant.value) {
        return {
            ordered:   selectedProduct.value?.ordered   || 0,
            purchased: selectedProduct.value?.purchased || 0,
            allocated: selectedProduct.value?.allocated || 0,
        };
    }
    // 選特定 variation：打 /variations/{id}/stats API 取得個別數字
    // 但 computed 不能 async，改用獨立的 allocationVariationStats ref 搭配 watch
    return allocationVariationStats.value;
});
```

因為 computed 不能 async，**用 watch + ref 組合**：

```js
const allocationVariationStats = ref({ ordered: 0, purchased: 0, allocated: 0 });

watch(allocationSelectedVariant, async (varId) => {
    if (!varId) {
        // 回到「全部」，清空 variation stats（computed 會自動用 selectedProduct 總計）
        allocationVariationStats.value = { ordered: 0, purchased: 0, allocated: 0 };
        return;
    }
    try {
        const res = await fetch(`/wp-json/buygo-plus-one/v1/variations/${varId}/stats?_t=${Date.now()}`, {
            cache: 'no-store', credentials: 'include', headers: { 'X-WP-Nonce': wpNonce }
        });
        const data = await res.json();
        if (data.success) {
            allocationVariationStats.value = {
                ordered:   data.data.ordered   || 0,
                purchased: data.data.purchased || 0,
                allocated: data.data.allocated || 0,
            };
        }
    } catch (e) {
        console.error('載入 variation 統計失敗:', e);
    }
});
```

將 `admin/partials/products.php` L975/L979/L983/L987 的 `selectedProduct?.xxx` 替換為 `allocationPageStats.ordered/purchased/allocated`，並確保 `allocationPageStats` 在 template 中可用。

**拒絕方案：直接修改 selectedProduct 屬性**
- `selectedProduct` 是引用，修改它會連動影響商品列表顯示
- 離開分配頁後需要還原，容易出錯

**拒絕方案：從 productOrders 計算**
- `productOrders` 只有訂單資料，`purchased`（採購量）是商品層級的數字，無法從訂單推算
- 需要額外 API 才能得到 `purchased`，與方案 A 等效但更複雜

## Risks / Trade-offs

- **[Risk]** 切換 variation 時有短暫 API 請求延遲，統計數字可能短暫顯示舊值 → Mitigation：切換時立即將 `allocationVariationStats` 清零，避免顯示錯誤數字
- **[Trade-off]** `allocationPageStats` computed 依賴 `allocationVariationStats` ref，邏輯分兩層（computed + watch），稍複雜，但符合 Vue 的響應式設計原則
