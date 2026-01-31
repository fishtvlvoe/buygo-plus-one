---
phase: 10-表格與卡片遷移-4-個頁面
plan: 01
subsystem: ui
tags: [design-system, css, table, card, responsive]

# Dependency graph
requires:
  - phase: 08-Header-區域遷移
    provides: Design system components and responsive isolation pattern
provides:
  - shipment-products.php migrated to design system table and card classes
  - Established pattern for migrating data tables to .data-table
  - Established pattern for migrating mobile cards to .card-list/.card
affects: [10-02, 10-03, 10-04, ui, design-system]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Table migration pattern: .data-table replaces hardcoded Tailwind classes"
    - "Card migration pattern: .card-list + .card with semantic title/subtitle elements"
    - "Preserve Vue directives and dynamic bindings during migration"

key-files:
  created: []
  modified:
    - buygo-plus-one-dev/admin/partials/shipment-products.php

key-decisions:
  - "Use semantic HTML (h3.card-title, p.card-subtitle) instead of div elements for card structure"
  - "Preserve all Vue directives (v-for, :key, v-if, @click) unchanged"
  - "Keep alignment classes (text-center) while removing style classes"

patterns-established:
  - "Table migration: Remove wrapper divs, use .data-table on outer container"
  - "Remove all Tailwind style classes from table/thead/tbody/tr elements"
  - "Keep functional classes (text-center, w-12) but remove style classes from th/td"
  - "Card migration: Replace .md:hidden with .card-list, individual cards use .card class"
  - "Replace title/subtitle divs with h3.card-title and p.card-subtitle semantic elements"

# Metrics
duration: 1.5min
completed: 2026-01-28
---

# Phase 10 Plan 01: shipment-products.php 表格與卡片遷移 Summary

**備貨頁面桌面版表格和手機版卡片完全遷移至設計系統,使用 .data-table 和 .card-list/.card 語義化 classes**

## Performance

- **Duration:** 1.5 min (90 seconds)
- **Started:** 2026-01-28T11:14:58Z
- **Completed:** 2026-01-28T11:16:28Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- 桌面版表格使用 .data-table class,移除所有 Tailwind hardcoded classes
- 手機版卡片使用 .card-list 和 .card classes,語義化標題/副標題元素
- 保留所有 Vue 功能(商品展開/收起、狀態標籤、勾選框)
- 響應式隔離正確(桌面顯示表格,手機顯示卡片)

## Task Commits

Each task was committed atomically:

1. **Tasks 1-2: 遷移桌面版表格和手機版卡片** - `f35103e` (refactor)

**Plan metadata:** (pending after STATE.md update)

## Files Created/Modified
- `buygo-plus-one-dev/admin/partials/shipment-products.php` - 遷移表格和卡片至設計系統 classes

## Decisions Made

**1. 使用語義化 HTML 元素替代 div**
- 卡片標題從 `<div class="text-sm font-bold...">` 改為 `<h3 class="card-title">`
- 卡片副標題從 `<div class="text-xs text-slate-500">` 改為 `<p class="card-subtitle">`
- 理由: 提升可訪問性和語義化,設計系統已定義這些元素的樣式

**2. 保留對齊 classes,移除樣式 classes**
- 保留: `text-center`, `w-12` (功能性 class)
- 移除: `px-4 py-3`, `text-sm`, `font-semibold` 等(樣式 class,由設計系統處理)
- 理由: 功能性 classes 影響佈局邏輯,樣式 classes 應由組件統一定義

**3. 移除表格包裝層**
- 原本: `<div class="hidden md:block ..."><div class="overflow-x-auto"><table>...`
- 現在: `<div class="data-table"><table>...`
- 理由: 設計系統 .data-table 已處理響應式隱藏和溢出捲動

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - 遷移過程順利,所有 Vue directives 和功能正常保留。

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for next plan (10-02):**
- 表格和卡片遷移模式已建立並驗證
- shipment-products.php 遷移成功,可作為其他頁面參考
- 設計系統 components (table.css, card.css) 運作正常

**Pattern for 10-02, 10-03, 10-04:**
1. 桌面版表格: 外層用 `.data-table`,移除 table/thead/tbody/tr 的樣式 classes
2. th/td 僅保留對齊 classes (`text-center`, `text-right`)
3. 手機版卡片: 外層用 `.card-list`,單張卡片用 `.card`
4. 標題用 `h3.card-title`,副標題用 `p.card-subtitle`
5. 保留所有 Vue directives 和動態綁定

**No blockers:** 可立即開始下一個頁面遷移。

---
*Phase: 10-表格與卡片遷移-4-個頁面*
*Completed: 2026-01-28*
