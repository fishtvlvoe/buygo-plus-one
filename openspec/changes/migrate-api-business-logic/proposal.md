## Summary

將 API 層和 Integration 層中的商業邏輯遷移至 Service 層，恢復架構隔離規範。

## Motivation

Code Review 發現 5 處隔離違規：API handler 直接執行 $wpdb 查詢、包含完整商業邏輯（拆單、分頁、統計），違反「API 層只做驗證和路由」原則。這些違規導致：
1. 商業邏輯無法被單元測試覆蓋
2. 相同邏輯可能被重複實作
3. 修改商業規則需要同時改 API 和 Service 層

## Proposed Solution

1. 將 customers-api 的分頁查詢和客戶詳情查詢遷入既有 CustomerEditService 或新建 CustomerQueryService
2. 將 orders-api 的 split_order() 330 行邏輯遷入 OrderService
3. 將 products-api 的 allocate_all_for_customer() 遷入 AllocationService
4. 將 seller-grant-integration 的資料存取抽出為 SellerGrantService
5. API 層改為只做參數驗證 + 呼叫 Service 方法 + 格式化回應

## Non-Goals

- 不修改商業邏輯本身（純遷移，行為不變）
- 不拆分超大 Service class（另有 split-oversized-services change）
- 不修改前端
- 不新增功能

## Alternatives Considered

- 只遷移最大的 split_order → 拒絕，其他 4 處同樣違規，應一次處理
- 建新 Service 取代所有 → 拒絕，應優先利用既有 Service class

## Impact

- Affected specs: 無（純架構重構，行為不變）
- Affected code:
  - Modified: includes/api/class-customers-api.php（移除 $wpdb query，改呼叫 Service）
  - Modified: includes/api/class-orders-api.php（split_order 遷移）
  - Modified: includes/api/class-products-api.php（allocate_all_for_customer 遷移）
  - Modified: includes/services/class-order-service.php（新增 splitOrder 方法）
  - Modified: includes/services/class-allocation-service.php（新增 allocateAllForCustomer 方法）
  - Modified: includes/integrations/class-fluentcart-seller-grant-integration.php（抽出資料存取）
  - New: includes/services/class-customer-query-service.php（客戶查詢 Service）
  - New: includes/services/class-seller-grant-service.php（賣家權限 Service）

## Implementation Strategy

此變更涉及 4 個獨立的 API 檔案遷移，各自有明確邊界，可分為 4 個並行 Group 執行：

- **Group 1（customers-api）**：新建 CustomerQueryService + 遷移 2 個方法 → 4 tasks，串行
- **Group 2（orders-api）**：split_order 330 行遷入 OrderService → 2 tasks，串行
- **Group 3（products-api）**：allocate_all 遷入 AllocationService → 2 tasks，串行
- **Group 4（seller-grant）**：新建 SellerGrantService + 遷移 + 整合測試 → 3 tasks，串行

Group 1–4 之間無依賴，可並行執行（不同檔案，無衝突）。
