# product-variation-stats-display Specification

## Purpose

TBD - created by archiving change 'fix-product-list-variation-init'. Update Purpose after archive.

## Requirements

### Requirement: Product list initializes variation stats on load

When the product list page loads, the system SHALL display statistics (ordered, purchased, allocated, shipped) for the default selected variation of each multi-variation product, not the aggregate total across all variations.
The system SHALL fetch per-variation statistics via the existing `/variations/{id}/stats` API for all products with `has_variations = true` immediately after the product list is rendered.
The system SHALL use `Promise.all()` to fetch variation stats in parallel, not sequentially.

#### Scenario: Multi-variation product shows correct stats on initial load

- **WHEN** the product list page loads
- **AND** a product has `has_variations = true` with a `default_variation`
- **THEN** the displayed ordered/purchased/allocated/shipped numbers SHALL match the stats of the `default_variation`, not the aggregate total
- **AND** no manual variation switching SHALL be required to see correct numbers

#### Scenario: Single-variation product stats unaffected

- **WHEN** the product list page loads
- **AND** a product has `has_variations = false`
- **THEN** the product stats display SHALL remain unchanged

#### Scenario: Variation stats API failure does not break page load

- **WHEN** the product list page loads
- **AND** one or more `/variations/{id}/stats` API calls fail
- **THEN** the product list SHALL still render
- **AND** the failed product SHALL show the fallback aggregate stats (original behavior)
- **AND** other products' stats SHALL load normally (one failure SHALL NOT block others)


<!-- @trace
source: fix-order-quantity-and-fluentcart-delete
updated: 2026-04-17
code:
  - tests/Unit/Services/ProductServiceDeletePostTest.php
  - docs/bug/截圖 2026-04-17 中午12.47.23.png
  - includes/api/class-products-api.php
  - docs/bug/截圖 2026-04-17 中午12.47.36.png
  - includes/services/class-product-service.php
  - includes/views/composables/useProducts.js
  - tests/bootstrap-unit.php
-->

---
### Requirement: Allocation page ordered count matches buyers page ordered count

The system SHALL display the same "ordered" quantity on both the allocation page and the buyers list page for the same product.
When no variant filter is selected (showing all), the allocation page "ordered" stat SHALL be computed by summing the `quantity` field of all entries in `productOrders` (the live order list already loaded for the allocation page), NOT from the cached `selectedProduct.ordered` value.
The allocation page "allocated" stat SHALL similarly be computed by summing `allocated_quantity` (or `allocated`) from `productOrders`.

#### Scenario: Allocation page ordered count equals buyers page ordered count

- **WHEN** a seller navigates from the product list to the allocation page for a product
- **AND** no variant filter is selected
- **THEN** the "ordered" number shown in the allocation page header SHALL equal the sum of all order quantities shown in the allocation order list
- **AND** the "ordered" number SHALL equal what the buyers list page shows as total quantity for the same product

#### Scenario: Allocation page ordered count reflects new orders without page reload

- **WHEN** a new order is placed for a product
- **AND** the seller opens the allocation page for that product
- **THEN** the allocation page "ordered" count SHALL reflect the new order
- **AND** the count SHALL NOT be stale from a previous product list load

#### Scenario: Variant-filtered allocation stats remain unchanged

- **WHEN** a seller selects a specific variant on the allocation page
- **THEN** the stats SHALL be fetched from `/variations/{id}/stats` as before
- **AND** the behavior for variant-specific stats SHALL be unchanged

<!-- @trace
source: fix-order-quantity-and-fluentcart-delete
updated: 2026-04-17
code:
  - tests/Unit/Services/ProductServiceDeletePostTest.php
  - docs/bug/截圖 2026-04-17 中午12.47.23.png
  - includes/api/class-products-api.php
  - docs/bug/截圖 2026-04-17 中午12.47.36.png
  - includes/services/class-product-service.php
  - includes/views/composables/useProducts.js
  - tests/bootstrap-unit.php
-->