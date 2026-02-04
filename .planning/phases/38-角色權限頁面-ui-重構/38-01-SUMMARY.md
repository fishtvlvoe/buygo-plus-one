---
phase: 38-角色權限頁面-ui-重構
plan: 01
subsystem: ui
tags: [wordpress, admin-ui, user-roles, buygo-helpers]

# Dependency graph
requires:
  - phase: database
    provides: buygo_helpers 表結構（id, helper_id, seller_id）
provides:
  - 角色權限頁面顯示 WordPress User ID
  - 角色權限頁面顯示 BuyGo ID（小幫手）
  - 優化的 BuyGo ID 查詢邏輯
affects: [38-02, 38-03]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - 兩行顯示格式（主要文字 + 灰色小字 ID）
    - 條件顯示 BuyGo ID（小幫手有 ID，賣家無 ID）

key-files:
  created: []
  modified:
    - includes/admin/class-settings-page.php

key-decisions:
  - "使用 wp_buygo_helpers.id 作為 BuyGo ID"
  - "賣家顯示「（無 BuyGo ID）」而非空白"
  - "優化 SQL 查詢，一次取得 BuyGo ID 和賣家資訊"

patterns-established:
  - "ID 顯示格式：主要資訊換行後加上 <small style='color: #666;'> 顯示 ID"
  - "條件顯示邏輯：根據 buygo_id 是否存在決定顯示內容"

# Metrics
duration: 1.5min
completed: 2026-02-04
---

# Phase 38 Plan 01: UI 欄位顯示改造（WP ID + BuyGo ID） Summary

**角色權限頁面顯示 WordPress User ID（WP-X）和 BuyGo ID（BuyGo-X），使用兩行灰色小字格式**

## Performance

- **Duration:** 1.5 分鐘
- **Started:** 2026-02-04T14:46:13Z
- **Completed:** 2026-02-04T14:47:49Z
- **Tasks:** 3（合併為單一 commit）
- **Files modified:** 1

## Accomplishments
- 使用者欄位顯示 WordPress User ID（格式：WP-{數字}）
- 角色欄位顯示 BuyGo ID（小幫手：BuyGo-{數字}，賣家：無 BuyGo ID）
- 優化資料庫查詢，一次取得 buygo_id 和賣家資訊
- 統一 ID 顯示格式（兩行，主要文字 + 灰色小字）

## Task Commits

所有三個任務合併為單一 commit（因為修改同一個檔案的相鄰區域）：

1. **合併任務：修改使用者欄位 + 查詢 BuyGo ID + 修改角色欄位** - `47256d9` (feat)

## Files Created/Modified
- `includes/admin/class-settings-page.php` - 修改 render_roles_tab() 方法的使用者和角色欄位顯示邏輯

## Decisions Made

**1. 使用 wp_buygo_helpers.id 作為 BuyGo ID**
- **Rationale:** 這是 helpers 表的主鍵（auto_increment），穩定且唯一
- **Alternative considered:** 使用 helper_id（WordPress User ID），但這與 WP ID 重複

**2. 賣家顯示「（無 BuyGo ID）」而非空白或隱藏**
- **Rationale:** 明確告知使用者這是預期行為（賣家不在 helpers 表中）
- **Impact:** 提升 UI 清晰度，避免使用者困惑

**3. 優化 SQL 查詢結構**
- **What:** 將原本的 `SELECT s.ID, s.display_name` 改為 `SELECT h.id as buygo_id, s.ID as seller_wp_id, s.display_name as seller_name`
- **Rationale:** 一次查詢取得所有需要的資料，避免額外查詢
- **Impact:** 提升效能，減少資料庫查詢次數

## Deviations from Plan

None - 計畫執行完全符合規範。

## Issues Encountered

None - 所有任務順利完成，無遇到技術問題。

## User Setup Required

None - 此為純 UI 變更，無需使用者設定。

## Next Phase Readiness

✅ **Plan 38-02 已準備就緒**
- UI 欄位顯示 ID 對應完成
- 可以進行欄位簡化（移除冗餘欄位）

✅ **Plan 38-03 已準備就緒**
- 顯示邏輯已優化
- 可以進行統一編輯模式實作

**無 blockers** - 所有基礎 ID 顯示功能已實作完成

---
*Phase: 38-角色權限頁面-ui-重構*
*Completed: 2026-02-04*
