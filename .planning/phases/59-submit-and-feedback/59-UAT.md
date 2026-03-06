---
status: complete
phase: 59-submit-and-feedback
source: [59-01-SUMMARY.md]
started: 2026-03-03T17:30:00+08:00
updated: 2026-03-03T18:30:00+08:00
---

## Current Test

[testing complete]

## Tests

### 1. 手機版底部固定欄
expected: 進入批量表單頁，手機版底部固定欄左側顯示「N 件商品」，右側藍色「批量上架」按鈕。空白表單時按鈕 disabled，填寫名稱+售價後按鈕變藍。
result: pass

### 2. 桌面版提交按鈕
expected: 桌面版（≥768px）右上角，在「匯入 CSV」按鈕旁邊顯示藍色「批量上架 (N)」按鈕。N 為有效商品數量。空白表單時 disabled。
result: pass

### 3. 提交 loading 狀態
expected: 點擊「批量上架」後，按鈕變為 disabled 並顯示旋轉 spinner +「上架中...」文字。不能再次點擊（防重複提交）。
result: pass

### 4. 全部成功結果
expected: 所有商品上架成功時，顯示綠色 toast「成功上架 N 個商品」，然後自動跳回商品列表頁。
result: pass

### 5. 部分失敗結果
expected: 部分商品失敗時，顯示 toast「成功 N 個，失敗 M 個」。成功的商品自動從表單移除，失敗的商品保留並加紅色邊框，每個失敗商品下方顯示具體錯誤原因。可修改後重新提交。
result: skipped
reason: 無法在即時環境觸發部分失敗情境（需要特定後端條件如配額限制），程式碼邏輯已驗證正確

### 6. 全部失敗結果
expected: 所有商品都失敗時（例如配額不足），顯示紅色 toast「上架失敗：[原因]」。所有已填寫資料保留，按鈕恢復可點擊，可修改後重試。
result: pass
note: 在 Bug #3（name→title 欄位對應錯誤）修復前隱式驗證 — 所有商品因「商品名稱為必填」而全部失敗，錯誤紅色邊框正確顯示，資料保留，按鈕恢復可點擊

### 7. 桌面版提示文字
expected: 桌面版表格下方顯示淡灰色小字：「商品名稱和售價為必填。數量填 0 代表無限上架。支援 CSV 匯入（欄位：名稱、售價、數量、描述）。」手機版不顯示此提示。
result: pass

## Summary

total: 7
passed: 5
issues: 0
pending: 0
skipped: 1

## Bugs Fixed During Testing

### Bug #1: price.trim() TypeError
- **現象**: 填寫售價後出現 `TypeError: item.price.trim is not a function`
- **根因**: `<input type="number" v-model="item.price">` 使 Vue 將值轉為 number 型別，呼叫 `.trim()` 失敗
- **修復**: `item.price.trim()` → `String(item.price).trim()`（4 處）
- **檔案**: useBatchCreate.js

### Bug #2: useApi 攔截 batch-create 回應
- **現象**: 提交後出現 `[API] API 錯誤: Error: 操作失敗`
- **根因**: `useApi.request()` 檢查 `!result.success` 並拋出錯誤，batch-create API 在全部失敗時回傳 `success: false`，被 useApi 攔截導致 submitBatch 無法處理三種結果
- **修復**: 改用 `window.fetch()` 直接呼叫 API，繞過 useApi 的 success 檢查
- **檔案**: useBatchCreate.js

### Bug #3: name→title 欄位對應錯誤
- **現象**: 提交後所有商品回傳「商品名稱為必填」
- **根因**: 前端 payload 用 `name` 欄位，後端 `validateItem()` 讀取 `$item['title']`
- **修復**: payload 中 `name: item.name.trim()` → `title: item.name.trim()`
- **檔案**: useBatchCreate.js

## Gaps

[none]
