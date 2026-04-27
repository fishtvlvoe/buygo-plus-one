## Context

本 change 源自 Code Review 發現的 5 處 API 層隔離違規。違規範圍：
- class-customers-api.php：2 個方法含 $wpdb 查詢（107-232 行、293-402 行）
- class-orders-api.php：split_order() 330 行商業邏輯（430-760 行）
- class-products-api.php：allocate_all_for_customer() 6 段 $wpdb 查詢（1081-1204 行）
- fluentcart-seller-grant-integration.php：518 行完整商業邏輯（172-689 行）

架構規範要求：API 層只做輸入驗證和路由；商業邏輯只放 includes/services/；Integration 層只做橋接。

## Overview

本 change 採用 **Extract Method 模式**逐一遷移，每個方法從 API handler 剪切到 Service，API handler 改為一行呼叫。行為保持完全不變。

## Decisions

### D1: 遷移策略 — Extract Method，保持行為不變

**決策**：採用 Extract Method 模式（剪切 + 一行 delegate），不重寫邏輯。

**理由**：
- 純架構重構，不是功能改善
- 最小化回歸風險
- 便於 diff review（新增行數 ≈ 移除行數）

**反對方案**：重寫為更清晰的實作 → 拒絕，會混入行為變更，超出本 change 範圍。

---

### D2: customers-api 遷移目標 — 新建 CustomerQueryService

**決策**：新建 `includes/services/class-customer-query-service.php`，而非加入 CustomerEditService。

**理由**：
- CustomerEditService 現有 247 行，加上查詢邏輯（~130 行）會超過 300 行上限
- 查詢（Read）與編輯（Write）是不同職責，分開符合 SRP

**方法清單**：
- `getListCustomers(array $params): array` ← 來自 get_list_customers() 第 107-232 行
- `getCustomerDetail(int $customer_id): array` ← 來自 get_customer_detail() 第 293-402 行

---

### D3: orders-api split_order 遷移目標 — OrderService

**決策**：將 split_order() 330 行遷入 `includes/services/class-order-service.php`。

**理由**：
- OrderService 是拆單邏輯的語義歸屬
- OrderService 現有 669 行，加入後 ~999 行，超過 300 行上限
- 但拆分 Service 是 split-oversized-services change 的職責，本 change 只做遷移

**方法清單**：
- `splitOrder(int $order_id, array $split_config): array` ← 來自 split_order() handler

---

### D4: products-api allocate_all 遷移目標 — AllocationService

**決策**：將 allocate_all_for_customer() 遷入 `includes/services/class-allocation-service.php`。

**理由**：
- AllocationService 是分配邏輯的語義歸屬
- 6 段 $wpdb query 直接在 API handler 是最嚴重的隔離違規

**方法清單**：
- `allocateAllForCustomer(int $customer_id, array $params): array` ← 來自 allocate_all_for_customer()

---

### D5: seller-grant-integration 遷移目標 — 新建 SellerGrantService

**決策**：新建 `includes/services/class-seller-grant-service.php`，Integration 改為呼叫此 Service。

**理由**：
- Integration 層職責是「橋接」，不應含商業邏輯（172-689 行，518 行完整邏輯）
- 沒有既有 Service 適合承接賣家權限邏輯

**方法清單**（待 Task 4.1 分析後確認）：
- `grantSellerAccess(int $user_id, array $grant_data): bool`
- `revokeSellerAccess(int $user_id): bool`
- `getSellerGrantStatus(int $user_id): array`

---

## Implementation Distribution Strategy

| Group | 任務 | Agent | 並行？ |
|-------|------|-------|-------|
| Group 1 | CustomerQueryService + customers-api 遷移 | Sonnet 子代理 | [P] |
| Group 2 | split_order → OrderService | Sonnet 子代理 | [P] |
| Group 3 | allocate_all → AllocationService | Sonnet 子代理 | [P] |
| Group 4 | SellerGrantService + seller-grant 遷移 | Sonnet 子代理 | [P] |

**並行策略**：Group 1–4 操作不同檔案，可在同一 Wave 並行派出。
**Token 成本估算**：每 Group 約 3-5K tokens（讀原始碼 + 遷移），總計 ~15-20K tokens。

## Testing Strategy

每個 Group 的測試驗收：
1. 新 Service class 有對應的 PHPUnit 測試（TDD：先寫測試）
2. API handler 遷移後，`grep -r '$wpdb' includes/api/` 應為空
3. `composer test` 全綠

## Risks

| 風險 | 可能性 | 對策 |
|------|--------|------|
| split_order 依賴 API 層的 $this 方法 | 中 | Task 2.1 先分析依賴再遷移 |
| seller-grant 518 行邏輯邊界不清 | 高 | Task 4.1 先做依賴分析，再拆分方法 |
| OrderService 超過 300 行上限 | 確定 | 已記錄為已知技術債，split-oversized-services change 處理 |
