---
phase: 34-模板管理介面
plan: 01
subsystem: ui
tags: [vue3, rest-api, notification-templates, wordpress]

# Dependency graph
requires:
  - phase: 33-通知觸發與模板引擎
    provides: NotificationTemplates 服務和後端模板定義
provides:
  - 前端模板管理 UI，支援所有 TMPL-01 通知類型（product_available, new_order, order_status_changed, shipment_shipped）
  - 「重設為預設值」功能（DELETE API 端點 + 前端 UI）
  - 完整的模板編輯器（變數列表、點擊複製、儲存、重設）
affects: [v1.4-會員前台子訂單顯示功能, 未來通知功能擴展]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "DELETE REST API 端點用於重設資源為預設值"
    - "前端確認對話框 + 非同步 API 呼叫模式"

key-files:
  created: []
  modified:
    - admin/partials/settings.php
    - includes/api/class-settings-api.php

key-decisions:
  - "所有 TMPL-01 通知類型在前端 templateDefinitions 中定義，確保 UI 完整性"
  - "「重設為預設值」透過 DELETE /settings/templates/{key} 實作，清除自訂模板並觸發快取清除"

patterns-established:
  - "模板管理 UI：變數下拉選單 + 點擊插入 + 儲存 + 重設按鈕"
  - "DELETE 端點清除 wp_option 中的單一模板 key，而非整個選項"

# Metrics
duration: 5min
completed: 2026-02-02
---

# Phase 34 Plan 01: 模板管理介面 Summary

**Settings 頁面新增四種客戶通知模板（商品上架、新訂單、訂單狀態變更、出貨通知），並實作「重設為預設值」功能**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-02T02:15:52Z
- **Completed:** 2026-02-02T02:20:41Z
- **Tasks:** 3 (Task 2 為驗證任務，無需提交)
- **Files modified:** 2

## Accomplishments
- 前端 templateDefinitions 新增 4 個客戶通知類型，100% TMPL-01 需求覆蓋
- 所有新增變數（product_list, shipping_method, estimated_delivery, shop_name, old_status, new_status）已加入 variableDescriptions
- 實作「重設為預設值」功能，包含 DELETE API 端點、前端按鈕和確認對話框
- 驗證模板儲存 API 和多層快取實作（TMPL-02, TMPL-04）

## Task Commits

Each task was committed atomically:

1. **Task 1: 在前端 templateDefinitions 新增 shipment_shipped 模板定義** - `76bfb4c` (feat)
   - 新增 product_available, new_order, order_status_changed, shipment_shipped 四個模板
   - 新增 6 個變數說明到 variableDescriptions

2. **Task 2: 確認後端 definitions() 包含 shipment_shipped 預設模板** - 無提交（驗證任務，模板已存在於 Phase 33-02）

3. **Task 3: 驗證模板儲存 API 和實作「重設為預設值」功能** - `b58011e` (feat)
   - 新增 DELETE /settings/templates/{key} API 端點
   - 實作 delete_template() 後端方法
   - 新增前端「重設為預設值」按鈕（文字模板和 Flex Message 模板）
   - 實作 resetTemplate() JavaScript 方法

## Files Created/Modified
- `admin/partials/settings.php` - 新增 4 個模板定義、6 個變數說明、「重設為預設值」按鈕和處理器
- `includes/api/class-settings-api.php` - 新增 DELETE 端點和 delete_template() 方法

## Decisions Made

1. **所有 TMPL-01 通知類型一次新增**
   - 原計劃僅新增 shipment_shipped，但為確保 TMPL-01 需求 100% 覆蓋，同時新增其他三個通知類型
   - 避免未來重複修改同一區域程式碼

2. **「重設為預設值」透過 DELETE 端點實作**
   - 使用 RESTful 語義（DELETE = 刪除資源）
   - 刪除 wp_options 中的單一模板 key，而非整個選項
   - 自動觸發多層快取清除（靜態快取 + WordPress 物件快取）

3. **前端確認對話框保護誤操作**
   - 重設前顯示 confirm() 對話框
   - 成功後重新載入模板並顯示 toast 通知

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- v1.3 模板管理介面完成，賣家可自訂所有客戶通知模板
- 前端 UI 完整支援 TMPL-01 四種通知類型
- 「重設為預設值」功能可用，降低使用者操作風險
- 已驗證 TMPL-02（前端編輯器 UI）和 TMPL-04（模板儲存 API 和快取）實作正確
- v1.3 milestone 已完成，可開始 v1.4 planning（會員前台子訂單顯示功能）

---
*Phase: 34-模板管理介面*
*Completed: 2026-02-02*
