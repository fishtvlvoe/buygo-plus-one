# service-size-compliance Specification

## Purpose

Define how oversized service classes are split into smaller services that stay within the project size limit while preserving facade compatibility and existing test-suite behavior.

## Requirements

### Requirement: AllocationService child classes must not exceed 300 lines

After splitting AllocationService, each resulting Service class SHALL contain no more than 300 lines of PHP code (as measured by `wc -l`).

#### Scenario: AllocationQueryService line count within limit

- **WHEN** `wc -l includes/services/class-allocation-query-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

#### Scenario: AllocationWriteService line count within limit

- **WHEN** `wc -l includes/services/class-allocation-write-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

#### Scenario: AllocationCalculator line count within limit

- **WHEN** `wc -l includes/services/class-allocation-calculator.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - includes/services/class-allocation-query-service.php
  - includes/services/class-allocation-write-service.php
  - includes/services/class-allocation-calculator.php
-->


<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - .github/prompts/spectra-debug.prompt.md
  - tests/Unit/Services/AllocationCalculatorTest.php
  - .github/prompts/spectra-propose.prompt.md
  - .cursorrules
  - tests/Unit/Services/ProductWriteServiceTest.php
  - .spectra.yaml
  - AGENTS.md.before-zerospec-20260427
  - tests/Unit/Services/ProductQueryServiceTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/api/class-customers-api.php
  - includes/services/class-allocation-service.php
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-ingest.prompt.md
  - .github/prompts/spectra-apply.prompt.md
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/skills/spectra-apply/SKILL.md
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - .github/prompts/spectra-commit.prompt.md
  - includes/services/class-allocation-calculator.php
  - includes/services/class-product-catalog-query-service.php
  - includes/api/class-products-api.php
  - GEMINI.md
  - includes/services/class-product-buyer-query-service.php
  - includes/services/class-allocation-write-service.php
  - .github/skills/spectra-archive/SKILL.md
  - CLAUDE.md
  - .github/skills/spectra-propose/SKILL.md
  - includes/api/class-orders-api.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - includes/services/class-customer-query-service.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - .github/skills/spectra-commit/SKILL.md
  - includes/services/class-product-variation-service.php
  - .github/prompts/spectra-archive.prompt.md
  - includes/class-database.php
  - includes/services/class-order-service.php
  - includes/services/class-product-write-service.php
  - .github/prompts/spectra-ask.prompt.md
  - tests/Unit/Services/ProductVariationServiceTest.php
  - .github/prompts/spectra-audit.prompt.md
  - line-buygo-logo.png
  - includes/services/class-allocation-query-service.php
  - tests/Unit/Services/OrderServiceTest.php
  - includes/services/class-product-query-service.php
  - .github/skills/spectra-discuss/SKILL.md
  - .github/prompts/spectra-discuss.prompt.md
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-product-service.php
  - tests/bootstrap-unit.php
  - tests/Unit/Services/SellerGrantServiceTest.php
-->

---
### Requirement: ProductService child classes must not exceed 300 lines

After splitting ProductService, each resulting Service class SHALL contain no more than 300 lines of PHP code (as measured by `wc -l`).

#### Scenario: ProductQueryService line count within limit

- **WHEN** `wc -l includes/services/class-product-query-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

#### Scenario: ProductWriteService line count within limit

- **WHEN** `wc -l includes/services/class-product-write-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

#### Scenario: ProductVariationService line count within limit

- **WHEN** `wc -l includes/services/class-product-variation-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - includes/services/class-product-query-service.php
  - includes/services/class-product-write-service.php
  - includes/services/class-product-variation-service.php
-->


<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - .github/prompts/spectra-debug.prompt.md
  - tests/Unit/Services/AllocationCalculatorTest.php
  - .github/prompts/spectra-propose.prompt.md
  - .cursorrules
  - tests/Unit/Services/ProductWriteServiceTest.php
  - .spectra.yaml
  - AGENTS.md.before-zerospec-20260427
  - tests/Unit/Services/ProductQueryServiceTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/api/class-customers-api.php
  - includes/services/class-allocation-service.php
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-ingest.prompt.md
  - .github/prompts/spectra-apply.prompt.md
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/skills/spectra-apply/SKILL.md
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - .github/prompts/spectra-commit.prompt.md
  - includes/services/class-allocation-calculator.php
  - includes/services/class-product-catalog-query-service.php
  - includes/api/class-products-api.php
  - GEMINI.md
  - includes/services/class-product-buyer-query-service.php
  - includes/services/class-allocation-write-service.php
  - .github/skills/spectra-archive/SKILL.md
  - CLAUDE.md
  - .github/skills/spectra-propose/SKILL.md
  - includes/api/class-orders-api.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - includes/services/class-customer-query-service.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - .github/skills/spectra-commit/SKILL.md
  - includes/services/class-product-variation-service.php
  - .github/prompts/spectra-archive.prompt.md
  - includes/class-database.php
  - includes/services/class-order-service.php
  - includes/services/class-product-write-service.php
  - .github/prompts/spectra-ask.prompt.md
  - tests/Unit/Services/ProductVariationServiceTest.php
  - .github/prompts/spectra-audit.prompt.md
  - line-buygo-logo.png
  - includes/services/class-allocation-query-service.php
  - tests/Unit/Services/OrderServiceTest.php
  - includes/services/class-product-query-service.php
  - .github/skills/spectra-discuss/SKILL.md
  - .github/prompts/spectra-discuss.prompt.md
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-product-service.php
  - tests/bootstrap-unit.php
  - tests/Unit/Services/SellerGrantServiceTest.php
