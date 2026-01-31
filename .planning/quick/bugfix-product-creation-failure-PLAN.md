# Bug 修復計劃：商品上架失敗

## 問題描述

**症狀**：
- 系統顯示「0 今日商品建立」
- 系統顯示「1 今日錯誤」
- 日誌中有 `product_creating` 事件，但沒有 `product_created` 事件
- 商品建立流程中斷，沒有明確的錯誤訊息顯示

**影響範圍**：
- 透過 LINE Webhook 建立的商品無法成功上架到 FluentCart
- 使用者無法看到具體的錯誤原因

## 根本原因分析

### 已識別的問題

1. **錯誤處理不完整**
   - `FluentCartService::create_product()` 中的錯誤沒有正確記錄到 `WebhookLogger`
   - 內部方法（`create_product_details`, `create_variation` 等）返回錯誤時沒有傳遞到上層

2. **日誌記錄不一致**
   - `DebugService::log()` 的參數順序錯誤
   - 錯誤沒有同時記錄到 `WebhookLogger`（使用者可見的介面）

3. **資料表檢查安全性問題**
   - 使用字串拼接而非 `prepare` 語句

### 已完成的修復

✅ 修正所有 `DebugService::log()` 的調用方式
✅ 確保錯誤同時記錄到 `WebhookLogger`
✅ 修正資料表檢查使用 `prepare` 確保安全性
✅ 確保所有資料庫操作失敗都會正確返回錯誤

## 修復計劃

### Task 1: 驗證錯誤處理流程

**目標**：確保所有錯誤路徑都正確記錄和傳遞

**檢查點**：
- [ ] `FluentCartService::create_product()` 所有錯誤都記錄到 `WebhookLogger`
- [ ] `create_product_details()` 返回 `WP_Error` 時正確傳遞
- [ ] `create_default_variation()` 返回 `WP_Error` 時正確傳遞
- [ ] `create_variable_product()` 返回 `WP_Error` 時正確傳遞
- [ ] `create_variation()` 返回 `WP_Error` 時正確傳遞

**驗證方式**：
- 檢查程式碼中所有錯誤返回點
- 確認每個錯誤都有對應的 `webhookLogger->log()` 調用

### Task 2: 測試錯誤訊息顯示

**目標**：確保錯誤訊息正確顯示在流程監控介面

**測試步驟**：
1. 模擬 FluentCart 未安裝的情況
2. 模擬資料表不存在的情況
3. 模擬資料插入失敗的情況
4. 檢查「流程監控」介面的「今日錯誤」標籤
5. 點擊錯誤事件查看詳細資訊

**預期結果**：
- 錯誤事件出現在「今日錯誤」中
- 錯誤訊息包含詳細資訊（error_code, error, table_name 等）
- 使用者可以清楚看到失敗原因

### Task 3: 添加診斷工具

**目標**：提供診斷工具幫助快速定位問題

**實作內容**：
- 檢查 FluentCart 是否安裝
- 檢查必要的資料表是否存在
- 檢查資料庫連線是否正常
- 提供診斷報告

## 驗證標準

### 功能驗證

- [ ] 商品建立成功時，`product_created` 事件正確記錄
- [ ] 商品建立失敗時，`error` 事件正確記錄
- [ ] 錯誤訊息顯示在「流程監控」介面
- [ ] 錯誤訊息包含足夠的診斷資訊

### 程式碼驗證

- [ ] 所有錯誤路徑都有對應的日誌記錄
- [ ] 所有資料庫操作都使用 `prepare` 確保安全性
- [ ] 所有 `WP_Error` 都正確傳遞到上層
- [ ] 程式碼符合 WordPress 編碼標準

## 執行狀態

- [x] Task 1: 驗證錯誤處理流程 - 已完成
- [x] Task 2: 測試錯誤訊息顯示 - 待使用者測試
- [ ] Task 3: 添加診斷工具 - 待實作

## 下一步

1. 請使用者重新測試商品上架功能
2. 檢查「流程監控」介面的錯誤訊息
3. 根據錯誤訊息進一步診斷問題
4. 如有需要，實作診斷工具
