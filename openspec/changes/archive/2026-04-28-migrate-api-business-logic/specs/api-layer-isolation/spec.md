## ADDED Requirements

### Requirement: API handlers delegate business logic to service layer

API handler 方法 SHALL NOT 包含直接的 $wpdb 查詢或商業邏輯。所有資料存取和商業邏輯 SHALL 委派至對應的 Service class。

#### Scenario: customers-api 無直接 $wpdb 查詢

- **WHEN** 審查 includes/api/class-customers-api.php 中的 get_list_customers() 和 get_customer_detail() 實作
- **THEN** 這兩個方法 SHALL NOT 包含任何 $wpdb 查詢
- **AND** 這兩個方法 SHALL 呼叫 CustomerQueryService 對應方法
- **AND** CustomerQueryService SHALL 持有所有分頁查詢和客戶詳情查詢邏輯

##### Example: API handler contains no wpdb queries

- **GIVEN** includes/api/class-customers-api.php
- **WHEN** 執行 `grep -c '$wpdb' includes/api/class-customers-api.php`
- **THEN** 結果 SHALL 為 0

#### Scenario: orders-api split_order 委派至 OrderService

- **WHEN** 審查 includes/api/class-orders-api.php 中的 split_order() handler
- **THEN** handler SHALL NOT 包含任何 $wpdb 查詢或業務判斷邏輯
- **AND** handler SHALL 呼叫 OrderService::splitOrder()
- **AND** OrderService SHALL 持有所有拆單邏輯（原 430-760 行）

##### Example: split_order delegates to OrderService

- **GIVEN** includes/api/class-orders-api.php
- **WHEN** 執行 `grep -c '$wpdb' includes/api/class-orders-api.php`
- **THEN** 結果 SHALL 為 0

#### Scenario: products-api allocate_all 委派至 AllocationService

- **WHEN** 審查 includes/api/class-products-api.php 中的 allocate_all_for_customer() handler
- **THEN** handler SHALL NOT 包含任何 $wpdb 查詢
- **AND** handler SHALL 呼叫 AllocationService::allocateAllForCustomer()
- **AND** AllocationService SHALL 持有所有 6 段分配查詢邏輯（原 1081-1204 行）

##### Example: products-api allocate handler has no wpdb

- **GIVEN** includes/api/class-products-api.php
- **WHEN** 執行 `grep -n '$wpdb' includes/api/class-products-api.php | grep -i 'allocat'`
- **THEN** 無任何輸出

---

### Requirement: Integration layer delegates data access to service layer

Integration class SHALL NOT 包含直接的 $wpdb 查詢或資料處理邏輯。所有資料存取 SHALL 委派至 Service class。

#### Scenario: seller-grant 的資料存取透過 SellerGrantService

- **WHEN** 審查 includes/integrations/class-fluentcart-seller-grant-integration.php
- **THEN** Integration class SHALL NOT 包含任何 $wpdb 查詢
- **AND** Integration SHALL 只做 FluentCart hook 橋接
- **AND** SellerGrantService SHALL 持有所有賣家權限管理邏輯

##### Example: Integration contains no wpdb queries

- **GIVEN** includes/integrations/class-fluentcart-seller-grant-integration.php
- **WHEN** 執行 `grep -c '$wpdb' includes/integrations/class-fluentcart-seller-grant-integration.php`
- **THEN** 結果 SHALL 為 0

---

### Requirement: New service classes are unit-testable

新建的 CustomerQueryService 和 SellerGrantService SHALL 有對應的 PHPUnit 測試檔案。

#### Scenario: CustomerQueryService 有單元測試

- **WHEN** 執行 PHPUnit
- **THEN** tests/Unit/Services/CustomerQueryServiceTest.php SHALL 存在
- **AND** 所有測試案例 SHALL 通過

##### Example: CustomerQueryService test passes

- **GIVEN** CustomerQueryService 已建立
- **WHEN** 執行 `composer test -- --filter "CustomerQueryServiceTest"`
- **THEN** 結果為 OK，0 failures，0 errors

#### Scenario: SellerGrantService 有單元測試

- **WHEN** 執行 PHPUnit
- **THEN** tests/Unit/Services/SellerGrantServiceTest.php SHALL 存在
- **AND** 所有測試案例 SHALL 通過

##### Example: SellerGrantService test passes

- **GIVEN** SellerGrantService 已建立
- **WHEN** 執行 `composer test -- --filter "SellerGrantServiceTest"`
- **THEN** 結果為 OK，0 failures，0 errors
