---
phase: 32-database-foundation
plan: 01
subsystem: database
tags: [mysql, database-migration, shipment, datetime]

# Dependency graph
requires:
  - phase: 31-order-notifications
    provides: 訂單通知系統（需要出貨通知功能的資料基礎）
provides:
  - buygo_shipments 資料表包含 estimated_delivery_at 欄位
  - 資料庫版本 1.3.0 升級機制
  - 從舊版本自動升級的欄位新增邏輯
affects: [32-02-api, 32-03-ui, 33-notification-triggers]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "資料庫欄位升級使用 in_array 檢查實現 idempotent"
    - "新增/升級雙路徑：create_tables() 新安裝 + upgrade_tables() 升級"

key-files:
  created: []
  modified:
    - includes/class-plugin.php
    - includes/class-database.php

key-decisions:
  - "使用 DATETIME NULL 無預設值（符合 MySQL 8.0 最佳實踐）"
  - "同時更新 create_shipments_table() 和 upgrade_shipments_table() 確保結構一致"
  - "升級邏輯使用 in_array 檢查，確保可重複執行（idempotent）"

patterns-established:
  - "資料庫欄位新增模式：CREATE TABLE + ALTER TABLE 雙重定義"
  - "版本號升級模式：DB_VERSION 常數 → maybe_upgrade_database() → upgrade_tables()"

# Metrics
duration: 1min
completed: 2026-02-02
---

# Phase 32 Plan 01: Database Foundation Summary

**buygo_shipments 資料表新增 estimated_delivery_at 欄位，支援 DATETIME NULL，自動升級機制完整**

## Performance

- **Duration:** 1 分鐘
- **Started:** 2026-02-01T20:18:15Z
- **Completed:** 2026-02-01T20:19:11Z
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments
- 資料庫版本號從 1.2.0 升級至 1.3.0
- buygo_shipments 資料表新增 estimated_delivery_at 欄位（DATETIME NULL）
- 升級邏輯確保從舊版本自動新增欄位（idempotent）
- 新舊安裝資料表結構完全一致

## Task Commits

每個任務都已原子性提交：

1. **Task 1: 更新 DB_VERSION 版本號** - `d30c784` (chore)
2. **Task 2-3: 新增 estimated_delivery_at 欄位支援** - `8f3237a` (feat)

## Files Created/Modified
- `includes/class-plugin.php` - DB_VERSION 常數更新為 1.3.0
- `includes/class-database.php` - 新增欄位定義（3 處：create CREATE TABLE, upgrade CREATE TABLE, upgrade ALTER TABLE）

## Decisions Made

1. **使用 DATETIME NULL 無預設值**
   - 理由：符合 MySQL 8.0 最佳實踐，避免預設值相容性問題
   - 影響：賣家可選擇不設定預計送達時間（NULL 值）

2. **同時更新兩個方法確保結構一致**
   - create_shipments_table()：新安裝時建立完整結構
   - upgrade_shipments_table()：升級安裝時補齊缺失欄位
   - 理由：避免新舊安裝資料表結構不一致

3. **升級邏輯使用 in_array 檢查**
   - 理由：確保可重複執行（idempotent），避免重複執行 ALTER TABLE 錯誤
   - 實作：`if (!in_array('estimated_delivery_at', $columns))`

## Deviations from Plan

無偏差 - 計畫完全按照撰寫內容執行。

## Issues Encountered

無問題 - 所有 PHP 語法檢查通過，欄位定義確認完成。

## User Setup Required

無 - 無需外部服務配置。

## Next Phase Readiness

已準備好進入下一階段：
- ✅ 資料庫欄位已就緒
- ✅ 升級機制已驗證
- ✅ 新舊安裝結構一致

下一階段（32-02）可以安全地實作 API 層，使用 `estimated_delivery_at` 欄位。

無阻礙或疑慮。

---
*Phase: 32-database-foundation*
*Completed: 2026-02-02*
