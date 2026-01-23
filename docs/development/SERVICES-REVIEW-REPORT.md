# BuyGo+1 服務類別錯誤處理與日誌集成審查報告

**審查日期：** 2026-01-24
**審查範圍：** `includes/services/` 目錄
**審查文件數量：** 15 個 PHP 服務類別

---

## 📊 總體評估

### 整體評分：3.2/5.0

**關鍵發現：**
- ✅ 部分服務使用了統一的 `DebugService::log()` 進行日誌記錄
- ⚠️ 錯誤處理不一致，部分服務缺少 try-catch
- ⚠️ 日誌級別使用不統一（info/warning/error）
- ❌ 有些服務完全沒有錯誤處理或日誌集成
- ❌ 部分服務使用舊的 `WebhookLogger`，而非新的 `DebugService`

---

## 📋 各服務詳細評分

### 優秀服務 (4.0-5.0) ⭐

| 服務 | 評分 | 錯誤處理 | 日誌集成 | 備註 |
|------|------|---------|---------|------|
| DebugService | 5.0/5.0 | ✅ 完美 | ✅ 本身為日誌服務 | 新系統，完美實現 |
| ProductService | 4.5/5.0 | ✅ 完整 | ✅ 使用 DebugService | 最佳範例 |
| OrderService | 4.5/5.0 | ✅ 完整 + 事務 | ✅ 使用 DebugService | 最佳範例 |
| AllocationService | 4.5/5.0 | ✅ 完整 + 事務 | ✅ 使用 DebugService + 文件日誌 | 最佳範例 |
| ShipmentService | 4.0/5.0 | ✅ 完整 | ✅ 使用 DebugService | 良好 |
| ShippingStatusService | 4.0/5.0 | ✅ 完整 | ✅ 使用 DebugService | 良好 |

### 良好服務 (3.0-3.9)

| 服務 | 評分 | 錯誤處理 | 日誌集成 | 需要改進 |
|------|------|---------|---------|---------|
| FluentCartService | 3.5/5.0 | ✅ 完整 | ⚠️ 使用 WebhookLogger | 升級到 DebugService |
| LineWebhookHandler | 3.5/5.0 | ⚠️ 部分完整 | ⚠️ 使用 WebhookLogger | 升級到 DebugService |
| ImageUploader | 3.5/5.0 | ⚠️ 部分完整 | ⚠️ 使用 WebhookLogger | 升級到 DebugService |
| WebhookLogger | 3.0/5.0 | ⚠️ 部分完整 | ⚠️ 舊系統 | 考慮廢棄或合併 |

### 需要改進服務 (2.0-2.9)

| 服務 | 評分 | 錯誤處理 | 日誌集成 | 優先級 |
|------|------|---------|---------|--------|
| SettingsService | 2.5/5.0 | ⚠️ 部分有 | ❌ 使用 error_log() | 高 |
| LineService | 2.5/5.0 | ⚠️ 部分有 | ❌ 無日誌 | 高 |
| NotificationTemplates | 2.0/5.0 | ❌ 無 | ❌ 無日誌 | **緊急** |
| ExportService | 2.0/5.0 | ❌ 無 | ❌ 無日誌 | **緊急** |

### 嚴重問題服務 (< 2.0)

| 服務 | 評分 | 錯誤處理 | 日誌集成 | 優先級 |
|------|------|---------|---------|--------|
| ProductDataParser | 1.5/5.0 | ❌ 無 | ❌ 無日誌 | **緊急** |

---

## 🔍 需要改進的具體問題清單

### ⚠️ 高優先級（Critical）

#### 1. ProductDataParser (1.5/5.0)
**問題：**
- ❌ 完全沒有 try-catch 區塊
- ❌ 完全沒有使用 Debug_Service::log()
- ⚠️ 有註解掉的日誌代碼

**需要添加：**
```php
public function parse($text_content) {
    Debug_Service::log('ProductDataParser', '開始解析產品資料', [
        'text_length' => strlen($text_content)
    ]);

    try {
        // 解析邏輯...

        Debug_Service::log('ProductDataParser', '解析成功', [
            'product_name' => $result['name']
        ]);

        return $result;
    } catch (\Exception $e) {
        Debug_Service::log('ProductDataParser', '解析失敗', [
            'error' => $e->getMessage()
        ], 'error');

        return new \WP_Error('parse_failed', '解析失敗：' . $e->getMessage());
    }
}
```

