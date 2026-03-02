---
phase: 58-batch-form-and-csv
plan: 02
subsystem: ui
tags: [vue3, composable, csv, filereader, responsive, tailwind, php]

# Dependency graph
requires:
  - phase: 58-batch-form-and-csv
    plan: 01
    provides: "useBatchCreate.js 表單狀態管理（items/addItem/removeItem/quotaUsed）+ batch-create.php 響應式表單 UI"
provides:
  - "CSV 前端解析邏輯（parseCSV + FileReader + 拖放支援）"
  - "formMode 模式切換（manual/csv），切換時保留 items 資料"
  - "手機版 pill 切換 UI（手動輸入/CSV 匯入）"
  - "桌面版「匯入 CSV」按鈕"
  - "FORM-05, CSV-01 ~ CSV-03 需求實現"
affects: [59-submit-and-feedback]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "FileReader API 前端讀取 CSV，不送後端解析"
    - "CSV 表頭支援中英文別名（名稱/name/商品名稱/品名 等），降低賣家門檻"
    - "數量缺失預設 '0' = 無限上架（延續 items 存字串型態的慣例）"
    - "CSV 匯入策略：保留已填寫的手動資料 + 追加 CSV 資料"
    - "匯入後自動切回 manual 模式，讓賣家繼續在表單中編輯"

key-files:
  created: []
  modified:
    - includes/views/composables/useBatchCreate.js
    - admin/partials/batch-create.php

key-decisions:
  - "CSV 解析完全在前端做（FileReader + 字串分割），不送後端，符合 STATE.md 已定案決策"
  - "匯入策略：保留已有手動填寫資料（name/price 非空的 items）+ 追加 CSV 資料，避免覆蓋賣家已輸入的內容"
  - "數量缺失或非數字時預設 '0'（無限上架），和 items 的 quantity 欄位設計一致"
  - "手機版 CSV 模式：隱藏卡片表單（v-if），顯示上傳區；桌面版永遠顯示表格，CSV 按鈕獨立"
  - "handleCsvUpload 和 handleDrop 走相同 FileReader 路徑，DRY 設計（重複程式碼接受，因為是不同觸發路徑）"

patterns-established:
  - "bp-pill / bp-pill-group：手機版模式切換 pill 按鈕元件 CSS"
  - "bp-csv-upload / bp-csv-btn / bp-toast：CSV 上傳區、按鈕、提示訊息 CSS 前綴"
  - "file input value 重置（event.target.value = ''），允許重複上傳同一檔案"

requirements-completed: [FORM-05, CSV-01, CSV-02, CSV-03]

# Metrics
duration: 27min
completed: 2026-03-03
---

# Phase 58 Plan 02: CSV 匯入功能 + 模式切換 UI Summary

**FileReader 前端 CSV 解析（支援中英文表頭）+ 手機版 pill 切換 + 桌面版匯入按鈕 + 拖放上傳 + 匯入結果提示**

## Performance

- **Duration:** 約 27 分鐘
- **Started:** 2026-03-02T17:40:35Z
- **Completed:** 2026-03-03T00:07:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- useBatchCreate.js 新增 CSV 完整邏輯：formMode 切換、parseCSV()（中英文表頭別名 + 逐行驗證）、handleCsvUpload()（FileReader）、handleDrop()（拖放）、isDragging 視覺狀態、csvError/csvSuccessMsg 提示訊息
- 手機版新增 pill 切換「手動輸入」/「CSV 匯入」，切到 CSV 模式顯示上傳區（虛線邊框 + 拖放 + 點擊）
- 桌面版在配額 badge 旁加入「匯入 CSV」按鈕（label 包裝 file input，無需額外 JS）
- 匯入成功：自動切回 manual 模式 + 綠色提示條「成功匯入 N 筆商品」；匯入失敗：紅色提示條含錯誤說明

## Task Commits

Each task was committed atomically:

1. **Task 1: useBatchCreate composable 擴充 — CSV 解析 + 模式切換** - `9e891e0` (feat)
2. **Task 2: CSV 匯入 UI — pill 切換 + 上傳區 + 匯入結果提示** - `2fb23f8` (feat)

## Files Created/Modified
- `includes/views/composables/useBatchCreate.js` - 新增 CSV 解析邏輯、模式切換、拖放狀態、提示訊息狀態，擴充 return 物件
- `admin/partials/batch-create.php` - 新增 CSV 專用 CSS（pill/上傳區/提示/按鈕）、手機版 pill 切換 UI、CSV 上傳區、桌面版 CSV 按鈕、手機版卡片加 v-if="formMode === 'manual'"

## Decisions Made
- CSV 解析在前端用 FileReader + 字串分割，不送後端（已在 STATE.md 定案）
- 匯入策略：保留已填寫的 items + 追加 CSV（不清空），避免覆蓋賣家已手動輸入的商品
- 數量缺失或非數字預設 '0'（無限上架），延續 items 使用字串型態的慣例
- handleCsvUpload 和 handleDrop 走相同 FileReader 路徑（略有重複程式碼，但觸發來源不同，可接受）

## Deviations from Plan

None — 計劃執行完全按照規格進行，沒有偏差。

## Issues Encountered

None — Plan 01 建立的 useBatchCreate 骨架（items/nextId/return 結構）完整，本次擴充接合順暢。

## User Setup Required

無需任何外部服務設定。

## Next Phase Readiness

Phase 59 提交與結果回饋可以繼續實作：
- SUBMIT-01：桌面版「批量上架 (N)」按鈕，讀取 `items` 陣列資料
- SUBMIT-02 ~ SUBMIT-04：呼叫 Phase 56 後端 API，處理成功/失敗回饋

## Self-Check: PASSED

- [x] `includes/views/composables/useBatchCreate.js` — 存在
- [x] `admin/partials/batch-create.php` — 存在
- [x] `.planning/phases/58-batch-form-and-csv/58-02-SUMMARY.md` — 存在
- [x] Commit `9e891e0` (Task 1) — 存在
- [x] Commit `2fb23f8` (Task 2) — 存在

---
*Phase: 58-batch-form-and-csv*
*Completed: 2026-03-03*
