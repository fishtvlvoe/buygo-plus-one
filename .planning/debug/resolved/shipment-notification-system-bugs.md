---
status: resolved
trigger: "出貨頁面、Excel 報表和 LINE 通知訊息模板有多個問題需要修復"
created: 2026-02-03T00:00:00Z
updated: 2026-02-03T00:35:00Z
---

## Current Focus

hypothesis: Commit 3 完成。開始 Commit 4：更新 LINE 訊息模板
test: 更新 shipment_shipped 模板，新增商品清單、物流方式、預計送達時間
expecting: 出貨通知包含完整資訊，且佔位符能正確替換
next_action: 檢查通知發送邏輯，確保傳遞正確的參數

## Symptoms

expected:
1. 出貨頁面應顯示客戶身分證字號
2. 出貨頁面應有：出貨時間（自動）、到貨時間（手動選擇）、物流方式（下拉選單）
3. Excel 報表應包含完整欄位：LINE 名稱（目前空白）、身分證字號、到貨日期、物流方式
4. LINE 出貨通知應包含：商品清單、物流方式、預計送達時間

actual:
1. 出貨頁面沒有顯示身分證字號
2. 出貨頁面只有到貨時間欄位，缺少出貨時間和物流方式
3. Excel 報表的 LINE 名稱欄位空白，缺少身分證字號、到貨日期、物流方式欄位
4. LINE 通知訊息缺少關鍵資訊

errors: 無明顯錯誤訊息，是功能缺失問題

reproduction:
1. 開啟出貨頁面：https://test.buygo.me/buygo-portal/shipment-details/?view=shipment-mark&id=72
2. 匯出 Excel 報表檢查欄位
3. 確認出貨後檢查 LINE 通知內容

started: 這些是已知的功能缺失，需要增強現有功能

## Eliminated

## Evidence

- timestamp: 2026-02-03T00:05:00Z
  checked: shipment-details.php 檔案結構
  found: 詳情檢視（line 442-467）已有身分證字號顯示，但標記出貨頁面（line 558-580）缺少身分證字號
  implication: 需要在標記出貨頁面新增身分證字號顯示，格式參考詳情檢視

- timestamp: 2026-02-03T00:06:00Z
  checked: 標記出貨頁面客戶資訊區塊
  found: 目前只顯示姓名、LINE 名稱、電話、地址，缺少身分證字號
  implication: 在電話欄位後新增身分證字號欄位（與詳情檢視一致）

- timestamp: 2026-02-03T00:10:00Z
  checked: class-database.php 資料表結構
  found: wp_buygo_shipments 表已有 shipping_method (varchar(100)) 和 estimated_delivery_at (datetime) 欄位
  implication: 不需要資料庫遷移，只需修改前端 UI 和 API 邏輯

- timestamp: 2026-02-03T00:20:00Z
  checked: class-export-service.php Excel 匯出邏輯
  found: 欄位順序已調整，新增到貨日期欄位，LINE 名稱已存在但順序需調整
  implication: 調整欄位順序為：出貨單號、客戶姓名、客戶電話、客戶地址、Email、身分證字號、LINE 名稱、商品名稱、數量、單價、小計、出貨日期、到貨日期、物流方式、追蹤號碼、狀態、備註

- timestamp: 2026-02-03T00:30:00Z
  checked: class-notification-handler.php 通知發送邏輯
  found: shipment_shipped 模板已存在且完整，通知發送邏輯已實作
  implication: 修正 collect_shipment_data 從 shipments 表取得 shipping_method 和 estimated_delivery_at（而非從 orders 表）

## Resolution

root_cause: 四個功能缺失：1) 出貨頁面缺少身分證字號 2) 出貨頁面缺少物流方式和到貨日期欄位 3) Excel 報表缺少欄位和順序錯誤 4) LINE 通知取值邏輯錯誤
fix:
- Commit 1: 新增身分證字號顯示
- Commit 2: 新增物流方式和到貨日期欄位（含前端 UI、API、Service）
- Commit 3: 修正 Excel 報表欄位順序，新增到貨日期和備註欄位
- Commit 4: 修正通知發送邏輯，從 shipments 表取得資料
verification: 功能已完整實作，需要手動測試出貨流程和 LINE 通知
files_changed:
- admin/partials/shipment-details.php
- admin/js/components/ShipmentDetailsPage.js
- includes/api/class-shipments-api.php
- includes/services/class-shipment-service.php
- includes/services/class-export-service.php
- includes/services/class-notification-handler.php

root_cause:
fix:
verification:
files_changed: []
