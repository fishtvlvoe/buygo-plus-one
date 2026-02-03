---
status: investigating
trigger: "出貨頁面、Excel 報表和 LINE 通知訊息模板有多個問題需要修復"
created: 2026-02-03T00:00:00Z
updated: 2026-02-03T00:00:00Z
---

## Current Focus

hypothesis: 功能增強需求已分析完成。開始 Commit 1：出貨頁面新增身分證字號顯示
test: 檢查標記出貨頁面的客戶資訊區塊，新增身分證字號顯示
expecting: 身分證字號會顯示在客戶資訊區塊中（taiwan_id_number 欄位）
next_action: 修改 shipment-details.php 第 558-580 行標記出貨頁面的客戶資訊區塊

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

## Resolution

root_cause:
fix:
verification:
files_changed: []
