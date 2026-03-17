---
phase: 11-按鈕與狀態標籤遷移-4-個頁面
plan: 05
subsystem: ui
tags: [products, button, status-tag, migration, icon-preservation]

# Dependency graph
requires:
  - phase: 11-04
    provides: Consistent migration pattern
provides:
  - products.php 使用設計系統按鈕和狀態標籤
  - 保留分配按鈕的特殊 icon 設計
affects: [11-06, products-page]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Button with icon preservation pattern"
    - "Grid/List view button adaptation"

key-files:
  created: []
  modified:
    - admin/partials/products.php

key-decisions:
  - "保留分配按鈕的 icon (🎯)"
  - "Grid View 保持原樣，只遷移 Table View 和 List View"
  - "Primary: 新增商品、分配"
  - "Secondary: 切換視圖、篩選"
  - "Danger: 刪除商品"

patterns-established:
  - "帶 icon 的按鈕可以在設計系統 class 內加入 icon 元素"
  - "不同視圖模式的按鈕可以選擇性遷移"

# Metrics
duration: 已完成
completed: 2026-01-28
---

# Phase 11 Plan 05: 遷移 products.php Summary

**遷移商品頁面的按鈕和狀態標籤到設計系統（保留特殊設計）**

## Performance

- **Duration:** 已完成（補記）
- **Completed:** 2026-01-28
- **Tasks:** 完成按鈕和狀態標籤遷移

## Accomplishments

- 遷移 Table View 和 List View 的按鈕到設計系統
- 遷移庫存狀態標籤到設計系統
- 保留分配按鈕的 🎯 icon 特殊設計
- Grid View 保持原有設計（未遷移）

## Files Created/Modified

### Modified:
- `admin/partials/products.php` - 按鈕和狀態標籤遷移（Table/List View）

## Key Features Implemented

### 按鈕遷移
- **Primary buttons**:
  - 新增商品 → `.btn .btn-primary`
  - 分配商品 → `.btn .btn-primary` + 🎯 icon
- **Secondary buttons**:
  - 切換視圖 (Table/Grid/List) → `.btn .btn-secondary`
  - 篩選 → `.btn .btn-secondary`
- **Danger buttons**:
  - 刪除商品 → `.btn .btn-danger`

### 狀態標籤遷移
- 庫存狀態使用 `.status-tag .status-tag-*`
- 顏色對應：
  - 充足 → success (綠色)
  - 不足 → warning (黃色)
  - 缺貨 → error (紅色)

### Icon 保留
- 分配按鈕內部包含 `<span>🎯</span>` icon
- icon 與設計系統 button class 相容

## Decisions Made

1. **選擇性遷移**: Grid View 保持原設計，只遷移 Table/List View
2. **Icon 整合**: 在 `.btn` 內加入 icon 元素，不影響設計系統樣式
3. **視圖切換按鈕**: 統一使用 secondary 樣式

## Deviations from Plan

- Grid View 未遷移（保持原有卡片式設計）

## Issues Encountered

無

## Next Phase Readiness

- ✅ products.php Table/List View 遷移完成
- ✅ 證明設計系統可與 icon 整合
- ✅ 下一步：遷移 settings.php (Plan 06)

---
*Phase: 11-按鈕與狀態標籤遷移-4-個頁面*
*Completed: 2026-01-28*
