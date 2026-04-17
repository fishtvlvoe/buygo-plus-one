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
source: fix-product-list-variation-init
updated: 2026-04-17
code:
  - tests/bootstrap-unit.php
  - tests/bootstrap.php
  - tests/Unit/Services/OrderItemServiceTest.php
  - includes/api/class-order-items-api.php
  - includes/api/class-api.php
  - includes/services/class-order-item-service.php
  - tests/Unit/Services/OrderFormatterChildOrderIdTest.php
  - components/order/order-detail-modal.php
  - buygo-plus-one.php
  - includes/services/class-order-formatter.php
-->