-->

---
### Requirement: Facade preserves backward compatibility for AllocationService callers

The original AllocationService class SHALL remain instantiable and SHALL delegate all public method calls to the appropriate child Service without changing method signatures or return types.

#### Scenario: Facade method delegation succeeds

- **WHEN** a caller invokes any public method on AllocationService (e.g. `getProductOrders`, `updateOrderAllocations`, `validateAdjustment`, `adjustAllocation`, `cancelChildOrder`)
- **THEN** the facade SHALL delegate to the corresponding child Service and return an identical result

##### Example: getProductOrders delegation

- **GIVEN** AllocationService facade delegates `getProductOrders()` to AllocationQueryService
- **WHEN** `$allocationService->getProductOrders($productId)` is called
- **THEN** the return value SHALL be identical to calling `$allocationQueryService->getProductOrders($productId)` directly

<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - includes/services/class-allocation-service.php
  - includes/services/class-allocation-query-service.php
  - includes/services/class-allocation-write-service.php
  - includes/services/class-allocation-calculator.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - tests/Unit/Services/AllocationCalculatorTest.php
-->


<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - .github/prompts/spectra-debug.prompt.md
  - tests/Unit/Services/AllocationCalculatorTest.php
  - .github/prompts/spectra-propose.prompt.md
  - .cursorrules
  - tests/Unit/Services/ProductWriteServiceTest.php
  - .spectra.yaml
  - AGENTS.md.before-zerospec-20260427
  - tests/Unit/Services/ProductQueryServiceTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/api/class-customers-api.php
  - includes/services/class-allocation-service.php
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-ingest.prompt.md
  - .github/prompts/spectra-apply.prompt.md
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/skills/spectra-apply/SKILL.md
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - .github/prompts/spectra-commit.prompt.md
  - includes/services/class-allocation-calculator.php
  - includes/services/class-product-catalog-query-service.php
  - includes/api/class-products-api.php
  - GEMINI.md
  - includes/services/class-product-buyer-query-service.php
  - includes/services/class-allocation-write-service.php
  - .github/skills/spectra-archive/SKILL.md
  - CLAUDE.md
  - .github/skills/spectra-propose/SKILL.md
  - includes/api/class-orders-api.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - includes/services/class-customer-query-service.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - .github/skills/spectra-commit/SKILL.md
  - includes/services/class-product-variation-service.php
  - .github/prompts/spectra-archive.prompt.md
  - includes/class-database.php
  - includes/services/class-order-service.php
  - includes/services/class-product-write-service.php
  - .github/prompts/spectra-ask.prompt.md
  - tests/Unit/Services/ProductVariationServiceTest.php
  - .github/prompts/spectra-audit.prompt.md
  - line-buygo-logo.png
  - includes/services/class-allocation-query-service.php
  - tests/Unit/Services/OrderServiceTest.php
  - includes/services/class-product-query-service.php
  - .github/skills/spectra-discuss/SKILL.md
  - .github/prompts/spectra-discuss.prompt.md
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-product-service.php
  - tests/bootstrap-unit.php
  - tests/Unit/Services/SellerGrantServiceTest.php
-->

---
### Requirement: Facade preserves backward compatibility for ProductService callers

The original ProductService class SHALL remain instantiable and SHALL delegate all public method calls to the appropriate child Service without changing method signatures or return types.

#### Scenario: Facade method delegation succeeds

- **WHEN** a caller invokes any public method on ProductService (e.g. `getProductsWithOrderCount`, `updateProduct`, `getVariations`, `getVariationStats`)
- **THEN** the facade SHALL delegate to the corresponding child Service and return an identical result

<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - includes/services/class-product-service.php
  - includes/services/class-product-query-service.php
  - includes/services/class-product-write-service.php
  - includes/services/class-product-variation-service.php
  - tests/Unit/Services/ProductQueryServiceTest.php
  - tests/Unit/Services/ProductWriteServiceTest.php
  - tests/Unit/Services/ProductVariationServiceTest.php
