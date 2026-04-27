# allocation-variation-filter Specification

## Purpose

Define allocation page behavior for variation-filtered statistics and order demand calculation during stock allocation.

## Requirements

### Requirement: Allocation page stats reflect selected variation filter

When the admin views the allocation page, the statistics panel (ordered quantity, purchased, allocatable, allocated) SHALL update to reflect the currently selected variation filter.
When "All" is selected, the system SHALL display aggregate totals across all variations.
When a specific variation is selected, the system SHALL display stats for that variation only.

#### Scenario: All variations selected shows aggregate stats

- **WHEN** admin is on the allocation page
- **AND** the variation filter is set to "All" (empty value)
- **THEN** the "ordered" stat SHALL show total ordered quantity across all variations
- **AND** the "purchased" stat SHALL show total purchased quantity across all variations
- **AND** the "allocatable" stat SHALL equal total purchased minus total allocated across all variations
- **AND** the "allocated" stat SHALL show total allocated quantity across all variations

#### Scenario: Specific variation selected shows variation-only stats

- **WHEN** admin selects a specific variation (e.g. "1號") from the filter dropdown
- **THEN** the "ordered" stat SHALL show the ordered quantity for that variation only (sum of item quantities, not order count)
- **AND** the "purchased" stat SHALL show the purchased quantity for that variation only
- **AND** the "allocatable" stat SHALL equal that variation's purchased minus allocated
- **AND** the "allocated" stat SHALL show the allocated quantity for that variation only

#### Scenario: Switching variation filter updates stats immediately

- **WHEN** admin switches the variation filter from one variation to another
- **THEN** the stats panel SHALL update to reflect the newly selected variation
- **AND** during the API fetch, the stats SHALL be cleared to zero to avoid showing stale data

#### Scenario: Switching back to All restores aggregate stats

- **WHEN** admin switches the variation filter back to "All"
- **THEN** the stats panel SHALL revert to showing the aggregate totals from `selectedProduct`
- **AND** no additional API call SHALL be required (data already loaded)

#### Scenario: Order list filter remains independent

- **WHEN** admin changes the variation filter
- **THEN** the order list below SHALL continue to filter correctly by variation (existing behavior)
- **AND** the order list filter SHALL not be affected by the stats panel changes

<!-- @trace
source: fix-allocation-variation-filter
updated: 2026-04-17
code:
  - tests/Unit/Services/OrderItemServiceTest.php
  - components/order/order-detail-modal.php
  - includes/api/class-order-items-api.php
  - tests/Unit/Services/OrderFormatterChildOrderIdTest.php
  - tests/bootstrap-unit.php
  - tests/bootstrap.php
  - includes/services/class-order-formatter.php
  - buygo-plus-one.php
  - includes/api/class-api.php
  - includes/services/class-order-item-service.php
-->

---
### Requirement: Allocation demand quantity uses child order actual quantity

When validating allocation limits for a child order (type='split'), the system SHALL use the child order's own `fct_order_items.quantity` as the demand quantity, NOT the parent order's original line item quantity.

The demand quantity for an order item SHALL be determined as follows:
- For child orders (parent_id IS NOT NULL): the quantity from the child order's own `fct_order_items` record
- For parent orders (parent_id IS NULL): the quantity from the parent order's `fct_order_items` record

The system SHALL NOT produce a "demand exceeded" warning when the allocated quantity is within the child order's actual demand.

#### Scenario: Child order demand reflects its own quantity

- **WHEN** a parent order with quantity 5 is split into child orders
- **AND** child order #A has quantity 3 for a specific variation
- **THEN** the demand quantity for child order #A SHALL be 3
- **AND** allocating 3 units to child order #A SHALL NOT trigger a "demand exceeded" warning

##### Example: Split order allocation validation

| Order | Type | Item Quantity | Allocate | Expected Warning |
|-------|------|--------------|----------|-----------------|
| #1420 | split (child) | 3 | 3 | none |
| #1420 | split (child) | 3 | 4 | "exceeds demand" |
| #1000 | parent | 5 | 5 | none |
| #1000 | parent | 5 | 6 | "exceeds demand" |

#### Scenario: Multiple child orders from same parent each have independent demand