#### 2. NotificationTemplates (2.0/5.0)
**問題：**
- ❌ 完全沒有錯誤處理
- ❌ 模板讀取失敗時沒有記錄

**需要添加：**
```php
public function get($key, $context = 'line') {
    try {
        $template = get_option($key);

        if (empty($template)) {
            Debug_Service::log('NotificationTemplates', '模板不存在', [
                'key' => $key,
                'context' => $context
            ], 'warning');

            return $this->get_default_template($key);
        }

        return $template;
    } catch (\Exception $e) {
        Debug_Service::log('NotificationTemplates', '讀取模板失敗', [
            'key' => $key,
            'error' => $e->getMessage()
        ], 'error');

        return null;
    }
}
```

#### 3. ExportService (2.0/5.0)
**問題：**
- ❌ 文件操作沒有錯誤處理
- ❌ 沒有日誌記錄

**需要添加：**
```php
public function export_orders($order_ids) {
    Debug_Service::log('ExportService', '開始匯出訂單', [
        'order_count' => count($order_ids)
    ]);

    try {
        // 匯出邏輯...

        Debug_Service::log('ExportService', '匯出成功', [
            'file_path' => $file_path
        ]);

        return $file_path;
    } catch (\Exception $e) {
        Debug_Service::log('ExportService', '匯出失敗', [
            'error' => $e->getMessage()
        ], 'error');

        return new \WP_Error('export_failed', '匯出失敗：' . $e->getMessage());
    }
}
```

---

### 🟡 中優先級（High）

#### 4. LineService (2.5/5.0)
**問題：**
- ❌ 沒有日誌集成
- ❌ 資料庫操作失敗時沒有記錄

**改進方案：**
```php
public function generate_binding_code($user_id) {
    Debug_Service::log('LineService', '產生綁定碼', ['user_id' => $user_id]);

    try {
        // 生成邏輯...

        Debug_Service::log('LineService', '綁定碼已產生', [
            'user_id' => $user_id,
            'code' => $code
        ]);

        return $code;
    } catch (\Exception $e) {
        Debug_Service::log('LineService', '產生綁定碼失敗', [
            'user_id' => $user_id,
            'error' => $e->getMessage()
        ], 'error');

        return new \WP_Error('code_generation_failed', $e->getMessage());
    }
}
```

#### 5. SettingsService (2.5/5.0)
**問題：**
- ⚠️ 使用 `error_log()` 而非 `DebugService`

**改進方案：**
```php
// ❌ 舊方式
error_log('Settings error: ' . $message);

// ✅ 新方式
Debug_Service::log('SettingsService', '設定錯誤', [
    'message' => $message
], 'error');
```

---

### 🟢 低優先級（Medium）

#### 6. 升級舊的 WebhookLogger 到 DebugService

**需要升級的服務：**
- FluentCartService (3.5/5.0)
- ImageUploader (3.5/5.0)
- LineWebhookHandler (3.5/5.0)

**改進方案：**
```php
// ❌ 舊方式
Webhook_Logger::log('action', $message, $data);

// ✅ 新方式
Debug_Service::log('ServiceName', $message, $data);
```

---

## ✅ 優化建議

### 1. 統一錯誤處理模式

**建議所有服務遵循此模式：**

```php
public function someMethod($param) {
    $service_name = 'ServiceName';

    // 記錄開始
    Debug_Service::log($service_name, '開始執行操作', [
        'param' => $param
    ]);

    try {
        // 輸入驗證
        if (empty($param)) {
            throw new \Exception('參數不能為空');
        }

        // 業務邏輯
        $result = $this->do_something($param);

        // 記錄成功
        Debug_Service::log($service_name, '操作成功', [
            'result' => $result
        ]);

        return $result;

    } catch (\Exception $e) {
        // 記錄失敗
        Debug_Service::log($service_name, '操作失敗', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], 'error');

        return new \WP_Error('operation_failed', '操作失敗：' . $e->getMessage());
    }
}
```