-->


<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - .github/prompts/spectra-debug.prompt.md
  - tests/Unit/Services/AllocationCalculatorTest.php
  - .github/prompts/spectra-propose.prompt.md
  - .cursorrules
  - tests/Unit/Services/ProductWriteServiceTest.php
  - .spectra.yaml
  - AGENTS.md.before-zerospec-20260427
  - tests/Unit/Services/ProductQueryServiceTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/api/class-customers-api.php
  - includes/services/class-allocation-service.php
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-ingest.prompt.md
  - .github/prompts/spectra-apply.prompt.md
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/skills/spectra-apply/SKILL.md
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - .github/prompts/spectra-commit.prompt.md
  - includes/services/class-allocation-calculator.php
  - includes/services/class-product-catalog-query-service.php
  - includes/api/class-products-api.php
  - GEMINI.md
  - includes/services/class-product-buyer-query-service.php
  - includes/services/class-allocation-write-service.php
  - .github/skills/spectra-archive/SKILL.md
  - CLAUDE.md
  - .github/skills/spectra-propose/SKILL.md
  - includes/api/class-orders-api.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - includes/services/class-customer-query-service.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - .github/skills/spectra-commit/SKILL.md
  - includes/services/class-product-variation-service.php
  - .github/prompts/spectra-archive.prompt.md
  - includes/class-database.php
  - includes/services/class-order-service.php
  - includes/services/class-product-write-service.php
  - .github/prompts/spectra-ask.prompt.md
  - tests/Unit/Services/ProductVariationServiceTest.php
  - .github/prompts/spectra-audit.prompt.md
  - line-buygo-logo.png
  - includes/services/class-allocation-query-service.php
  - tests/Unit/Services/OrderServiceTest.php
  - includes/services/class-product-query-service.php
  - .github/skills/spectra-discuss/SKILL.md
  - .github/prompts/spectra-discuss.prompt.md
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-product-service.php
  - tests/bootstrap-unit.php
  - tests/Unit/Services/SellerGrantServiceTest.php
-->

---
### Requirement: All existing tests must pass after refactoring

The full PHPUnit test suite SHALL pass with zero failures after the split and facade introduction.

#### Scenario: Composer test suite passes

- **WHEN** `composer test` is executed from the project root
- **THEN** all test cases SHALL pass with exit code 0 and zero failures reported

<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - composer.json
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - tests/Unit/Services/AllocationCalculatorTest.php
  - tests/Unit/Services/ProductQueryServiceTest.php
  - tests/Unit/Services/ProductWriteServiceTest.php
  - tests/Unit/Services/ProductVariationServiceTest.php
-->

<!-- @trace
source: split-oversized-services
updated: 2026-04-27
code:
  - .github/prompts/spectra-debug.prompt.md
  - tests/Unit/Services/AllocationCalculatorTest.php
  - .github/prompts/spectra-propose.prompt.md
  - .cursorrules
  - tests/Unit/Services/ProductWriteServiceTest.php
  - .spectra.yaml
  - AGENTS.md.before-zerospec-20260427
  - tests/Unit/Services/ProductQueryServiceTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/api/class-customers-api.php
  - includes/services/class-allocation-service.php
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-ingest.prompt.md
  - .github/prompts/spectra-apply.prompt.md
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/skills/spectra-apply/SKILL.md
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - .github/prompts/spectra-commit.prompt.md
  - includes/services/class-allocation-calculator.php
  - includes/services/class-product-catalog-query-service.php
  - includes/api/class-products-api.php
  - GEMINI.md
  - includes/services/class-product-buyer-query-service.php
  - includes/services/class-allocation-write-service.php
  - .github/skills/spectra-archive/SKILL.md
  - CLAUDE.md
  - .github/skills/spectra-propose/SKILL.md
  - includes/api/class-orders-api.php
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - includes/services/class-customer-query-service.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - .github/skills/spectra-commit/SKILL.md
  - includes/services/class-product-variation-service.php
  - .github/prompts/spectra-archive.prompt.md
  - includes/class-database.php
  - includes/services/class-order-service.php
  - includes/services/class-product-write-service.php
  - .github/prompts/spectra-ask.prompt.md
  - tests/Unit/Services/ProductVariationServiceTest.php
  - .github/prompts/spectra-audit.prompt.md
  - line-buygo-logo.png
  - includes/services/class-allocation-query-service.php
  - tests/Unit/Services/OrderServiceTest.php
  - includes/services/class-product-query-service.php
  - .github/skills/spectra-discuss/SKILL.md
  - .github/prompts/spectra-discuss.prompt.md
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - includes/services/class-product-service.php
  - tests/bootstrap-unit.php
  - tests/Unit/Services/SellerGrantServiceTest.php
-->