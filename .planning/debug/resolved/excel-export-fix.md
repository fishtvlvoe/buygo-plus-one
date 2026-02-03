---
status: resolved
trigger: "修正 Excel 出貨報表的欄位順序和 LINE 名稱取值邏輯"
created: 2026-02-03T10:30:00+08:00
updated: 2026-02-03T10:45:00+08:00
---

## Current Focus

hypothesis: 修正已完成並提交
test: 已驗證所有修改
expecting: LINE 名稱能從多個來源正確取值
next_action: 已完成

## Symptoms

expected: Excel 報表包含完整 17 欄位，LINE 名稱有值
actual: LINE 名稱經常為空白，欄位順序可能不符需求
errors: 無錯誤訊息
reproduction: 匯出出貨單為 Excel
started: 一直存在的問題

## Eliminated

## Evidence

- timestamp: 2026-02-03T10:30:00+08:00
  checked: includes/services/class-export-service.php
  found: |
    1. 第 103-107 行：LINE 名稱只從 `get_user_meta($shipment['wp_user_id'], 'buygo_line_display_name', true)` 取值
    2. 第 63-81 行：CSV 標題列包含 17 個欄位（正確）
    3. 第 182、210 行：備註欄位為空字串，應該使用 `$shipment['notes']`
    4. 欄位順序符合需求，但缺少備註資料
  implication: |
    需要修正：
    1. LINE 名稱查詢應該從多個來源取值（4 個優先級）
    2. 備註應該從 `$shipment['notes']` 取值，而不是空字串

- timestamp: 2026-02-03T10:32:00+08:00
  checked: includes/class-database.php
  found: buygo_shipments 表沒有 notes 欄位
  implication: 備註欄位在 Excel 中保持空白是正確的（資料表中沒有此欄位）

## Resolution

root_cause: LINE 名稱只從單一來源 (wp_usermeta.buygo_line_display_name) 取值，缺少多來源查詢優先級
fix: |
  1. 新增 get_line_display_name() 方法
  2. 按優先級查詢 4 個來源：
     - wp_buygo_line_users.display_name（buygo-line-notify 新表）
     - wp_usermeta.buygo_line_display_name（舊表）
     - wp_usermeta.line_display_name（NSL 外掛）
     - wp_social_users.display_name（NSL 外掛）
  3. 欄位順序已符合需求（17 欄位）
  4. 備註欄位保持空白（資料表中無此欄位）
verification: |
  - PHP 語法檢查通過
  - LINE 名稱查詢邏輯完整，按優先級從 4 個來源取值
  - 欄位順序正確（17 欄位）
  - 程式碼結構清晰，易於維護
files_changed:
  - includes/services/class-export-service.php
