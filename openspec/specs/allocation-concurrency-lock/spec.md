# allocation-concurrency-lock Specification

## Purpose

TBD - created by archiving change 'debug-allocation-quantity-mismatch'. Update Purpose after archive.

## Requirements

### Requirement: Allocation operations use exclusive lock per product

The system SHALL acquire a MySQL named lock (`GET_LOCK('buygo_allocate_{product_id}', 10)`) before executing any allocation write operation in `AllocationWriteService::updateOrderAllocations()`. The lock SHALL be released via `RELEASE_LOCK()` after the operation completes (success or failure). If the lock cannot be acquired within 10 seconds, the system SHALL return a WP_Error with code `allocation_locked` and a human-readable message.

#### Scenario: Concurrent allocation requests for the same product

- **WHEN** two allocation requests for the same product_id arrive simultaneously
- **THEN** the first request acquires the lock and proceeds; the second request waits until the first completes, then proceeds with up-to-date data

##### Example: Double-click allocation prevention

- **GIVEN** product_id=100 with 5 units purchased, 0 allocated; order_item for customer A has quantity=3
- **WHEN** two identical POST /products/allocate requests arrive within 100ms, each requesting 3 units for customer A
- **THEN** the first request creates a child order with quantity=3 (allocated becomes 3); the second request sees allocated=3, validates 3+3=6 > 5 purchased, and returns validation error

#### Scenario: Lock timeout

- **WHEN** an allocation request cannot acquire the lock within 10 seconds
- **THEN** the system returns WP_Error with code `allocation_locked` and message indicating the product is currently being processed

#### Scenario: Lock does not block different products

- **WHEN** allocation requests for product_id=100 and product_id=200 arrive simultaneously
- **THEN** both requests proceed independently without waiting for each other


<!-- @trace
source: debug-allocation-quantity-mismatch
updated: 2026-05-06
code:
  - includes/services/class-order-service.php
  - tests/Unit/Services/SplitOrderTransactionTest.php
  - includes/services/class-allocation-batch-service.php
  - tests/Unit/Services/CancelSpellingFilterTest.php
  - tests/bootstrap-unit.php
  - includes/services/class-product-stats-calculator.php
  - tests/Unit/Services/AllocationIntegrationTest.php
  - includes/services/class-allocation-query-service.php
  - tests/Unit/Services/AllocationLockTest.php
  - tests/Unit/Services/AllocationServiceTest.php
  - includes/services/class-allocation-calculator.php
  - tests/Unit/Services/ShipOrderMetaSyncTest.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - includes/services/class-allocation-write-service.php
  - tests/Unit/Services/AllocationCrossVariantTest.php
  - includes/services/class-shipment-service.php
-->

---
### Requirement: Legacy object_id=0 rejected for multi-variation products

The system SHALL reject allocation requests where `object_id=0` when the target product has `has_variations=true` and more than one variation. The system SHALL return a WP_Error with code `variation_required` indicating that a specific variation_id must be provided. Products with `has_variations=false` or exactly one variation SHALL continue to accept `object_id=0` with fallback to the single/default variation.

#### Scenario: Multi-variation product with object_id=0

- **WHEN** an allocation request specifies object_id=0 for a product with 4 variations (A, B, C, D)
- **THEN** the system returns WP_Error with code `variation_required`

#### Scenario: Single-variation product with object_id=0

- **WHEN** an allocation request specifies object_id=0 for a product with exactly 1 variation
- **THEN** the system resolves object_id to the single variation and proceeds normally

<!-- @trace
source: debug-allocation-quantity-mismatch
updated: 2026-05-06
code:
  - includes/services/class-order-service.php
  - tests/Unit/Services/SplitOrderTransactionTest.php
  - includes/services/class-allocation-batch-service.php
  - tests/Unit/Services/CancelSpellingFilterTest.php
  - tests/bootstrap-unit.php
  - includes/services/class-product-stats-calculator.php
  - tests/Unit/Services/AllocationIntegrationTest.php
  - includes/services/class-allocation-query-service.php
  - tests/Unit/Services/AllocationLockTest.php
  - tests/Unit/Services/AllocationServiceTest.php
  - includes/services/class-allocation-calculator.php
  - tests/Unit/Services/ShipOrderMetaSyncTest.php
  - tests/Unit/Services/AllocationWriteServiceTest.php
  - includes/services/class-allocation-write-service.php
  - tests/Unit/Services/AllocationCrossVariantTest.php
  - includes/services/class-shipment-service.php
-->