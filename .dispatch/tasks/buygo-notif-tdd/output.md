# 出貨通知 TDD — 執行結果

## 測試結果

| 指標 | 結果 |
|------|------|
| 總測試數 | 238（舊 227 + 新 11） |
| 通過數 | 238 |
| 失敗數 | 0 |
| Assertions | 532 |

## 新增測試檔案

`tests/Unit/Services/ShipmentNotificationTest.php`（11 個測試）

### T1: mark_shipped 觸發 buygo/shipment/marked_as_shipped 恰好一次

| 測試方法 | 驗證重點 |
|---------|---------|
| `testMarkShippedFiresBuyGoHookExactlyOnce` | 1 筆出貨單 → hook 觸發 1 次，且 shipment_id 正確 |
| `testMarkShippedTwoShipmentsFiredTwice` | 2 筆出貨單 → hook 各觸發一次（共 2 次）|
| `testMarkShippedWithNonexistentShipmentDoesNotFireHook` | 不存在的出貨單 → hook 不觸發，返回 WP_Error |

### T2: NotificationHandler::handle_shipment_marked_shipped 只發一次

| 測試方法 | 驗證重點 |
|---------|---------|
| `testHandlerIdempotencyPreventsDoubleSend` | 設定 transient 後，第二次呼叫被 idempotency guard 攔截 |
| `testMarkNotificationSentStoresTimestamp` | mark_notification_sent 儲存 Unix 時間戳 |
| `testIdempotencyIsPerShipment` | 不同出貨單的 idempotency 狀態互不干擾 |

### T3: mark_shipped 不觸發 fluent_cart/shipping_status_changed_to_shipped

| 測試方法 | 驗證重點 |
|---------|---------|
| `testMarkShippedDoesNotFireFluentCartHook` | 執行後 action log 無 fluent_cart hook |
| `testShipmentServiceSourceDoesNotReferenceFluentCartHook` | 靜態分析：原始碼不含 fluent_cart 字串 |

### T4: buygo_order_shipped hook 在 ShipmentService 中沒有任何 do_action（殭屍路徑確認）

| 測試方法 | 驗證重點 |
|---------|---------|
| `testMarkShippedDoesNotFireBuyGoOrderShippedHook` | 執行後 action log 無 buygo_order_shipped |
| `testShipmentServiceSourceHasNoDoActionForBuyGoOrderShipped` | 靜態分析：原始碼無 do_action('buygo_order_shipped') |
| `testBuyGoOrderShippedOnlyExistsInNonShipmentPaths` | 整合驗證：mark_shipped 只觸發 buygo 自有 hook，不觸發兩個殭屍 hook |

## 修改的測試基礎設施

**`tests/bootstrap-unit.php`（測試基礎設施，非實作檔案）**

1. `do_action` 從 no-op 改為記錄呼叫到 `$GLOBALS['mock_action_calls']`
2. 新增 `$GLOBALS['mock_shipment_rows']` 全域（供 wpdb mock 使用）
3. `wpdb->get_row()` 新增 `buygo_shipments WHERE id = X` 查詢支援

## 踩坑記錄

**跨測試類別 wpdb 污染**：多個測試類別（如 `ProductNotificationHandlerTest`、`IdentityServiceTest` 等）在 `setUp` 中替換 `$GLOBALS['wpdb']` 但不在 `tearDown` 還原。解法：在 `ShipmentNotificationTest::setUp` 中儲存 originalWpdb 並自行設置包含 shipment 支援的 wpdb mock，tearDown 時還原。

## 確認的設計事實

- `mark_shipped` 只觸發 `buygo/shipment/marked_as_shipped`（一次），不觸發任何 FluentCart 或殭屍 hook
- `buygo_order_shipped` 只在 `class-order-shipping-manager.php` 和 `class-shipping-status-service.php` 中存在，兩者均不在 `mark_shipped` 呼叫鏈上
- `NotificationHandler` 的 idempotency 機制基於 transient，有效防止重複發送
