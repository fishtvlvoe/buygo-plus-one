---
phase: 57-route-and-quantity-selection
plan: 02
subsystem: ui
tags: [vue3, tailwind, composable, spa, php]

requires:
  - phase: 57-01
    provides: batch-create SPA 路由入口和空殼頁面（Plan 02 替換為完整 UI）
  - phase: 56-batch-create-api
    provides: /products/limit-check API 端點（配額查詢）

provides:
  - useBatchCreate composable（全域函式，數量選擇邏輯 + 配額查詢）
  - BatchCreatePage 完整數量選擇 UI（快選 5/10/15/20 + 自訂輸入 + 配額顯示 + CTA 按鈕）
  - admin/js/components/BatchCreatePage.js（thin shell 元件）

affects:
  - 58-batch-form（使用 useBatchCreate.step 切換至 form 步驟）

tech-stack:
  added: []
  patterns:
    - "useBatchCreate composable：快選按鈕（selectedPreset）與自訂輸入（customQuantity）互斥狀態模式"
    - "quota.limit === 0 表示無限制，remaining 回傳 Infinity，永不觸發超額邏輯"
    - "行內 <style> 標籤在 partial 中定義元件專屬 CSS（繞過 InstaWP WAF）"
    - "CSS class 命名加前綴（.batch-preset-btn, .bp-number）避免與全站 Tailwind 衝突"

key-files:
  created:
    - includes/views/composables/useBatchCreate.js
    - admin/js/components/BatchCreatePage.js
  modified:
    - admin/partials/batch-create.php
    - includes/views/template.php

key-decisions:
  - "customQuantity 存為字串而非 number，避免 number input 的 0 預設值問題（空字串 vs 0 的語意差異）"
  - "quota.limit === 0 定義為無限制，remaining 計算為 Infinity，isOverQuota 永遠 false"
  - "CSS class 加 bp- 前綴（bp-number, bp-unit）避免與全站樣式衝突"
  - "BatchCreatePage.js 獨立成檔（而非嵌入 batch-create.php），維持 composable/component/partial 三層分離慣例"

patterns-established:
  - "步驟機器（step ref）模式：'select' → 'form' → 未來更多步驟，Phase 58 只需改 startFilling()"

requirements-completed: [SELECT-01, SELECT-02, SELECT-03, SELECT-04]

duration: 10min
completed: 2026-03-02
---

# Phase 57 Plan 02: 路由與數量選擇 Summary

**Vue3 useBatchCreate composable + 完整數量選擇頁 UI，含快選按鈕互斥邏輯、配額 API 查詢、超額禁用 CTA**

## Performance

- **Duration:** 10 min
- **Started:** 2026-03-02T11:08:14Z
- **Completed:** 2026-03-02T11:18:51Z
- **Tasks:** 2
- **Files modified:** 4 (2 created, 2 modified)

## Accomplishments

- useBatchCreate.js composable 實作完整數量選擇邏輯：快選按鈕（5/10/15/20）與自訂輸入互斥切換，computed 屬性 quantity/remaining/isOverQuota/canProceed
- 呼叫 `/wp-json/buygo-plus-one/v1/products/limit-check` 查詢配額，三態顯示（載入中 / 有上限 / 無限制）
- batch-create.php 重寫為完整數量選擇頁，含頂部導航、圖示、快選按鈕區、分隔線、自訂輸入、配額資訊、底部 sticky CTA
- BatchCreatePage.js thin shell 元件（`setup() { return useBatchCreate(); }`），符合現有 composable 模式

## Task Commits

每個 Task 獨立提交：

1. **Task 1: useBatchCreate composable — 數量選擇邏輯** - `768308d` (feat)
2. **Task 2: 數量選擇頁 UI — BatchCreatePage 模板 + 元件** - `94b6ece` (feat)

## Files Created/Modified

- `includes/views/composables/useBatchCreate.js` — 新建，全域函式，數量選擇 + 配額查詢邏輯
- `admin/js/components/BatchCreatePage.js` — 新建，thin shell 元件定義
- `admin/partials/batch-create.php` — 重寫，Plan 01 空殼替換為完整數量選擇 UI
- `includes/views/template.php` — 加入 useBatchCreate.js 載入（頁面 Composables 區塊）

## Decisions Made

- **customQuantity 用字串**：`v-model` 配合 `type="number"` 時，空字串比 0 更能正確表達「未輸入」狀態，避免 computed 誤判 0 為有效數量
- **quota.limit === 0 = 無限制**：符合後端 API 設計（limit-check 回傳 0 表示無上限），remaining 給 Infinity 讓超額邏輯永遠不觸發
- **CSS 加 bp- 前綴**：批量上架頁面的 number/unit class 改為 bp-number/bp-unit，避免被全站其他 partial 的同名 class 覆蓋

## Deviations from Plan

None — 計畫執行完全照原計畫進行。

## Issues Encountered

None

## User Setup Required

None — 純前端變更，無需外部服務配置。

## Next Phase Readiness

- Phase 57 兩個 Plan 全部完成：路由入口（Plan 01）+ 數量選擇 UI（Plan 02）
- Phase 58 可立即開始實作批量表單 + CSV 匯入
- `useBatchCreate.startFilling()` 已預留接口：Phase 58 只需將 `step.value = 'form'` 解除註解並實作表單邏輯
- 無阻礙事項

---
*Phase: 57-route-and-quantity-selection*
*Completed: 2026-03-02*

## Self-Check: PASSED

- FOUND: includes/views/composables/useBatchCreate.js
- FOUND: admin/js/components/BatchCreatePage.js
- FOUND: admin/partials/batch-create.php
- FOUND: includes/views/template.php
- FOUND commit: 768308d (Task 1)
- FOUND commit: 94b6ece (Task 2)
