---
phase: 59-submit-and-feedback
plan: 01
subsystem: ui
tags: [vue3, composable, rest-api, responsive, tailwind, php, batch-create]

# Dependency graph
requires:
  - phase: 58-batch-form-and-csv
    plan: 02
    provides: "useBatchCreate.js 表單狀態管理 + CSV 匯入 + batch-create.php 響應式表單 UI"
provides:
  - "submitBatch() 呼叫 POST /products/batch-create API，三種結果處理（全部成功/部分失敗/全部失敗）"
  - "validItems/validItemCount computed 篩選有效商品（name + price 必填）"
  - "手機版底部固定欄（有效商品數 + 藍色批量上架按鈕）"
  - "桌面版右上角「批量上架 (N)」按鈕"
  - "失敗商品紅色邊框 + 錯誤原因顯示（手機卡片 + 桌面表格行）"
  - "桌面版表格下方淡灰色提示文字"
  - "SUBMIT-01 ~ SUBMIT-04 需求實現，v3.2 milestone 完成"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "useApi() 解構 get + post，submitBatch 使用 post() 呼叫批量上架 API"
    - "validItems computed 過濾有效商品（name.trim() 非空 + price > 0），validItemCount 驅動按鈕狀態"
    - "_error 臨時屬性標記失敗商品，clearItemErrors() 在重試前清除"
    - "部分失敗時自動移除成功商品，保留失敗 + 未提交的商品"
    - "桌面版 template v-for 包裝多個 tr（資料行 + 錯誤行），解決 Vue scope 問題"

key-files:
  created: []
  modified:
    - includes/views/composables/useBatchCreate.js
    - admin/partials/batch-create.php

key-decisions:
  - "useApi() 改為解構 get + post，直接呼叫 POST /products/batch-create API"
  - "部分失敗時的 index 對應：API results[i].index -> validItems[index] -> items[id]，透過 id 精確對應"
  - "桌面版錯誤行用 template v-for 包裝（非 plan 原始設計），確保 item 在 error row 的 scope 中可存取"
  - "手機版底部固定欄用 sticky bottom-0，和數量選擇步驟的底部按鈕一致"

patterns-established:
  - "bp-submit-bar / bp-submit-btn / bp-submit-desktop / bp-spinner：提交按鈕相關 CSS 前綴"
  - "bp-error-msg / bp-card.error / bp-table tr.error：失敗標記 CSS"
  - "bp-hint：淡灰色提示文字"

requirements-completed: [SUBMIT-01, SUBMIT-02, SUBMIT-03, SUBMIT-04]

# Metrics
duration: 3min
completed: 2026-03-03
---

# Phase 59 Plan 01: 提交與結果回饋 Summary

**批量上架提交按鈕（手機底部固定欄 + 桌面右上角）+ POST API 呼叫 + 三種結果回饋（全部成功 toast 跳轉 / 部分失敗自動移除成功 + 紅色標記 / 全部失敗保留資料）+ 桌面提示文字**

## Performance

- **Duration:** 約 3 分鐘
- **Started:** 2026-03-03T09:43:44Z
- **Completed:** 2026-03-03T09:47:16Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- useBatchCreate.js 新增完整提交邏輯：validItems/validItemCount 篩選有效商品、submitting/submitError 狀態控制、submitBatch() 三種結果處理、clearItemErrors() 重試前清除
- 手機版底部固定欄顯示有效商品數 + 藍色「批量上架」按鈕，0 件時 disabled，提交時 spinner +「上架中...」
- 桌面版右上角「批量上架 (N)」按鈕，在 CSV 按鈕旁邊
- 失敗商品手機版卡片加紅色邊框 + 錯誤原因文字，桌面版表格行加紅色背景 + colspan 錯誤原因行
- 桌面版表格下方淡灰色提示文字
- v3.2 批量上架前端 milestone 全部完成 (Phase 57 + 58 + 59)

## Task Commits

Each task was committed atomically:

1. **Task 1: useBatchCreate composable 擴充 -- 提交邏輯與結果處理** - `4491bf8` (feat)
2. **Task 2: 提交按鈕 UI + 結果回饋 + 提示文字** - `b58bfe6` (feat)

## Files Created/Modified
- `includes/views/composables/useBatchCreate.js` - 新增 submitting/submitError/validItems/validItemCount/submitBatch/clearItemErrors，useApi 改為解構 get+post
- `admin/partials/batch-create.php` - 新增 Phase 59 CSS（提交按鈕/spinner/失敗標記/提示文字）、桌面版提交按鈕、手機版底部固定欄、卡片/表格錯誤標記、桌面提示文字

## Decisions Made
- useApi() 改為解構 get + post，直接在 composable 中使用 post() 呼叫 batch-create API
- 部分失敗時 index 對應策略：API results[i].index 對應 validItems 的 index，再透過 id 對應回 items
- 桌面版錯誤行改用 template v-for 包裝（偏離 plan 原始設計），確保 Vue scope 正確
- 手機版底部固定欄用 sticky bottom-0，和數量選擇步驟保持一致

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] 桌面版表格 v-for scope 修正**
- **Found during:** Task 2（桌面版表格失敗標記）
- **Issue:** Plan 原始設計將錯誤行 `<tr v-if="item._error">` 放在 v-for 的 `</tr>` 之後但 `</tbody>` 之前，此時 `item` 已不在 Vue v-for 的 scope 中，會導致 undefined 錯誤
- **Fix:** 用 `<template v-for>` 包裝資料行和錯誤行，讓兩個 `<tr>` 都在同一個 v-for scope 中
- **Files modified:** admin/partials/batch-create.php
- **Verification:** template v-for 是 Vue 3 的標準模式，確保 item 在兩個 tr 中都可存取
- **Committed in:** b58bfe6 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** 必要修正，確保 Vue 渲染不出錯。無範圍擴大。

## Issues Encountered

None -- Phase 58 建立的表單骨架和 CSS 前綴慣例（bp-）完整，本次擴充接合順暢。

## User Setup Required

無需任何外部服務設定。

## Next Phase Readiness

v3.2 批量上架前端 milestone 全部完成：
- Phase 57: 路由與數量選擇 (ROUTE-01~02, SELECT-01~04)
- Phase 58: 批量表單 + CSV 匯入 (FORM-01~05, CSV-01~03)
- Phase 59: 提交與結果回饋 (SUBMIT-01~04)

批量上架功能可端對端運作：商品列表頁「+ 上架」-> 數量選擇 -> 表單填寫（手動/CSV）-> 提交 -> 結果回饋。

## Self-Check: PASSED

- [x] `includes/views/composables/useBatchCreate.js` -- 存在
- [x] `admin/partials/batch-create.php` -- 存在
- [x] `.planning/phases/59-submit-and-feedback/59-01-SUMMARY.md` -- 存在
- [x] Commit `4491bf8` (Task 1) -- 存在
- [x] Commit `b58bfe6` (Task 2) -- 存在

---
*Phase: 59-submit-and-feedback*
*Completed: 2026-03-03*
