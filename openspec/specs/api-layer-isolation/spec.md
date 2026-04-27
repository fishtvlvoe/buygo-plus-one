# api-layer-isolation Specification

## Purpose

定義 API 層與 Integration 層的隔離邊界：handlers 只負責驗證、路由與橋接，所有資料存取與商業邏輯都委派到 Service layer，以維持可測試性與分層一致性。

## Requirements

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

<!-- @trace
source: migrate-api-business-logic
updated: 2026-04-27
code:
  - includes/api/class-customers-api.php
  - includes/api/class-orders-api.php
  - includes/api/class-products-api.php
  - includes/services/class-customer-query-service.php
  - includes/services/class-order-service.php
  - includes/services/class-allocation-service.php
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - tests/Unit/Services/OrderServiceTest.php
  - tests/Unit/Services/AllocationServiceTest.php
-->


<!-- @trace
source: migrate-api-business-logic
updated: 2026-04-28
code:
  - includes/services/class-order-service.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - CLAUDE.md
  - includes/services/class-customer-query-service.php
  - includes/api/class-customers-api.php
  - includes/api/class-products-api.php
  - includes/services/class-allocation-query-service.php
  - .github/skills/spectra-discuss/SKILL.md
  - tests/Unit/Services/ProductVariationServiceTest.php
  - .spectra.yaml
  - tests/Unit/Services/ProductQueryServiceTest.php
  - tests/bootstrap-unit.php
  - line-buygo-logo.png
  - .github/prompts/spectra-apply.prompt.md
  - includes/services/class-allocation-service.php
  - .github/prompts/spectra-debug.prompt.md
  - GEMINI.md
  - .github/prompts/spectra-audit.prompt.md
  - .github/prompts/spectra-ingest.prompt.md
  - tests/Unit/Services/AllocationCalculatorTest.php
  - includes/services/class-product-write-service.php
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - .github/prompts/spectra-archive.prompt.md
  - AGENTS.md.before-zerospec-20260427
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-product-buyer-query-service.php
  - tests/Unit/Services/SellerGrantServiceTest.php
  - includes/class-database.php
  - .github/skills/spectra-commit/SKILL.md
  - includes/services/class-product-query-service.php
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - includes/services/class-allocation-calculator.php
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-product-catalog-query-service.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/prompts/spectra-propose.prompt.md
  - .github/skills/spectra-archive/SKILL.md
  - tests/Unit/Services/OrderServiceTest.php
  - includes/api/class-orders-api.php
  - .github/prompts/spectra-ask.prompt.md
  - .github/skills/spectra-apply/SKILL.md
  - includes/services/class-product-service.php
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-discuss.prompt.md
  - .cursorrules
  - .github/skills/spectra-propose/SKILL.md
  - includes/services/class-allocation-write-service.php
  - includes/services/class-product-variation-service.php
  - tests/Unit/Services/ProductWriteServiceTest.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - .github/prompts/spectra-commit.prompt.md
-->

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

<!-- @trace
source: migrate-api-business-logic
updated: 2026-04-27
code:
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-seller-grant-service.php
  - tests/Unit/Services/SellerGrantServiceTest.php
-->


<!-- @trace
source: migrate-api-business-logic
updated: 2026-04-28
code:
  - includes/services/class-order-service.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - CLAUDE.md
  - includes/services/class-customer-query-service.php
  - includes/api/class-customers-api.php
  - includes/api/class-products-api.php
  - includes/services/class-allocation-query-service.php
  - .github/skills/spectra-discuss/SKILL.md
  - tests/Unit/Services/ProductVariationServiceTest.php
  - .spectra.yaml
  - tests/Unit/Services/ProductQueryServiceTest.php
  - tests/bootstrap-unit.php
  - line-buygo-logo.png
  - .github/prompts/spectra-apply.prompt.md
  - includes/services/class-allocation-service.php
  - .github/prompts/spectra-debug.prompt.md
  - GEMINI.md
  - .github/prompts/spectra-audit.prompt.md
  - .github/prompts/spectra-ingest.prompt.md
  - tests/Unit/Services/AllocationCalculatorTest.php
  - includes/services/class-product-write-service.php
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - .github/prompts/spectra-archive.prompt.md
  - AGENTS.md.before-zerospec-20260427
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-product-buyer-query-service.php
  - tests/Unit/Services/SellerGrantServiceTest.php
  - includes/class-database.php
  - .github/skills/spectra-commit/SKILL.md
  - includes/services/class-product-query-service.php
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - includes/services/class-allocation-calculator.php
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-product-catalog-query-service.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/prompts/spectra-propose.prompt.md
  - .github/skills/spectra-archive/SKILL.md
  - tests/Unit/Services/OrderServiceTest.php
  - includes/api/class-orders-api.php
  - .github/prompts/spectra-ask.prompt.md
  - .github/skills/spectra-apply/SKILL.md
  - includes/services/class-product-service.php
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-discuss.prompt.md
  - .cursorrules
  - .github/skills/spectra-propose/SKILL.md
  - includes/services/class-allocation-write-service.php
  - includes/services/class-product-variation-service.php
  - tests/Unit/Services/ProductWriteServiceTest.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - .github/prompts/spectra-commit.prompt.md
-->

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

<!-- @trace
source: migrate-api-business-logic
updated: 2026-04-27
code:
  - includes/services/class-customer-query-service.php
  - includes/services/class-seller-grant-service.php
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - tests/Unit/Services/SellerGrantServiceTest.php
-->

<!-- @trace
source: migrate-api-business-logic
updated: 2026-04-28
code:
  - includes/services/class-order-service.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - CLAUDE.md
  - includes/services/class-customer-query-service.php
  - includes/api/class-customers-api.php
  - includes/api/class-products-api.php
  - includes/services/class-allocation-query-service.php
  - .github/skills/spectra-discuss/SKILL.md
  - tests/Unit/Services/ProductVariationServiceTest.php
  - .spectra.yaml
  - tests/Unit/Services/ProductQueryServiceTest.php
  - tests/bootstrap-unit.php
  - line-buygo-logo.png
  - .github/prompts/spectra-apply.prompt.md
  - includes/services/class-allocation-service.php
  - .github/prompts/spectra-debug.prompt.md
  - GEMINI.md
  - .github/prompts/spectra-audit.prompt.md
  - .github/prompts/spectra-ingest.prompt.md
  - tests/Unit/Services/AllocationCalculatorTest.php
  - includes/services/class-product-write-service.php
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - .github/prompts/spectra-archive.prompt.md
  - AGENTS.md.before-zerospec-20260427
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-product-buyer-query-service.php
  - tests/Unit/Services/SellerGrantServiceTest.php
  - includes/class-database.php
  - .github/skills/spectra-commit/SKILL.md
  - includes/services/class-product-query-service.php
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - includes/services/class-allocation-calculator.php
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-product-catalog-query-service.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/prompts/spectra-propose.prompt.md
  - .github/skills/spectra-archive/SKILL.md
  - tests/Unit/Services/OrderServiceTest.php
  - includes/api/class-orders-api.php
  - .github/prompts/spectra-ask.prompt.md
  - .github/skills/spectra-apply/SKILL.md
  - includes/services/class-product-service.php
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-discuss.prompt.md
  - .cursorrules
  - .github/skills/spectra-propose/SKILL.md
  - includes/services/class-allocation-write-service.php
  - includes/services/class-product-variation-service.php
  - tests/Unit/Services/ProductWriteServiceTest.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - .github/prompts/spectra-commit.prompt.md
-->