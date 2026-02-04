---
phase: 39
plan: 01
subsystem: admin-ui
tags: [fluentcart, seller-grant, product-validation, admin-settings]
requires:
  - phase-38-ui-refactor
provides:
  - seller-product-id-settings
  - product-validation-ajax
affects:
  - phase-39-02-order-hook-integration
tech-stack:
  added: []
  patterns:
    - ajax-validation-pattern
    - real-time-product-verification
key-files:
  created: []
  modified:
    - includes/admin/class-settings-page.php
    - admin/js/admin-settings.js
decisions:
  - SETTINGS-UI-PLACEMENT: "在角色權限頁面頂部新增設定區塊"
  - VALIDATION-TIMING: "即時驗證（點擊驗證按鈕時）"
  - PRODUCT-TYPE-CHECK: "虛擬商品驗證使用 fct_products.is_shippable = 0"
duration: 113
completed: 2026-02-04
---

# Phase 39 Plan 01: 賣家商品 ID 設定介面 Summary

**One-liner:** 在角色權限設定頁面新增 FluentCart 賣家商品 ID 設定區塊，支援即時驗證商品存在性和虛擬商品類型

## What Was Built

### 設定區塊 UI
在角色權限設定頁面（`?page=buygo-settings&tab=roles`）頂部新增「FluentCart 自動賦予設定」區塊：

- **商品 ID 輸入框**：輸入 FluentCart 商品 ID
- **驗證按鈕**：即時驗證商品
- **驗證結果顯示**：顯示商品名稱、價格、虛擬商品狀態、後台連結
- **儲存按鈕**：儲存到 `buygo_seller_product_id` option

### AJAX 驗證系統
實作 `ajax_validate_seller_product()` handler：

1. **商品存在性驗證**
   - 查詢 `wp_posts` 表（post_type = 'fct_product'）
   - 確認商品狀態為 publish

2. **虛擬商品驗證**
   - 查詢 `wp_fct_products` 表
   - 檢查 `is_shippable = 0`（虛擬商品不需物流）
   - 拒絕實體商品（避免賣家商品需要物流地址）

3. **商品資訊回傳**
   - 商品名稱、價格、虛擬商品狀態
   - FluentCart 後台編輯連結

### 前端驗證邏輯
JavaScript 實作（`admin-settings.js`）：

- **即時驗證**：點擊「驗證商品」按鈕觸發 AJAX
- **成功顯示**：綠色背景顯示商品詳細資訊
- **錯誤顯示**：紅色文字顯示錯誤訊息
- **輸入清除**：輸入時自動清除驗證結果

## Tasks Completed

| Task | Name | Commit | Files Modified |
|------|------|--------|----------------|
| 1 | 新增賣家商品 ID 設定區塊到角色權限頁面 | e3d12ad | class-settings-page.php |
| 2 | 實作商品驗證 AJAX handler | 021f064 | class-settings-page.php |
| 3 | 實作前端驗證邏輯 | 87edb40 | admin-settings.js |

## Decisions Made

### SETTINGS-UI-PLACEMENT
**Context:** 設定區塊應該放在哪裡？

**Options considered:**
- 獨立的 Tab
- 角色權限頁面內
- 通知設定頁面內

**Decision:** 放在角色權限頁面頂部

**Rationale:**
- 與角色賦予功能語意相關
- 避免過多 Tab（UX 簡化）
- 使用 .card 樣式區隔清楚

### VALIDATION-TIMING
**Context:** 何時觸發商品驗證？

**Options considered:**
- 輸入時即時驗證（on input）
- 點擊按鈕驗證（on click）
- 儲存時才驗證（on submit）

**Decision:** 點擊「驗證商品」按鈕時驗證

**Rationale:**
- 避免頻繁查詢資料庫（輸入時）
- 給使用者明確的驗證觸發點
- 儲存前可多次驗證不同商品

### PRODUCT-TYPE-CHECK
**Context:** 如何判斷虛擬商品？

**Options considered:**
- 檢查 `wp_postmeta._require_shipping`
- 檢查 `wp_fct_products.is_shippable`

**Decision:** 使用 `fct_products.is_shippable = 0`

**Rationale:**
- FluentCart 標準欄位
- 直接查詢商品表效率高
- `is_shippable = 0` 明確代表虛擬商品

## Technical Details

### 資料庫查詢
```php
// 商品基本資訊
SELECT ID, post_title, post_status
FROM wp_posts
WHERE ID = %d AND post_type = 'fct_product'

// FluentCart 商品資訊
SELECT price, is_shippable
FROM wp_fct_products
WHERE id = %d
```

### Option 儲存
```php
update_option('buygo_seller_product_id', $product_id);
```

### AJAX 回應格式
```json
{
  "success": true,
  "data": {
    "product": {
      "id": 123,
      "title": "賣家方案",
      "price": 1000,
      "is_virtual": true,
      "admin_url": "..."
    }
  }
}
```

## Deviations from Plan

無 - 計畫完全按照執行。

## Testing Performed

✅ 手動測試（需用戶驗證）：
- 瀏覽角色權限頁面，確認設定區塊顯示
- 輸入有效商品 ID，驗證成功顯示商品資訊
- 輸入無效商品 ID，驗證顯示錯誤訊息
- 輸入實體商品 ID，驗證拒絕並顯示錯誤
- 儲存設定，重新整理頁面，確認設定值保留

## Known Issues

無

## Next Phase Readiness

**Ready for Phase 39-02:**
✅ 商品 ID 設定已完成
✅ 驗證機制已實作
✅ Option 儲存機制已建立

**Next steps:**
1. 實作 FluentCart `order_paid` hook 監聽
2. 檢查訂單商品是否為設定的賣家商品
3. 自動賦予 `buygo_admin` 角色和預設配額

**Blockers:** 無

**Concerns:** 無

## Lessons Learned

### What Went Well
- ✅ 使用現有的 AJAX 模式（參考 `product_limit` 更新）
- ✅ UI 設計與現有頁面風格一致（.card 樣式）
- ✅ 驗證邏輯清晰（商品存在 → 狀態 → 類型）

### What Could Be Better
- 可考慮加入商品圖片預覽（但可能增加複雜度）
- 可考慮支援多個商品 ID（但當前需求只需單一商品）

### Technical Debt
無

## Performance Metrics

- **Execution time:** 113 seconds (~2 minutes)
- **Tasks completed:** 3/3 (100%)
- **Commits:** 3
- **Files modified:** 2

## References

- Phase 39 CONTEXT: `.planning/phases/39-fluentcart-自動賦予賣家權限-/39-CONTEXT.md`
- Phase 39 RESEARCH: `.planning/phases/39-fluentcart-自動賦予賣家權限-/39-RESEARCH.md`
- FluentCart Product Structure: `wp_posts` (post_type = 'fct_product') + `wp_fct_products`

---

*Summary created: 2026-02-04*
*Phase: 39-fluentcart-自動賦予賣家權限*
*Plan: 01 - 賣家商品 ID 設定介面*