---

### 2. 統一日誌級別使用

| 級別 | 使用場景 | 範例 |
|------|---------|------|
| **info** | 正常操作（開始、成功） | `Debug_Service::log('Service', '操作成功', [])` |
| **warning** | 異常但不影響功能 | `Debug_Service::log('Service', '使用預設值', [], 'warning')` |
| **error** | 錯誤（失敗、異常） | `Debug_Service::log('Service', '操作失敗', [], 'error')` |

---

### 3. 淘汰舊的 WebhookLogger

**理由：**
- 新的 `DebugService` 功能更完整
- 統一日誌格式
- 更好的查詢和分析能力

**遷移步驟：**
1. 將所有 `Webhook_Logger::log()` 替換為 `Debug_Service::log()`
2. 確保傳遞正確的參數格式
3. 測試日誌正常記錄
4. 標記 `WebhookLogger` 為 @deprecated

---

### 4. 添加上下文資訊

**所有日誌應包含：**
- 商品 ID、訂單 ID、用戶 ID（如適用）
- 輸入參數
- 操作結果
- 錯誤堆疊（Exception trace）

**範例：**
```php
Debug_Service::log('OrderService', '建立訂單', [
    'customer_id' => $customer_id,
    'product_ids' => $product_ids,
    'total_amount' => $total_amount,
    'shipping_address' => $shipping_address
]);
```

---

### 5. 使用資料庫事務

**已正確使用事務的服務：**
- ✅ OrderService (shipOrder)
- ✅ AllocationService (allocateStock, updateOrderAllocations)

**需要添加事務的服務：**
- LineService (generate_binding_code, verify_binding_code)

**範例：**
```php
global $wpdb;
$wpdb->query('START TRANSACTION');

try {
    // 多個資料庫操作...

    $wpdb->query('COMMIT');
    return $result;
} catch (\Exception $e) {
    $wpdb->query('ROLLBACK');
    throw $e;
}
```

---

## 📊 評分統計

| 評分範圍 | 服務數量 | 百分比 |
|---------|---------|--------|
| 4.0-5.0 (優秀) | 6 | 40% |
| 3.0-3.9 (良好) | 4 | 27% |
| 2.0-2.9 (及格) | 4 | 27% |
| 1.0-1.9 (不及格) | 1 | 6% |

**最佳實踐範例：**
1. ⭐ DebugService (5.0/5.0)
2. ⭐ ProductService (4.5/5.0)
3. ⭐ OrderService (4.5/5.0)
4. ⭐ AllocationService (4.5/5.0)

**需要優先改進：**
1. 🚨 ProductDataParser (1.5/5.0)
2. 🚨 ExportService (2.0/5.0)
3. 🚨 NotificationTemplates (2.0/5.0)

---

## 🎯 行動計畫

### 第一階段：緊急修復（本週）

- [ ] 修復 ProductDataParser - 添加錯誤處理和日誌
- [ ] 修復 ExportService - 添加錯誤處理和日誌
- [ ] 修復 NotificationTemplates - 添加錯誤處理和日誌

### 第二階段：統一日誌系統（下週）

- [ ] 將所有 `error_log()` 替換為 `DebugService::log()`
- [ ] 將所有 `WebhookLogger` 升級為 `DebugService`

### 第三階段：補充日誌（2週內）

- [ ] 為 LineService 添加日誌記錄
- [ ] 為 SettingsService 統一日誌格式

### 第四階段：代碼審查（1個月內）

- [ ] 審查所有服務的錯誤處理完整性
- [ ] 確保所有關鍵操作都有日誌記錄
- [ ] 建立服務開發規範文檔

---

## 📚 參考資源

- [DebugService 實現](../../includes/services/class-debug-service.php)
- [ProductService 最佳範例](../../includes/services/class-product-service.php)
- [OrderService 最佳範例](../../includes/services/class-order-service.php)
- [編碼規範](CODING-STANDARDS.md)

---

## 🔄 更新日誌

| 日期 | 更新內容 |
|------|----------|
| 2026-01-24 | 初始審查報告，評分 3.2/5.0 |

---

**報告生成者：** Claude Code
**最後更新：** 2026-01-24
