---
phase: 34-模板管理介面
plan: 02
subsystem: ui
tags: [vue3, shipment, date-picker, estimated-delivery, api]

# Dependency graph
requires:
  - phase: 33-通知觸發與模板引擎
    provides: 出貨通知基礎架構和模板系統
provides:
  - 出貨單預計送達時間欄位（前端輸入、API 傳遞、資料庫儲存）
  - 標記出貨 Modal 含日期選擇器
  - estimated_delivery_at 完整資料流（UI → API → Service → Database）
affects: [33-通知觸發與模板引擎, notification-templates, shipment-management]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "HTML5 date input with min attribute for user-friendly date selection"
    - "Modal-based form input for mark-as-shipped flow"
    - "Date format conversion between UI (YYYY-MM-DD) and Database (YYYY-MM-DD HH:MM:SS)"

key-files:
  created: []
  modified:
    - admin/partials/shipment-details.php
    - admin/js/components/ShipmentDetailsPage.js
    - includes/api/class-shipments-api.php
    - includes/services/class-shipment-service.php

key-decisions:
  - "Use HTML5 date input for native date picker support"
  - "Convert date format in JavaScript before sending to API (YYYY-MM-DD → YYYY-MM-DD 00:00:00)"
  - "Make estimated_delivery_at optional field in API and Service layer"
  - "Use modal for mark-as-shipped instead of simple confirm dialog"

patterns-established:
  - "Date field handling: formatDateForInput() for display, getTodayDate() for min attribute"
  - "Optional parameter pattern in API: sanitize, validate, pass to Service"
  - "Modal state management: show/close/confirm pattern"

# Metrics
duration: 3min
completed: 2026-02-02
---

# Phase 34 Plan 02: 模板管理介面 Summary

**出貨單預計送達時間欄位完整實作：HTML5 date picker、Vue 狀態管理、API 驗證、資料庫儲存**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-02T02:15:51Z
- **Completed:** 2026-02-02T02:19:47Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- 出貨單詳情頁顯示預計送達時間（已出貨狀態）
- 標記出貨 Modal 新增日期選擇器（選填欄位）
- API 層接收和驗證 estimated_delivery_at 參數
- ShipmentService 支援 estimated_delivery_at 資料庫更新
- 完整的日期格式轉換流程（UI ↔ API ↔ Database）

## Task Commits

Each task was committed atomically:

1. **Task 1: 在出貨單詳情 Modal 新增預計送達時間欄位** - `0f6850e` (feat)
   - 新增標記出貨 Modal（markShippedModal）
   - 加入 HTML5 date input 欄位（min 設為今天）
   - 在已出貨狀態的出貨資訊區塊顯示預計送達時間

2. **Task 2: 在 Vue 組件中處理 estimated_delivery_date 狀態和 API 呼叫** - `a1ba314` (feat)
   - 新增 markShippedModal 狀態管理
   - 實作 getTodayDate() 和 formatDateForInput() 輔助方法
   - 修改 showMarkShippedConfirm 使用 Modal 而非簡單確認對話框
   - 新增 closeMarkShippedModal 和 confirmMarkShipped 方法
   - 在 API 請求中加入 estimated_delivery_at 參數
   - 更新 loadShipmentDetail 轉換日期格式供顯示

3. **Task 3: 在 Shipments API 支援 estimated_delivery_at 參數** - `ec73619` (feat)
   - API 層接收和清理 estimated_delivery_at 參數
   - 使用 strtotime() 驗證日期格式
   - ShipmentService::mark_shipped() 新增 estimated_delivery_at 參數
   - 資料庫更新邏輯支援可選的 estimated_delivery_at 欄位

## Files Created/Modified
- `admin/partials/shipment-details.php` - 新增標記出貨 Modal 和預計送達時間顯示欄位
- `admin/js/components/ShipmentDetailsPage.js` - 狀態管理、日期處理方法、Modal 控制邏輯
- `includes/api/class-shipments-api.php` - batch_mark_shipped 接收和驗證 estimated_delivery_at
- `includes/services/class-shipment-service.php` - mark_shipped 支援 estimated_delivery_at 參數和資料庫更新

## Decisions Made
- **使用 HTML5 date input**: 提供原生日期選擇器，無需額外 JavaScript 函式庫
- **日期格式轉換策略**: 前端使用 YYYY-MM-DD，傳送至 API 時轉換為 MySQL DATETIME 格式（YYYY-MM-DD 00:00:00）
- **可選欄位設計**: estimated_delivery_at 為選填，未填寫不影響出貨流程
- **Modal 取代 confirm**: 改善 UX，提供更完整的出貨確認介面，未來可擴充其他欄位（如物流方式、追蹤號碼）

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - implementation proceeded smoothly. All verification checks passed:
- ✓ HTML 模板包含 estimated_delivery 欄位
- ✓ Vue 組件處理邏輯完整
- ✓ API 參數驗證正確
- ✓ Service 層支援資料庫更新
- ✓ 所有 PHP 檔案通過語法檢查

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for Phase 33 integration:**
- 預計送達時間欄位已完整實作並儲存到資料庫
- 可在出貨通知模板中使用 `{estimated_delivery_at}` 變數
- 資料流程完整：UI 輸入 → API 驗證 → 資料庫儲存 → 通知模板顯示

**Blockers/Concerns:**
- None - 功能完整且獨立，不依賴其他未完成功能

**Technical notes:**
- estimated_delivery_at 欄位必須已存在於 buygo_shipments 資料表（應在 Phase 33 資料庫遷移中建立）
- 日期格式為 MySQL DATETIME（YYYY-MM-DD HH:MM:SS），時間部分固定為 00:00:00
- 前端顯示時使用 formatDate() 轉換為 YYYY/MM/DD 格式

---
*Phase: 34-模板管理介面*
*Completed: 2026-02-02*
