## Group 1: customers-api 遷移 [P]

- [x] [P] 1.1 [Tool: sonnet] 依 design「D2: customers-api 遷移目標 — 新建 CustomerQueryService」，新建 `includes/services/class-customer-query-service.php`，包含 getListCustomers() 和 getCustomerDetail() 方法骨架（TDD 先建骨架供測試參考）。確認 spec「New service classes are unit-testable」— 新 class 存在且可 autoload。
- [x] [P] 1.2 [Tool: sonnet] 依 design「D2: customers-api 遷移目標 — 新建 CustomerQueryService」，在 `tests/Unit/Services/CustomerQueryServiceTest.php` 撰寫測試案例，覆蓋 getListCustomers() 分頁邏輯和 getCustomerDetail() 查詢邏輯。確認 spec「New service classes are unit-testable」— 測試此時應為紅燈。
- [x] 1.3 [Tool: sonnet] 依 design「D1: 遷移策略 — Extract Method，保持行為不變」，將 class-customers-api.php 第 107-232 行分頁查詢邏輯剪切至 CustomerQueryService::getListCustomers()。API handler 改為一行呼叫。確認 spec「API handlers delegate business logic to service layer」— customers-api 零個 $wpdb 查詢。
- [x] 1.4 [Tool: sonnet] 依 design「D1: 遷移策略 — Extract Method，保持行為不變」，將 class-customers-api.php 第 293-402 行 3 段 $wpdb query 剪切至 CustomerQueryService::getCustomerDetail()。API handler 改為一行呼叫。跑 `composer test -- --filter CustomerQueryServiceTest` 確認綠燈。

## Group 2: orders-api 遷移 [P]

- [x] [P] 2.1 [Tool: sonnet] 依 design「D3: orders-api split_order 遷移目標 — OrderService」與「D1: 遷移策略 — Extract Method，保持行為不變」，先分析 split_order()（430-760 行）依賴，解耦後將邏輯剪切至 OrderService::splitOrder()。API handler 改為一行呼叫。確認 spec「API handlers delegate business logic to service layer」— orders-api 零個 $wpdb 查詢。
- [x] 2.2 [Tool: sonnet] 在 tests/Unit/Services/OrderServiceTest.php 補充 splitOrder() 的測試案例，確認主要拆單場景（正常拆、邊界條件）全綠。執行 `composer test` 確認無回歸。

## Group 3: products-api 遷移 [P]

- [x] [P] 3.1 [Tool: sonnet] 依 design「D4: products-api allocate_all 遷移目標 — AllocationService」與「D1: 遷移策略 — Extract Method，保持行為不變」，將 class-products-api.php 第 1081-1204 行 6 段 $wpdb query 剪切至 AllocationService::allocateAllForCustomer()。API handler 改為一行呼叫。確認 spec「API handlers delegate business logic to service layer」— products-api 分配相關方法零個 $wpdb 查詢。
- [x] 3.2 [Tool: sonnet] 在 tests/Unit/Services/AllocationServiceTest.php 補充 allocateAllForCustomer() 的測試案例，確認分配邏輯全綠。執行 `composer test` 確認無回歸。

## Group 4: seller-grant 遷移 [P]

- [x] [P] 4.1 [Tool: sonnet] 依 design「D5: seller-grant-integration 遷移目標 — 新建 SellerGrantService」，分析 fluentcart-seller-grant-integration.php 第 172-689 行，識別商業邏輯邊界。建立 `includes/services/class-seller-grant-service.php` 骨架，定義方法簽名。確認 spec「Integration layer delegates data access to service layer」— 骨架建立完成。
- [x] [P] 4.2 [Tool: sonnet] 在 `tests/Unit/Services/SellerGrantServiceTest.php` 撰寫測試案例，覆蓋賣家權限管理的主要場景。確認 spec「New service classes are unit-testable」— 測試此時應為紅燈。
- [x] 4.3 [Tool: sonnet] 依 design「D1: 遷移策略 — Extract Method，保持行為不變」，將 Integration 中的商業邏輯剪切至 SellerGrantService 對應方法。Integration 改為只做 hook 橋接 + 呼叫 SellerGrantService。確認 spec「Integration layer delegates data access to service layer」— Integration 零個 $wpdb 查詢。跑 `composer test` 確認全綠。
