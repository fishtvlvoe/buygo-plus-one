---
phase: 43-data-management-tab
plan: 01
subsystem: database
tags: [wpdb, fluentcart, service-layer, cascade-delete, soft-delete]

# Dependency graph
requires: []
provides:
  - DataManagementService with 7 methods for query/delete/edit operations
  - Order cascade delete (shipment_items -> shipments -> order_items -> orders)
  - Product soft-delete following FluentCart convention
  - Customer edit across fct_customers + fct_customer_addresses + wp_usermeta
affects: [43-02-PLAN, data-management-api]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "DataManagementService: stateless service with wpdb direct queries"
    - "Cascade delete pattern: child tables first, parent tables last, wrapped in transaction"
    - "Soft delete for products via FluentCart ProductVariation::item_status = inactive"

key-files:
  created:
    - includes/services/class-data-management-service.php
  modified: []

key-decisions:
  - "Used wpdb direct queries (not Eloquent) for maximum control over cascade deletes and complex JOINs"
  - "Product soft-delete reuses existing FluentCart pattern from Products_API::batch_delete"
  - "Customer delete only removes FluentCart data (fct_customers + fct_customer_addresses), preserves WP user account"
  - "Order delete cascade includes child orders recursively"

patterns-established:
  - "DataManagementService: centralized data management business logic separate from API layer"
  - "Transaction wrapping for multi-table delete operations"

requirements-completed: [DATA-01, DATA-02, DATA-03, DATA-04]

# Metrics
duration: 15min
completed: 2026-02-21
---

# Phase 43 Plan 01: DataManagementService Summary

**DataManagementService with 7 methods: 3 query methods (orders/products/customers by date range + keyword), 3 delete methods (order cascade, product soft-delete, customer hard-delete), and customer edit with cross-table updates**

## Performance

- **Duration:** 15 min
- **Started:** 2026-02-21T14:07:19Z
- **Completed:** 2026-02-21T14:22:32Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- DataManagementService class created with all 7 required methods
- All SQL queries use $wpdb->prepare() for injection prevention
- Order deletion cascade correctly cleans buygo_shipment_items and buygo_shipments before fct_order_items and fct_orders
- Product soft-delete follows existing FluentCart convention (item_status = 'inactive')
- Customer edit updates fct_customers (name), fct_customer_addresses (address/phone), and optionally wp_usermeta (taiwan_id_number)

## Task Commits

Each task was committed atomically:

1. **Task 1: Create DataManagementService with query methods** - `19b6ecb` (feat)
2. **Task 2: Add delete and edit methods to DataManagementService** - `8ce655e` (feat)

## Files Created/Modified
- `includes/services/class-data-management-service.php` - DataManagementService with query, delete, and edit operations (635 lines)

## Decisions Made
- Used wpdb direct queries instead of FluentCart Eloquent models for query methods, giving full control over complex JOINs and date range filtering
- Reused existing FluentCart ProductVariation model for soft-delete to maintain consistency with Products_API::batch_delete
- Customer delete preserves WordPress user accounts - only FluentCart customer data is removed
- Order delete recursively handles child orders (finds child IDs, cascades their items/addresses too)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- DataManagementService is ready to be consumed by the API layer in Plan 43-02
- All 7 methods return structured arrays suitable for REST API responses
- No blockers for next plan

## Self-Check: PASSED

- [x] `includes/services/class-data-management-service.php` exists (635 lines)
- [x] Commit `19b6ecb` found (Task 1)
- [x] Commit `8ce655e` found (Task 2)
- [x] All 7 methods verified present
- [x] No PHP syntax errors

---
*Phase: 43-data-management-tab*
*Completed: 2026-02-21*
