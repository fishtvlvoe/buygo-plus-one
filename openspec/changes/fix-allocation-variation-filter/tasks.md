## 1. 新增 allocationVariationStats ref 與 watch（Allocation page stats reflect selected variation filter）

- [ ] 1.1 [Tool: cursor] 在 `includes/views/composables/useProducts.js` 的 `allocationVariants` computed 附近，新增 `allocationVariationStats` ref（初始值 `{ ordered: 0, purchased: 0, allocated: 0 }`），以及 `watch(allocationSelectedVariant, async (varId) => {...})` 邏輯：varId 有值時打 `/variations/{varId}/stats` API 更新 ref；varId 為空時清零（讓 computed 回退到 selectedProduct 總計）；切換時先清零避免顯示舊數字（實作 "Switching variation filter updates stats immediately" 及 "Allocation page stats reflect selected variation filter" 需求）

## 2. 新增 allocationPageStats computed（Allocation page stats reflect selected variation filter）

- [ ] 2.1 [Tool: cursor] 在 `useProducts.js` 同區塊，依「新增 allocationPageStats computed，綁定上方統計數字」設計決策，新增 `allocationPageStats` computed：`allocationSelectedVariant` 為空時回傳 `selectedProduct` 的 ordered/purchased/allocated 總計（實作 "All variations selected shows aggregate stats"）；否則回傳 `allocationVariationStats.value`（實作 "Specific variation selected shows variation-only stats"）；確保 `allocationPageStats` 加入 return 物件供 template 使用

## 3. 更新前端 template 綁定（Switching back to All restores aggregate stats）

- [ ] 3.1 [Tool: cursor] 在 `admin/partials/products.php` L975（已下單）、L979（已採購）、L983（可分配）、L987（已分配）四處，將 `selectedProduct?.ordered`、`selectedProduct?.purchased`、`selectedProduct?.allocated` 替換為 `allocationPageStats.ordered`、`allocationPageStats.purchased`、`allocationPageStats.allocated`（可分配公式調整為 `Math.max(0, allocationPageStats.purchased - allocationPageStats.allocated)`）

## 4. 驗收（Order list filter remains independent）

- [ ] 4.1 [Tool: codex] 手動驗證三個情境：選「全部」顯示總計、選「1號」顯示 1號數字、切回「全部」恢復總計；確認下方訂單列表篩選行為不受影響
- [ ] 4.2 [Tool: codex] 執行 `composer test` 確認無回歸
- [ ] 4.3 [Tool: kimi] Code Review：讀取 `useProducts.js` 和 `products.php` 的 diff，確認 watch/computed 邏輯正確、無 race condition、selectedProduct 未被直接修改
