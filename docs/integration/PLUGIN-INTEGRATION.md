# 外掛整合說明

## 外掛架構

系統由兩個外掛組成：

### buygo-line-notify（基礎設施層）

**位置**：`/wp-content/plugins/buygo-line-notify/`

**職責**：
- LINE API 通訊（圖片下載、訊息發送）
- 設定管理（加密/解密）
- 日誌記錄
- 提供 Facade API 供其他外掛使用

**核心服務**：
- `ImageUploader` - 圖片上傳服務
- `LineMessagingService` - 訊息發送服務（reply + push）
- `SettingsService` - 設定管理服務
- `Logger` - 日誌服務

**Facade API**：
- `BuygoLineNotify::image_uploader()` - 取得 ImageUploader 實例
- `BuygoLineNotify::messaging()` - 取得 LineMessagingService 實例
- `BuygoLineNotify::settings()` - 取得 SettingsService 類別
- `BuygoLineNotify::logger()` - 取得 Logger 實例
- `BuygoLineNotify::is_active()` - 檢查外掛是否啟用

### buygo-plus-one-dev（業務邏輯層）

**位置**：`/wp-content/plugins/buygo-plus-one-dev/`

**職責**：
- Webhook 事件處理流程
- 商品上架業務邏輯
- 訂單通知業務邏輯
- 模板管理（通知內容）
- 權限檢查
- 商品資料解析

**核心服務**：
- `LineWebhookHandler` - Webhook 處理器
- `LineOrderNotifier` - 訂單通知服務
- `FluentCartService` - FluentCart 商品建立服務
- `ProductDataParser` - 商品資料解析器
- `NotificationTemplates` - 通知模板管理

## 整合方式

### Facade 模式

`buygo-plus-one-dev` 透過 `BuygoLineNotify` Facade 使用 `buygo-line-notify` 的服務：

```php
// 圖片上傳
if ( class_exists( '\BuygoLineNotify\BuygoLineNotify' ) && \BuygoLineNotify\BuygoLineNotify::is_active() ) {
    $image_uploader = \BuygoLineNotify\BuygoLineNotify::image_uploader();
    $attachment_id = $image_uploader->download_and_upload( $message_id, $user_id );
}

// 訊息發送
if ( class_exists( '\BuygoLineNotify\BuygoLineNotify' ) && \BuygoLineNotify\BuygoLineNotify::is_active() ) {
    $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
    $result = $messaging->send_reply( $reply_token, $message, $line_uid );
}
```

### 依賴檢查

所有使用 Facade 的地方都會先檢查外掛是否啟用：

```php
if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) || ! \BuygoLineNotify\BuygoLineNotify::is_active() ) {
    // 處理外掛未啟用的情況
    return;
}
```

## 資料流程

### 商品上架流程

```
LINE Webhook (文字訊息)
    ↓
buygo-plus-one-dev (LineWebhookHandler)
    ↓
檢查 buygo-line-notify 是否啟用
    ↓
解析商品資料 (ProductDataParser)
    ↓
取得暫存圖片 (BuygoLineNotify::image_uploader()->get_temp_images())
    ↓
建立 FluentCart 商品 (FluentCartService::create_product())
    ↓
    ├─ 建立 WordPress Post (wp_insert_post)
    ├─ 建立商品詳情 (fct_product_details)
    ├─ 建立商品變體 (fct_product_variations)
    └─ 設定圖片和 meta
    ↓
清除暫存圖片 (BuygoLineNotify::image_uploader()->clear_temp_images())
    ↓
發送成功訊息 (BuygoLineNotify::messaging()->send_reply())
```

### 訂單通知流程

```
FluentCart 訂單事件
    ↓
buygo-plus-one-dev (LineOrderNotifier)
    ↓
檢查 buygo-line-notify 是否啟用
    ↓
建立通知訊息 (NotificationTemplates)
    ↓
推播訊息 (BuygoLineNotify::messaging()->push_message())
    ↓
重試機制（失敗時）
```

## 錯誤處理

### 錯誤記錄

所有錯誤都會記錄到兩個地方：

1. **DebugService** - 詳細的除錯日誌（資料庫 + 檔案）
2. **WebhookLogger** - Webhook 事件日誌（顯示在流程監控介面）

### 錯誤類型

- **外掛未啟用**：`BuygoLineNotify::is_active()` 返回 false
- **FluentCart 未安裝**：`class_exists('FluentCart\App\App')` 返回 false
- **資料表不存在**：`fct_product_details` 或 `fct_product_variations` 不存在
- **資料插入失敗**：`$wpdb->insert()` 返回 false
- **例外錯誤**：try-catch 捕獲的 Exception

### 錯誤處理流程

```php
// FluentCartService::create_product()
try {
    // 建立商品
    $product_id = wp_insert_post( $post_data, true );
    
    if ( is_wp_error( $product_id ) ) {
        // 記錄錯誤到 DebugService 和 WebhookLogger
        $this->debugService->log( 'FluentCartService', '錯誤訊息', array(...), 'error' );
        $this->webhookLogger->log( 'error', array(...), $user_id, $line_uid );
        return $product_id;
    }
    
    // 建立商品詳情
    $details_result = $this->create_product_details( $product_id, $product_data );
    if ( is_wp_error( $details_result ) ) {
        // 刪除已建立的 post
        wp_delete_post( $product_id, true );
        // 記錄錯誤
        $this->webhookLogger->log( 'error', array(...), $user_id, $line_uid );
        return $details_result;
    }
    
} catch ( \Exception $e ) {
    // 記錄例外錯誤
    $this->debugService->log( 'FluentCartService', '例外錯誤', array(...), 'error' );
    $this->webhookLogger->log( 'error', array(...), $user_id, $line_uid );
    return new \WP_Error( 'exception', $e->getMessage() );
}
```

