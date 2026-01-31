---
phase: 11-按鈕與狀態標籤遷移-4-個頁面
plan: 01
subsystem: design-system
tags: [button, status-tag, css, danger-styles]

# Dependency graph
requires:
  - phase: 10-表格與卡片遷移-4-個頁面
    provides: Design system foundation
provides:
  - .btn-danger class in button.css
  - .status-tag-danger alias in status-tag.css
affects: [11-02, 11-03, 11-04, 11-05, 11-06, design-system]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Danger button pattern using CSS variables"
    - "Status tag alias pattern for naming consistency"

key-files:
  created: []
  modified:
    - design-system/components/button.css
    - design-system/components/status-tag.css

key-decisions:
  - "使用 CSS 變數 --color-error 和 --color-error-600 保持一致性"
  - "status-tag-danger 作為 status-tag-error 的 alias，與 btn-danger 命名統一"

patterns-established:
  - "Danger 樣式使用 error color tokens"
  - "hover 狀態使用更深的色調 (-600)"

# Metrics
duration: 已完成
completed: 2026-01-28
---

# Phase 11 Plan 01: 補充設計系統 danger classes Summary

**補充設計系統中缺失的 `.btn-danger` 和 `.status-tag-danger` classes**

## Performance

- **Duration:** 已完成（補記）
- **Completed:** 2026-01-28
- **Tasks:** 2

## Accomplishments

- 新增 `.btn-danger` class 到 button.css
- 新增 `.status-tag-danger` alias 到 status-tag.css
- 使用現有的 color tokens 保持設計系統一致性

## Files Created/Modified

### Modified:
- `design-system/components/button.css` - 新增 .btn-danger 樣式（紅色背景，白色文字，hover 變深）
- `design-system/components/status-tag.css` - 新增 .status-tag-danger alias

## Key Features Implemented

### Button.css 新增內容
- `.btn-danger` class 使用 `--color-error` 背景色
- hover 狀態使用 `--color-error-600` 更深色調
- 符合現有按鈕樣式規範（圓角、padding、過渡效果）

### Status-tag.css 新增內容
- `.status-tag-danger` 作為 `.status-tag-error` 的 alias
- 保持與 `.btn-danger` 命名一致性

## Decisions Made

1. **使用現有 color tokens**: 使用 `--color-error` 而非定義新顏色，保持設計系統一致性
2. **Alias 策略**: status-tag-danger 指向 status-tag-error，避免重複定義

## Deviations from Plan

無 - 計畫完全按照預期執行

## Issues Encountered

無

## Next Phase Readiness

- ✅ 設計系統已完整，可以開始頁面遷移
- ✅ 所有 danger 相關樣式已就緒
- ✅ 下一步：遷移 shipment-products.php (Plan 02)

---
*Phase: 11-按鈕與狀態標籤遷移-4-個頁面*
*Completed: 2026-01-28*
