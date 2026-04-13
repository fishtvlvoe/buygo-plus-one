# 出貨通知 TDD — 只寫測試，不修實作

## 背景
- ShipmentService::mark_shipped() fire buygo/shipment/marked_as_shipped
- NotificationHandler 監聽此 hook 發 LINE 出貨通知
- LineOrderNotifier::onOrderShipped 監聽 fluent_cart/shipping_status_changed_to_shipped（殭屍路徑）
- 目標：測試確認通知正確、無重複、殭屍路徑不會被 mark_shipped 觸發

## 任務（只寫測試）
- [x] 讀 includes/services/class-shipment-service.php 了解 mark_shipped() 結構
- [x] 讀 includes/services/class-notification-handler.php 了解 handle_shipment_marked_shipped() 結構
- [x] 讀 includes/services/class-line-order-notifier.php 了解 onOrderShipped()、onBuyGoOrderShipped() 結構
- [x] 讀 tests/bootstrap-unit.php 了解現有 mock 基礎設施
- [x] 在 tests/Unit/Services/ShipmentNotificationTest.php 寫以下測試：
  T1: mark_shipped 觸發 buygo/shipment/marked_as_shipped 恰好一次
  T2: NotificationHandler::handle_shipment_marked_shipped 呼叫 LINE 發送，只發一次
  T3: mark_shipped 不觸發 fluent_cart/shipping_status_changed_to_shipped
  T4: buygo_order_shipped hook 沒有任何地方 do_action（確認殭屍）
- [x] 執行 composer test，確認新測試通過，舊測試不破壞（全 227+ pass）
- [x] 把結果寫入 .dispatch/tasks/buygo-notif-tdd/output.md
