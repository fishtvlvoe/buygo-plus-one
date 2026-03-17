---
phase: 11-按鈕與狀態標籤遷移-4-個頁面
plan: 04
subsystem: ui
tags: [orders, button, status-tag, migration]

# Dependency graph
requires:
  - phase: 11-03
    provides: Consistent migration pattern
provides:
  - orders.php 使用設計系統按鈕和狀態標籤
affects: [11-05, 11-06, orders-page]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Orders page button hierarchy"
    - "Status badge integration"

key-files:
  created: []
  modified:
    - admin/partials/orders.php

key-decisions:
  - "Primary: 新增訂單、匯出"
  - "Secondary: 篩選、取消"
  - "訂單狀態使用語義化標籤"

patterns-established:
  - "列表頁按鈕模式：Primary 新增 + 篩選/匯出功能"

# Metrics
duration: 已完成
completed: 2026-01-28
---

# Phase 11 Plan 04: 遷移 orders.php Summary

**遷移訂單頁面的按鈕和狀態標籤到設計系統**

## Performance

- **Duration:** 已完成（補記）
- **Completed:** 2026-01-28
- **Tasks:** 完成按鈕和狀態標籤遷移

## Accomplishments

- 遷移訂單頁面所有按鈕到設計系統
- 遷移訂單狀態標籤到設計系統
- 保持複雜的篩選和操作功能

## Files Created/Modified

### Modified:
- `admin/partials/orders.php` - 按鈕和狀態標籤遷移

## Key Features Implemented

### 按鈕遷移
- **Primary buttons**: 新增訂單、匯出 → `.btn .btn-primary`
- **Secondary buttons**: 篩選、取消 → `.btn .btn-secondary`
- **Danger buttons**: 取消訂單、刪除 → `.btn .btn-danger`

### 狀態標籤遷移
- 訂單狀態（待付款、已付款、已出貨、已完成、已取消）
- 使用語義化 `.status-tag .status-tag-*` classes
- 顏色對應狀態：
  - pending (待付款) → warning
  - paid (已付款) → info
  - shipped (已出貨) → info
  - completed (已完成) → success
  - cancelled (已取消) → error

## Decisions Made

1. **按鈕層級**: 根據訂單管理的操作重要性分配
2. **狀態顏色映射**: 建立訂單狀態到視覺樣式的對應

## Deviations from Plan

無

## Issues Encountered

無

## Next Phase Readiness

- ✅ orders.php 遷移完成
- ✅ 下一步：遷移 products.php (Plan 05)

---
*Phase: 11-按鈕與狀態標籤遷移-4-個頁面*
*Completed: 2026-01-28*