## 設定管理

### SettingsService 讀取順序

`buygo-line-notify` 的 `SettingsService` 會依以下順序讀取設定：

1. **獨立 option**（新外掛格式）
   - `buygo_line_channel_access_token`
   - `buygo_line_channel_secret`
   - `buygo_line_liff_id`

2. **buygo_core_settings**（舊外掛格式，陣列）
   - 支援加密欄位自動解密

### 加密欄位

以下欄位會自動加密/解密：
- `line_channel_secret`
- `line_channel_access_token`
- `line_login_channel_secret`

## 日誌整合

### buygo-line-notify Logger

`buygo-line-notify` 的 `Logger` 會自動整合 `buygo-plus-one-dev` 的 `WebhookLogger`：

```php
// buygo-line-notify/includes/services/class-logger.php
public function log($level, $data, $user_id = null, $line_uid = null)
{
    // 記錄到 error_log
    error_log($message);
    
    // 如果 buygo-plus-one-dev 的 WebhookLogger 存在，也記錄到那邊
    if (class_exists('\BuyGoPlus\Services\WebhookLogger')) {
        $webhook_logger = \BuyGoPlus\Services\WebhookLogger::get_instance();
        if (method_exists($webhook_logger, 'log')) {
            $webhook_logger->log($level, $data, $user_id, $line_uid);
        }
    }
}
```

## 常見問題

### Q: 商品建立失敗，但沒有看到錯誤訊息？

**A**: 檢查以下項目：

1. **檢查流程監控介面**
   - 查看「今日錯誤」標籤
   - 點擊錯誤事件查看詳細資訊

2. **檢查 FluentCart 是否安裝**
   - 確認 `FluentCart\App\App` 類別存在

3. **檢查資料表是否存在**
   - `wp_fct_product_details`
   - `wp_fct_product_variations`

4. **檢查資料庫錯誤**
   - 查看 `$wpdb->last_error` 的內容

### Q: 圖片上傳失敗？

**A**: 檢查以下項目：

1. **buygo-line-notify 是否啟用**
   - 確認 `BuygoLineNotify::is_active()` 返回 true

2. **LINE Channel Access Token 是否設定**
   - 檢查 `SettingsService::get('line_channel_access_token')`

3. **圖片下載是否成功**
   - 查看日誌中的 `image_download_start` 和 `image_downloaded` 事件

### Q: 訊息發送失敗？

**A**: 檢查以下項目：

1. **buygo-line-notify 是否啟用**
   - 確認 `BuygoLineNotify::is_active()` 返回 true

2. **LINE Channel Access Token 是否有效**
   - 檢查 Token 是否過期

3. **使用者是否已加 Bot 為好友**
   - Push 訊息需要使用者先加 Bot 為好友

## 測試檢查清單

### 整合測試

- [ ] buygo-line-notify 外掛已啟用
- [ ] buygo-plus-one-dev 外掛已啟用
- [ ] `BuygoLineNotify::is_active()` 返回 true
- [ ] Facade API 可以正常使用
- [ ] 圖片上傳功能正常
- [ ] 訊息發送功能正常
- [ ] 商品建立功能正常
- [ ] 錯誤訊息正確顯示在流程監控介面

### 功能測試

- [ ] 透過 LINE 上傳圖片
- [ ] 透過 LINE 發送商品資料
- [ ] 商品成功建立到 FluentCart
- [ ] 訂單建立後收到 LINE 通知
- [ ] 錯誤訊息正確記錄和顯示

## 除錯技巧

### 檢查外掛狀態

```php
// 檢查 buygo-line-notify 是否啟用
if ( class_exists( '\BuygoLineNotify\BuygoLineNotify' ) ) {
    $is_active = \BuygoLineNotify\BuygoLineNotify::is_active();
    var_dump($is_active);
}
```

### 檢查 Facade API

```php
// 測試 Facade API
$image_uploader = \BuygoLineNotify\BuygoLineNotify::image_uploader();
$messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
$settings = \BuygoLineNotify\BuygoLineNotify::settings();
$logger = \BuygoLineNotify\BuygoLineNotify::logger();
```

### 檢查日誌

```php
// 檢查 WebhookLogger 日誌
$webhook_logger = \BuyGoPlus\Services\WebhookLogger::get_instance();
$logs = $webhook_logger->get_logs( array(
    'event_type' => 'error',
    'limit' => 10,
) );

// 檢查 DebugService 日誌
$debug_service = \BuyGoPlus\Services\DebugService::get_instance();
$logs = $debug_service->getLogs( array(
    'module' => 'FluentCartService',
    'level' => 'error',
), 10 );
```

## 參考資源

- [BuyGo Plus One 整合測試指南](../testing/INTEGRATION-TESTING.md)
- [BuyGo Line Notify README](../../buygo-line-notify/README.md)
- [FluentCart 文件](https://fluentcart.com/docs/)