- **WHEN** a parent order with quantity 5 is split into three child orders
- **AND** child order #A has quantity 1, #B has quantity 1, #C has quantity 3
- **THEN** each child order's demand SHALL be evaluated independently
- **AND** allocating 3 units to child order #C SHALL NOT trigger a warning
- **AND** the sum of child order quantities (1+1+3=5) SHALL equal the parent order quantity

##### Example: Independent demand validation per child

- **GIVEN** parent order #100 with item quantity 5
- **AND** child order #101 (quantity=1), #102 (quantity=1), #103 (quantity=3)
- **WHEN** admin allocates 1 to #101, 1 to #102, 3 to #103
- **THEN** no "demand exceeded" warning SHALL appear for any child order

#### Scenario: API returns correct demand for child orders in allocation list

- **WHEN** the allocation page API returns pending orders for a product
- **THEN** each order item's demand quantity SHALL reflect its own order's quantity
- **AND** child orders SHALL NOT inherit or reference the parent order's quantity for demand calculation

##### Example: API response with mixed parent and child orders

- **GIVEN** product variation ID 966
- **AND** parent order #1400 (quantity=5) split into child orders #1425 (quantity=1), #1422 (quantity=1), #1420 (quantity=3)
- **WHEN** admin opens allocation page for variation 966
- **THEN** API response SHALL contain:

| order_id | type | quantity (demand) | allocated |
|----------|------|-------------------|-----------|
| #1425 | split | 1 | 0 |
| #1422 | split | 1 | 1 |
| #1420 | split | 3 | 0 |

<!-- @trace
source: fix-allocation-demand-calculation
updated: 2026-04-27
code:
  - includes/services/class-allocation-service.php
  - includes/api/class-products-api.php
  - tests/Unit/Services/AllocationDemandCalculationTest.php
-->

<!-- @trace
source: fix-allocation-demand-calculation
updated: 2026-04-28
code:
  - .github/skills/spectra-archive/SKILL.md
  - .github/skills/spectra-apply/SKILL.md
  - includes/services/class-allocation-calculator.php
  - includes/services/class-product-write-service.php
  - .github/prompts/spectra-commit.prompt.md
  - includes/integrations/class-fluentcart-seller-grant-integration.php
  - tests/Unit/Services/AllocationServiceTest.php
  - .github/prompts/spectra-archive.prompt.md
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - line-buygo-logo.png
  - GEMINI.md
  - .github/prompts/spectra-debug.prompt.md
  - includes/services/class-allocation-service.php
  - tests/Unit/Services/SellerGrantServiceTest.php
  - .github/prompts/spectra-discuss.prompt.md
  - includes/services/class-seller-grant-service.php
  - .github/prompts/spectra-audit.prompt.md
  - includes/services/class-order-service.php
  - includes/services/class-customer-query-service.php
  - includes/services/class-product-query-service.php
  - .github/skills/spectra-propose/SKILL.md
  - includes/api/class-orders-api.php
  - tests/Unit/Services/ProductVariationServiceTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-product-service.php
  - tests/Unit/Services/AllocationBatchPerformanceTest.php
  - includes/services/class-allocation-query-service.php
  - tests/Unit/Services/CustomerQueryServiceTest.php
  - includes/services/class-product-buyer-query-service.php
  - .github/prompts/spectra-ingest.prompt.md
  - tests/Unit/Services/ProductQueryServiceTest.php
  - includes/services/class-allocation-write-service.php
  - .spectra.yaml
  - AGENTS.md.before-zerospec-20260427
  - .github/prompts/spectra-apply.prompt.md
  - tests/Unit/Services/AllocationQueryServiceTest.php
  - tests/bootstrap-unit.php
  - includes/services/class-product-variation-service.php
  - includes/api/class-customers-api.php
  - .github/skills/spectra-commit/SKILL.md
  - .github/skills/spectra-discuss/SKILL.md
  - CLAUDE.md
  - includes/api/class-products-api.php
  - tests/Unit/Services/OrderServiceTest.php
  - includes/class-database.php
  - includes/services/class-product-catalog-query-service.php
  - tests/Unit/Services/AllocationDemandCalculationTest.php
  - tests/Unit/Services/AllocationCalculatorTest.php
  - .cursorrules
  - .github/prompts/spectra-propose.prompt.md
  - .github/prompts/spectra-ask.prompt.md
  - tests/Unit/Services/ProductWriteServiceTest.php
-->