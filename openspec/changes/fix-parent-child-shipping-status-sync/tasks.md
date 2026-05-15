## 1. 調整 OrderShippingManager 使 syncParentShippingStatus 可被外部呼叫

- [x] 1.1 將 `OrderShippingManager::syncParentShippingStatus()` 的存取修飾子從 `private` 改為 `public`，使 `ShipmentService` 能夠在出貨後觸發父訂單同步（對應 design 決策 1：在 mark_shipped() 成功後呼叫 syncParentShippingStatus()）。驗證：檢查 `includes/services/class-order-shipping-manager.php` 第 129 行，確認方法簽名為 `public function syncParentShippingStatus(int $parentId): void`。

## 2. 在 ShipmentService::mark_shipped() 中增加父訂單同步

- [x] 2.1 實作「Parent order shipping_status synchronizes when child order is marked as shipped」需求：在 `includes/services/class-shipment-service.php` 的 `mark_shipped()` 方法中，於 `check_parent_completion()` 呼叫之後（約第 470 行），新增對 `OrderShippingManager::syncParentShippingStatus()` 的呼叫。具體行為：對每個被標記為已出貨的訂單，若其存在 `parent_id`，則實例化 `OrderShippingManager` 並呼叫 `syncParentShippingStatus($order->parent_id)`。驗證：閱讀 `mark_shipped()` 方法原始碼，確認在 SQL 更新 `shipping_status = 'shipped'` 和 `check_parent_completion()` 之後，存在對父訂單 shipping_status 的同步邏輯。

- [x] 2.2 確保父訂單同步失敗不阻斷出貨流程（對應 design 決策 1 與失敗模式設計）。具體行為：將 `syncParentShippingStatus()` 呼叫包在 try-catch 中，例外時記錄 debug log 但不中斷方法執行。驗證：檢查程式碼，確認同步呼叫位於獨立的 try-catch 區塊，且 `mark_shipped()` 的回傳值不受同步失敗影響。

- [x] 2.3 驗證 syncParentShippingStatus() 計算邏輯無需修改（對應 design 決策 2：syncParentShippingStatus() 邏輯保持不變）。具體行為：確認現有邏輯正確覆蓋 BuyGo 6 個狀態值（unshipped / preparing / processing / shipped / completed / out_of_stock）。驗證：閱讀 `includes/services/class-order-shipping-manager.php` 第 129-200 行，確認狀態計算規則正確。

- [x] 2.4 確認不額外清除 Orders API transient 快取（對應 design 決策 3：不清除 Orders API transient 快取）。具體行為：不在 `mark_shipped()` 或相關方法中新增清除 `buygo_orders_*` transient 的邏輯。驗證：搜尋 `mark_shipped()` 與相關方法原始碼，確認無 `delete_transient` 或 `set_transient` 清除邏輯。

## 3. 補充單元測試驗證父子訂單同步

- [x] 3.1 新增測試驗證：當子訂單透過 `ShipmentService::mark_shipped()` 被標記為已出貨時，父訂單的 `shipping_status` 自動更新為正確狀態（對應 design 驗收標準 1 與介面 / 資料形狀）。驗證：執行 `composer test -- --filter "ShipmentService"`，確認新測試通過。

- [x] 3.2 新增測試驗證：多個子訂單部分出貨時，父訂單的 `shipping_status` 計算正確（對應 design 驗收標準 2，如部分子訂單為 `shipped`，部分為 `preparing`，則父訂單應為 `preparing`）。驗證：執行 `composer test -- --filter "syncParentShippingStatus"`，確認新測試通過。

- [x] 3.3 回歸測試：執行完整測試套件 `composer test`，確認所有現有測試通過，無回歸（對應 design 驗收標準 3 與範圍邊界）。驗證：`composer test` 輸出顯示全部通過。
