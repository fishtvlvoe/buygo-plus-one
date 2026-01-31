# Bug 修復總結：商品上架失敗

## 執行時間

2026-01-27

## 問題描述

**症狀**：
- 系統顯示「0 今日商品建立」
- 系統顯示「1 今日錯誤」
- 日誌中有 `product_creating` 事件，但沒有 `product_created` 事件
- 商品建立流程中斷，沒有明確的錯誤訊息顯示

## 根本原因

1. **錯誤處理不完整**
   - `FluentCartService` 內部方法返回 `WP_Error` 時，沒有記錄到 `WebhookLogger`
   - 錯誤只在 `DebugService` 中記錄，使用者看不到

2. **日誌記錄不一致**
   - `DebugService::log()` 的參數順序錯誤
   - 錯誤沒有同時記錄到 `WebhookLogger`（使用者可見的介面）

3. **資料表檢查安全性問題**
   - 使用字串拼接而非 `prepare` 語句

## 修復內容

### 1. 修正錯誤處理流程

**檔案**：`includes/services/class-fluentcart-service.php`

**修改點**：
- ✅ `create_product()` - 所有錯誤都記錄到 `WebhookLogger`
- ✅ `create_product_details()` - 錯誤記錄到 `WebhookLogger`
- ✅ `create_default_variation()` - 錯誤記錄到 `WebhookLogger`
- ✅ `create_variable_product()` - 錯誤記錄到 `WebhookLogger`
- ✅ `create_variation()` - 錯誤記錄到 `WebhookLogger`

**範例修改**：
```php
// 修改前
if ( is_wp_error( $variation_result ) ) {
    return $variation_result;
}

// 修改後
if ( is_wp_error( $variation_result ) ) {
    $this->webhookLogger->log( 'error', array(
        'message' => '預設變體建立失敗',
        'error' => $variation_result->get_error_message(),
        'error_code' => $variation_result->get_error_code(),
        'product_id' => $product_id,
    ), $data['user_id'] ?? null, $data['line_uid'] ?? null );
    return $variation_result;
}
```

### 2. 修正日誌記錄方式

**修改點**：
- ✅ 所有 `DebugService::log()` 調用都使用正確的參數順序
- ✅ 所有錯誤都同時記錄到 `WebhookLogger`

**範例修改**：
```php
// 修改前
$this->debugService->log( 'error', array(
    'message' => '商品詳情插入失敗',
), $user_id, null );

// 修改後
$this->debugService->log( 'FluentCartService', '商品詳情插入失敗', array(
    'error' => $wpdb->last_error,
    'product_id' => $product_id,
    'table_name' => $table_name,
), 'error' );
$this->webhookLogger->log( 'error', array(
    'message' => '商品詳情插入失敗',
    'error' => $wpdb->last_error,
    'error_code' => 'insert_failed',
    'product_id' => $product_id,
    'table_name' => $table_name,
), $data['user_id'] ?? null, $data['line_uid'] ?? null );
```

### 3. 修正資料表檢查安全性

**修改點**：
- ✅ 所有資料表檢查都使用 `$wpdb->prepare()` 確保安全性

**範例修改**：
```php
// 修改前
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {

// 修改後
$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
if ( $table_exists !== $table_name ) {
```

### 4. 確保錯誤傳遞

**修改點**：
- ✅ 所有內部方法都正確返回 `WP_Error`
- ✅ 所有錯誤都正確傳遞到上層
- ✅ 部分建立失敗時，會刪除已建立的 post（rollback）

## 測試驗證

### 待測試項目

- [ ] 商品建立成功時，`product_created` 事件正確記錄
- [ ] 商品建立失敗時，`error` 事件正確記錄
- [ ] 錯誤訊息顯示在「流程監控」介面
- [ ] 錯誤訊息包含足夠的診斷資訊（error_code, error, table_name 等）

### 測試步驟

1. 透過 LINE 發送商品資料
2. 檢查「流程監控」介面的「今日錯誤」標籤
3. 點擊錯誤事件查看詳細資訊
4. 確認錯誤訊息包含：
   - 錯誤類型（資料表不存在、插入失敗等）
   - 詳細錯誤訊息（`$wpdb->last_error`）
   - 相關資料（product_id、table_name 等）

## 相關檔案

### 修改的檔案

- `includes/services/class-fluentcart-service.php` - 主要修復檔案

### 新增的檔案

- `docs/integration/PLUGIN-INTEGRATION.md` - 外掛整合說明文件
- `.planning/quick/bugfix-product-creation-failure-PLAN.md` - 修復計劃
- `.planning/quick/bugfix-product-creation-failure-SUMMARY.md` - 修復總結（本檔案）

## 下一步

1. **請使用者重新測試商品上架功能**
   - 透過 LINE 發送商品資料
   - 檢查「流程監控」介面的錯誤訊息

2. **根據錯誤訊息進一步診斷**
   - 如果仍有問題，錯誤訊息會提供更多資訊
   - 可以根據錯誤訊息定位具體問題

3. **如有需要，實作診斷工具**
   - 檢查 FluentCart 是否安裝
   - 檢查必要的資料表是否存在
   - 提供診斷報告

## 預期結果

修復後，當商品建立失敗時：

1. **錯誤會正確記錄**
   - `DebugService` 記錄詳細的除錯資訊
   - `WebhookLogger` 記錄使用者可見的錯誤

2. **錯誤會顯示在介面上**
   - 「流程監控」介面的「今日錯誤」標籤會顯示錯誤
   - 點擊錯誤事件可以查看詳細資訊

3. **錯誤訊息包含診斷資訊**
   - 錯誤類型（error_code）
   - 詳細錯誤訊息（error）
   - 相關資料（product_id、table_name 等）

這樣可以幫助快速定位和解決問題。
