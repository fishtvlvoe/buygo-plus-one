## Problem

當子訂單透過出貨單（Shipment）標記為「已出貨」時，父訂單的運送狀態（shipping_status）未同步更新，導致訂單列表頁面出現「標籤不同步」現象：
- 父訂單在「全部」tab 仍顯示舊狀態（如「備貨中」）
- 子訂單在「已出貨」tab 顯示正確狀態
- 操作欄與運送狀態下拉框顯示不一致

## Root Cause

三層缺口疊加導致此問題：

1. **ShipmentService 繞過同步機制**：`ShipmentService::mark_shipped()` 使用原始 SQL 直接更新 `fct_orders.shipping_status`，未呼叫 `OrderShippingManager::syncParentShippingStatus()`，導致父訂單 shipping_status 停留在舊值。

2. **OrderSyncService 監聽錯誤 hook 且未同步 shipping_status**：`OrderSyncService` 監聽 `fluentcart/order_updated`，但此事件僅在 FluentCart 後台編輯訂單時觸發，且即使觸發也只同步 `status` 與 `payment_status`，完全不處理 `shipping_status`。

3. **FluentCart 原生 shipping_status 值域與 BuyGo 擴展值域衝突**：FluentCart 原生值域為 `unshipped / shipped / delivered / unshippable`，BuyGo Plus One 擴展為 `unshipped / preparing / processing / shipped / completed / out_of_stock`。當 BuyGo 使用 `preparing`、`processing`、`completed` 等值時，完全繞過 FluentCart 正規的 `OrderStatusUpdated` 事件系統。

## Proposed Solution

### 核心修復：ShipmentService 標記出貨後同步父訂單 shipping_status

在 `includes/services/class-shipment-service.php` 的 `mark_shipped()` 方法中，於 `check_parent_completion()` 之後，增加對父訂單 `shipping_status` 的同步：
- 對每個被標記為已出貨的子訂單，若其存在 `parent_id`，則呼叫 `OrderShippingManager::syncParentShippingStatus($parent_id)`
- 使父訂單的 shipping_status 根據所有子訂單狀態重新計算（邏輯已存在於 `OrderShippingManager`，只需正確觸發）

### 輔助修復：確保 OrderShippingManager 的同步邏輯涵蓋所有狀態

檢查 `OrderShippingManager::syncParentShippingStatus()` 的狀態計算邏輯：
- 所有子訂單 completed → 父 completed
- 所有子訂單 shipped 或 completed → 父 shipped
- 所有子訂單至少 processing → 父 processing
- 有任何子訂單 preparing 以上 → 父 preparing
- 其餘 → 父 unshipped

確認此邏輯正確覆蓋 BuyGo 自定義的 6 個狀態值。

### 輔助修復：清除 API 快取

在 shipping_status 變更後，確保 Orders API 的 30 秒 transient 快取被清除，避免前端讀取到舊資料。

## Non-Goals

- 不修改 FluentCart 核心外掛
- 不改變 BuyGo Plus One 自定義的 6 個 shipping_status 值域定義
- 不重構整個訂單狀態系統，僅修復父子訂單同步缺口
- 不新增 UI 元件或 API endpoint

## Success Criteria

1. 子訂單透過出貨單標記為「已出貨」後，父訂單的 `shipping_status` 自動同步為正確狀態
2. 「全部」tab 中父訂單顯示的運送狀態與「已出貨」tab 中子訂單的狀態一致
3. 操作欄顯示的狀態標籤與運送狀態下拉框選項一致
4. 現有測試全部通過，不引入回歸

## Impact

- Affected code:
  - Modified:
    - includes/services/class-shipment-service.php
    - includes/services/class-order-shipping-manager.php（驗證同步邏輯）
    - includes/services/class-order-sync-service.php（選擇性：增加 shipping_status 同步）
    - includes/api/class-orders-api.php（選擇性：清除 transient 快取）
  - New: (none)
  - Removed: (none)
