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

---
### Requirement: Child orders MUST preserve target variation identity

When the system creates a child order (split order) during stock allocation, the child order's `fct_order_items.object_id` SHALL exactly match the variation_id of the parent order item being allocated, regardless of how many sibling variations exist on the same parent order.

The system SHALL NOT use `IN (variation_id, ...)` with `LIMIT 1` semantics when locating the parent order item, because this implicitly selects the lowest variation_id and produces incorrect cross-variant child orders.

#### Scenario: Allocating BCD variants on a parent order that also has variant A

- **WHEN** a parent order #1687 contains four order_items: A (qty=3), B (qty=3), C (qty=3), D (qty=2)
- **AND** the admin allocates 2 units of variant D
- **THEN** a new child order SHALL be created with `type='split'` and `parent_id=1687`
- **AND** the child order's order_item SHALL have `object_id` equal to D's variation_id
- **AND** the child order's order_item SHALL NOT have `object_id` equal to A's variation_id

##### Example: Parent #1687 with ABCD variants, allocating D=2

- **GIVEN** parent order #1687 with order_items: `[{object_id: 1038, qty: 3}, {object_id: 1039, qty: 3}, {object_id: 1040, qty: 3}, {object_id: 1041, qty: 2}]`
- **WHEN** allocate D (variation_id=1041) quantity=2
- **THEN** new child order's order_item SHALL be `{object_id: 1041, qty: 2}`

#### Scenario: Allocating a single-variation product still works

- **WHEN** a parent order #500 contains one order_item with variation_id=900 (single-variation product)
- **AND** the admin allocates 5 units
- **THEN** a child order SHALL be created with order_item `{object_id: 900, qty: 5}`


<!-- @trace
source: fix-multi-variant-allocation-cross-contamination
updated: 2026-04-30
code:
  - includes/api/class-products-api.php
  - tests/Unit/Services/AllocationCrossVariantTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-allocation-write-service.php
-->

---
### Requirement: Allocate-all for a customer MUST create independent child orders per variation

When the system runs "allocate all for customer" on a parent order containing multiple variations of the same product, the system SHALL produce one child order per variation that has remaining unallocated quantity, each carrying its own correct `object_id`.

The system SHALL NOT collapse multiple variations of the same parent order into a single allocation entry, because doing so silently overwrites earlier variations' needed quantities.

#### Scenario: One-click allocate on a 4-variation parent order

- **WHEN** parent order #1687 has four order_items A(3 needed)/B(3 needed)/C(3 needed)/D(2 needed)
- **AND** the admin clicks "allocate all" for that customer's order
- **THEN** the system SHALL create four child orders, each with a distinct `object_id` matching A/B/C/D
- **AND** each child order's order_item quantity SHALL match the corresponding variation's needed quantity
- **AND** the response SHALL report `total_allocated = 11` (3+3+3+2)

##### Example: Multi-variant allocate-all output

- **GIVEN** parent order #1687 with needs `{A: 3, B: 3, C: 3, D: 2}` and zero existing child orders
- **WHEN** allocate-all-for-customer is invoked
- **THEN** four child orders SHALL exist after the call:

| child_order | parent_id | object_id | quantity |
| ----------- | --------- | --------- | -------- |
| (id 1)      | 1687      | 1038 (A)  | 3        |
| (id 2)      | 1687      | 1039 (B)  | 3        |
| (id 3)      | 1687      | 1040 (C)  | 3        |
| (id 4)      | 1687      | 1041 (D)  | 2        |


<!-- @trace
source: fix-multi-variant-allocation-cross-contamination
updated: 2026-04-30
code:
  - includes/api/class-products-api.php
  - tests/Unit/Services/AllocationCrossVariantTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-allocation-write-service.php
-->

---
### Requirement: Allocate stock API MUST accept per-item allocations carrying object_id

The `POST /wp-json/buygo-plus-one/v1/products/{id}/allocate` endpoint SHALL accept allocation entries that include `object_id` to disambiguate variations on the same parent order.

The endpoint SHALL preserve backward compatibility: the legacy formats `{ "<order_id>": <qty> }` (object map) and `[{order_id, allocated}]` (without `object_id`) SHALL continue to work for single-variation products by automatically resolving `object_id` from the parent order's first order_item for the requested product.

The endpoint SHALL forward the `object_id` value into the AllocationService layer when present, so that downstream child-order creation uses the explicit variation.

#### Scenario: Multi-variant allocation request with explicit object_id

