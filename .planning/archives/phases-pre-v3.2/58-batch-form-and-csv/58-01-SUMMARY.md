---
phase: 58-batch-form-and-csv
plan: 01
subsystem: ui
tags: [vue3, composable, responsive, tailwind, css, form]

# Dependency graph
requires:
  - phase: 57-route-and-quantity-selection
    provides: "useBatchCreate.js 骨架（step/quota/startFilling）+ BatchCreatePage.js + batch-create.php 模板外殼"
provides:
  - "useBatchCreate composable 表單狀態管理（items CRUD + 配額進度計算）"
  - "響應式批量表單 UI（手機卡片式 + 桌面表格式）"
  - "FORM-01 ~ FORM-04 需求實現"
affects: [58-02, 59-submit-and-feedback]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "bp- CSS 前綴避免全站樣式衝突（延續 Phase 57 慣例）"
    - "bp-mobile-only / bp-desktop-only 響應式斷點 CSS 類（768px 分界）"
    - "items 存字串型態（name/price/quantity/description），避免 number input 的 0 預設值問題"
    - "quotaUsed = quota.current + itemCount（已存在配額 + 表單新增數）"
    - "isFormOverQuota 獨立計算（不複用 isOverQuota，後者依賴 quantity 是數量選擇值）"

key-files:
  created: []
  modified:
    - includes/views/composables/useBatchCreate.js
    - admin/partials/batch-create.php

key-decisions:
  - "price/quantity 存字串而非 number，避免 number input 的 0 預設值問題（與 Phase 57 的 customQuantity 一致）"
  - "isFormOverQuota 獨立計算：表單階段用 itemCount，數量選擇階段用 quantity，兩者語意不同"
  - "quotaUsed = quota.current + itemCount.value：動態反映新增/刪除商品時的配額佔用"
  - "手機版「新增商品」/ 桌面版「新增商品列」文字區分，提升語意清晰度"

patterns-established:
  - "表單 item 使用遞增 nextId（前端追蹤用，非資料庫 ID），Vue v-for :key 追蹤正確"
  - "removeItem 保護最少 1 個：items.value.length <= 1 直接 return"
  - "CSS 行內 style 區塊：Phase 58 區段用 /* ===== Phase 58: 批量表單樣式 ===== */ 分隔"

requirements-completed: [FORM-01, FORM-02, FORM-03, FORM-04]

# Metrics
duration: 25min
completed: 2026-03-03
---

# Phase 58 Plan 01: 批量表單響應式 UI Summary

**Vue 3 響應式批量上架表單：手機卡片式（bp-card）+ 桌面表格式（bp-table），含 items CRUD composable 和配額進度即時更新**

## Performance

- **Duration:** 約 25 分鐘
- **Started:** 2026-03-02T16:29:04Z
- **Completed:** 2026-03-03T00:53:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- useBatchCreate.js 擴充表單狀態：startFilling() 解除 TODO，items ref 陣列管理，addItem/removeItem/initItems，quotaUsed/quotaPercent/isFormOverQuota 計算屬性
- 手機版（<768px）卡片式表單：每個商品一張 bp-card，標題「商品 #N」+藍色數字圓圈，售價和數量並排，垃圾桶刪除按鈕（>1 時顯示）
- 桌面版（>=768px）表格式表單：bp-table 行內直接編輯，表頭 # / 商品名稱 / 售價 / 數量 / 描述 / 操作
- 配額進度即時更新：手機版進度條 + 桌面版 badge，超額時變紅色

## Task Commits

Each task was committed atomically:

1. **Task 1: useBatchCreate composable 擴充 — 表單狀態管理** - `a26fccb` (feat)
2. **Task 2: 響應式表單 UI — 手機卡片 + 桌面表格 + 配額進度** - `6cbf375` (feat)

## Files Created/Modified
- `includes/views/composables/useBatchCreate.js` - 新增表單狀態管理（items/addItem/removeItem/quotaUsed 等），解除 startFilling() Phase 57 TODO
- `admin/partials/batch-create.php` - 替換 step === 'form' 空殼，實作完整響應式表單 UI + 配額進度 CSS

## Decisions Made
- `price` 和 `quantity` 存字串而非 number，避免 number input 的 0 預設值問題（和 Phase 57 的 `customQuantity` 保持一致）
- `isFormOverQuota` 獨立計算（不複用 `isOverQuota`），因為表單階段用 `itemCount`，數量選擇階段用 `quantity`，語意不同不能混用
- `quotaUsed = quota.current + itemCount`：加入 quota.current（已存在商品數），不只看表單裡有幾個

## Deviations from Plan

None — 計劃執行完全按照規格進行，沒有偏差。

## Issues Encountered

None — 現有 Phase 57 骨架的 `startFilling()` 預留接口設計良好，解除 TODO 即可。

## User Setup Required

無需任何外部服務設定。

## Next Phase Readiness

Phase 58 Plan 02 可以繼續實作：
- CSV 匯入功能（FORM-05, CSV-01 ~ CSV-03）：手機版「手動輸入/CSV 匯入」pill 切換，桌面版「匯入 CSV」按鈕
- Phase 59 提交按鈕（SUBMIT-01）：桌面版「批量上架 (N)」按鈕，需使用 `items` 陣列資料

## Self-Check: PASSED

- [x] `includes/views/composables/useBatchCreate.js` — 存在
- [x] `admin/partials/batch-create.php` — 存在
- [x] `.planning/phases/58-batch-form-and-csv/58-01-SUMMARY.md` — 存在
- [x] Commit `a26fccb` (Task 1) — 存在
- [x] Commit `6cbf375` (Task 2) — 存在

---
*Phase: 58-batch-form-and-csv*
*Completed: 2026-03-03*
