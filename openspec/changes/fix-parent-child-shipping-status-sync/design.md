## Context

BuyGo Plus One 使用 FluentCart 的 Order Model 管理訂單，並在 `fct_orders` 表中擴展了 `shipping_status` 欄位。訂單支援父子結構（`type='split'` 為子訂單，`parent_id` 指向父訂單）。

目前存在兩條獨立的運送狀態更新路徑：
- **路徑 A**：`OrderShippingManager::updateShippingStatus()` → 會觸發父訂單同步（`syncParentShippingStatus()`）
- **路徑 B**：`ShipmentService::mark_shipped()` → 直接 SQL 更新，完全繞過路徑 A

當使用者透過出貨單標記子訂單為「已出貨」時，走的是路徑 B，導致父訂單的 `shipping_status` 停留在舊值（如 `preparing`），產生「標籤不同步」的 UI 問題。

## Goals / Non-Goals

**Goals:**
- 子訂單出貨後，父訂單的 `shipping_status` 自動同步為正確狀態
- 不影響現有出貨流程與訂單狀態歷史記錄
- 維持現有測試通過

**Non-Goals:**
- 不改變 FluentCart 核心外掛行為
- 不改變 BuyGo 自定義的 6 個 shipping_status 值域
- 不重構整個 ShipmentService 或 OrderShippingManager 的職責劃分
- 不新增 API endpoint 或 UI 元件

## Decisions

### 決策 1：在 mark_shipped() 成功後呼叫 syncParentShippingStatus()

**選項 A**：在 `mark_shipped()` 中使用 `OrderShippingManager::updateShippingStatus()` 取代直接 SQL
- 被拒絕：會重複觸發 `buygo_shipping_status_changed` hook，可能導致通知重複發送

**選項 B**：在 `mark_shipped()` SQL 更新後，單獨呼叫 `OrderShippingManager::syncParentShippingStatus()`
- 被選擇：最小侵入性，不改變子訂單更新邏輯，只補上父訂單同步缺口

### 決策 2：syncParentShippingStatus() 邏輯保持不變

現有邏輯已正確覆蓋 BuyGo 的 6 個狀態值：
- 所有子訂單 completed → 父 completed
- 所有子訂單 shipped 或 completed → 父 shipped
- 所有子訂單至少 processing → 父 processing
- 有任何子訂單 preparing 以上 → 父 preparing
- 其餘 → 父 unshipped

無需修改此計算邏輯，只需確保它被正確觸發。

### 決策 3：不清除 Orders API transient 快取

**選項 A**：在 shipping_status 變更後清除 `get_transient($cache_key)`
- 被拒絕：transient key 包含查詢參數 hash，難以精確清除所有可能的 key；且 30 秒 TTL 在生產環境可接受

**選項 B**：依賴前端 `loadOrders()` 重新載入時自然過期
- 被選擇：減少複雜度；`updateShippingStatus()` 前端方法在成功後已即時更新本地 `orders.value`

## Implementation Contract

### 行為
當出貨單被標記為「已出貨」時，系統必須自動檢查該出貨單關聯的所有子訂單。若子訂單存在父訂單（`parent_id IS NOT NULL`），則根據所有子訂單的 `shipping_status` 重新計算父訂單應顯示的 `shipping_status`，並更新父訂單。

### 介面 / 資料形狀
無新增介面。現有 `OrderShippingManager::syncParentShippingStatus(int $parentId): void` 為私有方法，透過 `OrderShippingManager` 實例化後呼叫。

### 失敗模式
- 若父訂單不存在：靜默返回（現有行為）
- 若子訂單查詢失敗：靜默返回，記錄 debug log（現有行為）
- 同步失敗不應阻斷出貨單標記為已出貨的主要流程

### 驗收標準
1. 單元測試：模擬子訂單 shipping_status 變更為 `shipped`，驗證父訂單 shipping_status 被同步
2. 單元測試：模擬多個子訂單部分出貨，驗證父訂單 shipping_status 計算正確
3. 現有測試套件全部通過：`composer test`

### 範圍邊界
- **In scope**：`ShipmentService::mark_shipped()` 中增加父訂單同步呼叫
- **Out of scope**：修改 `syncParentShippingStatus()` 計算邏輯、修改前端篩選邏輯、修改 FluentCart 核心

## Risks / Trade-offs

- **[Risk]** `syncParentShippingStatus()` 在 `OrderShippingManager` 中為 `private`，需確認 `ShipmentService` 中如何正確呼叫
  - **Mitigation**：透過 `OrderShippingManager` 建構後呼叫其 `public` 的 `updateShippingStatus()`，或將 `syncParentShippingStatus` 改為 `public`
- **[Risk]** 若子訂單數量極多，同步可能增加 `mark_shipped()` 執行時間
  - **Mitigation**：同步只針對有直接關聯的父訂單，查詢範圍有限；且 `syncParentShippingStatus()` 已使用索引欄位 `parent_id`