- **WHEN** client sends `POST /products/2650/allocate` with body `{"product_id": 2650, "allocations": [{"order_id": 1687, "object_id": 1040, "allocated": 3}, {"order_id": 1687, "object_id": 1041, "allocated": 2}]}`
- **THEN** the system SHALL create two child orders: one with object_id=1040 qty=3, one with object_id=1041 qty=2
- **AND** the API SHALL return `success: true` with two entries in `child_orders`

#### Scenario: Legacy single-variant allocation request without object_id still works

- **WHEN** client sends `POST /products/500/allocate` with body `{"product_id": 500, "allocations": {"123": 5}}`
- **AND** parent order #123 contains exactly one order_item for the product
- **THEN** the system SHALL create one child order with the resolved `object_id` from the parent order_item


<!-- @trace
source: fix-multi-variant-allocation-cross-contamination
updated: 2026-04-30
code:
  - includes/api/class-products-api.php
  - tests/Unit/Services/AllocationCrossVariantTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-allocation-write-service.php
-->

---
### Requirement: Cross-variant purchased pool validation MUST remain enforced

The total allocated quantity across all variations of the same product SHALL NOT exceed the total purchased quantity across all variations. The system SHALL keep the existing cross-variant pool semantic — purchased counts SHALL be shared across variations of the same `post_id` — while still creating per-variation child orders.

#### Scenario: Allocation rejected when total exceeds purchased pool

- **WHEN** product post_id=2650 has total purchased=11 across A/B/C/D
- **AND** existing child orders sum to 9 across all variations
- **AND** admin attempts to allocate 3 more units (total would become 12)
- **THEN** the system SHALL reject with error code `INSUFFICIENT_STOCK`
- **AND** SHALL NOT create any new child orders

##### Example: Cross-variant overflow

- **GIVEN** purchased totals `{A: 7, B: 4, C: 0, D: 0}` (sum=11), existing allocated total=9
- **WHEN** request to allocate +3 units
- **THEN** validation SHALL fail with `INSUFFICIENT_STOCK`, message includes total=12 vs purchased=11


<!-- @trace
source: fix-multi-variant-allocation-cross-contamination
updated: 2026-04-30
code:
  - includes/api/class-products-api.php
  - tests/Unit/Services/AllocationCrossVariantTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-allocation-write-service.php
-->

---
### Requirement: One-time data repair SHALL fix existing cross-variant contaminated child orders

When the data repair WP-CLI command is executed, the system SHALL identify child orders whose `object_id` does not match any order_item of the same `parent_id`, and SHALL relabel each contaminated child order's `object_id` to the most-needy variation on the parent order at execution time.

The repair SHALL run in two phases: a `--dry-run` phase that prints planned changes without writing to the database, and a `--commit` phase that performs the actual writes inside a transaction.

The repair SHALL also recompute `wp_postmeta._buygo_allocated` for affected products and supplement missing `_buygo_purchased` meta for variations that lack one (using a value provided by the operator).

#### Scenario: Dry-run prints repair plan without writing

- **WHEN** operator runs `wp buygo fix-cross-variant-child-orders --dry-run`
- **THEN** the command SHALL output a plan listing each contaminated child_order_id, its current object_id, and the proposed new object_id
- **AND** SHALL NOT modify any database row

#### Scenario: Commit applies the plan inside a transaction

- **WHEN** operator runs `wp buygo fix-cross-variant-child-orders --commit`
- **THEN** the command SHALL update each contaminated child order's order_item `object_id` to the chosen variation
- **AND** SHALL recompute `wp_postmeta._buygo_allocated` for each affected post_id
- **AND** SHALL exit with success code if all updates committed, or rollback the transaction if any update fails

##### Example: Repairing 5 contaminated child orders on post_id=2650

- **GIVEN** 5 child orders all with `object_id=1038` (variant A) but their parent orders' actual needs span B/C/D
- **WHEN** repair commit runs
- **THEN** each child order's `object_id` SHALL be updated to one of {1039, 1040, 1041} based on the most-needy variation at the time of repair
- **AND** `wp_postmeta._buygo_allocated` for post_id=2650 SHALL be recomputed from the corrected child orders

<!-- @trace
source: fix-multi-variant-allocation-cross-contamination
updated: 2026-04-30
code:
  - includes/api/class-products-api.php
  - tests/Unit/Services/AllocationCrossVariantTest.php
  - includes/services/class-allocation-batch-service.php
  - includes/services/class-allocation-write-service.php
-->