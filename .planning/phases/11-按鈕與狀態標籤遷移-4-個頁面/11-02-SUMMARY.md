---
phase: 11-按鈕與狀態標籤遷移-4-個頁面
plan: 02
subsystem: ui
tags: [shipment-products, button, status-tag, migration]

# Dependency graph
requires:
  - phase: 11-01
    provides: .btn-danger and .status-tag-danger classes
provides:
  - shipment-products.php 使用設計系統按鈕和狀態標籤
affects: [11-03, 11-04, 11-05, 11-06, shipment-products-page]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Button migration: legacy buygo-btn-* → .btn .btn-*"
    - "Status tag migration: inline Tailwind → .status-tag .status-tag-*"

key-files:
  created: []
  modified:
    - admin/partials/shipment-products.php

key-decisions:
  - "保留所有 Vue directives (@click, v-if, :class)"
  - "Primary buttons: 新增商品、新增出貨單"
  - "Secondary buttons: 取消、返回"
  - "Danger buttons: 刪除操作"

patterns-established:
  - "按鈕類型對應操作類型：primary (確認)、secondary (取消)、danger (刪除)"
  - "狀態標籤使用語義化 class：success/warning/error/info/pending"

# Metrics
duration: 已完成
completed: 2026-01-28
---

# Phase 11 Plan 02: 遷移 shipment-products.php Summary

**遷移備貨頁面的按鈕和狀態標籤到設計系統**

## Performance

- **Duration:** 已完成（補記）
- **Completed:** 2026-01-28
- **Tasks:** 完成所有按鈕和狀態標籤遷移

## Accomplishments

- 遷移所有按鈕到 `.btn .btn-*` classes
- 遷移所有狀態標籤到 `.status-tag .status-tag-*` classes
- 移除 legacy `buygo-btn-*` classes
- 保留所有 Vue 功能（directives、bindings）

## Files Created/Modified

### Modified:
- `admin/partials/shipment-products.php` - 完整遷移按鈕和狀態標籤

## Key Features Implemented

### 按鈕遷移
- **Primary buttons**: 新增商品、新增出貨單 → `.btn .btn-primary`
- **Secondary buttons**: 取消、返回 → `.btn .btn-secondary`
- **Danger buttons**: 刪除商品 → `.btn .btn-danger`

### 狀態標籤遷移
- 使用 `.status-tag` + 語義化 modifier：
  - `.status-tag-success` - 已完成
  - `.status-tag-warning` - 進行中
  - `.status-tag-error` - 錯誤
  - `.status-tag-info` - 資訊
  - `.status-tag-pending` - 待處理

### Vue 相容性
- 保留所有 `@click` 事件綁定
- 保留所有 `v-if` 條件渲染
- 保留所有 `:class` 動態 class 綁定

## Decisions Made

1. **按鈕層級**: 根據操作重要性和風險分配按鈕類型
2. **移除 legacy classes**: 完全移除 `buygo-btn-*`，改用設計系統
3. **Vue 指令**: 完全保留，確保功能不受影響

## Deviations from Plan

無 - 按照計畫完整執行

## Issues Encountered

無

## Next Phase Readiness

- ✅ shipment-products.php 遷移完成
- ✅ 遷移模式已建立
- ✅ 下一步：遷移 shipment-details.php (Plan 03)

---
*Phase: 11-按鈕與狀態標籤遷移-4-個頁面*
*Completed: 2026-01-28*
