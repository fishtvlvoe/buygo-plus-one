# cancel-child-order Specification

## Purpose

TBD - created by archiving change 'cancel-child-order'. Update Purpose after archive.

## Requirements

### Requirement: Admin can cancel an unshipped child order

The system SHALL allow an admin to cancel a child order whose `shipping_status` is `unshipped`.
Upon cancellation, the system SHALL set the child order `status` to `cancelled` and release the allocated inventory for that child order.
The system SHALL NOT allow cancellation of child orders with `shipping_status` other than `unshipped`.

#### Scenario: Successful cancellation of unshipped child order

- **WHEN** admin sends `DELETE /wp-json/buygo-plus-one/v1/child-orders/{child_order_id}` with valid admin credentials
- **AND** the child order exists with `shipping_status = 'unshipped'`
- **THEN** the system SHALL update the child order `status` to `cancelled`
- **AND** the system SHALL clear the `_allocated_qty` meta on the child order item
- **AND** the system SHALL return HTTP 200 with `{ "success": true }`

#### Scenario: Cancellation rejected for shipped child order

- **WHEN** admin sends `DELETE /wp-json/buygo-plus-one/v1/child-orders/{child_order_id}`
- **AND** the child order `shipping_status` is `shipped`, `preparing`, `processing`, `ready_to_ship`, or `completed`
- **THEN** the system SHALL return HTTP 422 with `{ "success": false, "code": "CANNOT_CANCEL_SHIPPED", "message": "..." }`
- **AND** the child order status SHALL remain unchanged

#### Scenario: Cancellation rejected for already-cancelled child order

- **WHEN** admin sends `DELETE /wp-json/buygo-plus-one/v1/child-orders/{child_order_id}`
- **AND** the child order `status` is already `cancelled`
- **THEN** the system SHALL return HTTP 422 with `{ "success": false, "code": "ALREADY_CANCELLED", "message": "..." }`

#### Scenario: Cancellation rejected for non-existent child order

- **WHEN** admin sends `DELETE /wp-json/buygo-plus-one/v1/child-orders/{child_order_id}`
- **AND** no child order with that ID exists
- **THEN** the system SHALL return HTTP 404 with `{ "success": false, "code": "NOT_FOUND", "message": "..." }`

#### Scenario: Cancellation rejected for non-admin user

- **WHEN** a non-admin user sends `DELETE /wp-json/buygo-plus-one/v1/child-orders/{child_order_id}`
- **THEN** the system SHALL return HTTP 403


<!-- @trace
source: cancel-child-order
updated: 2026-04-17
code:
  - includes/api/class-api.php
  - tests/Unit/Services/OrderItemServiceTest.php
  - tests/Unit/Services/OrderFormatterChildOrderIdTest.php
  - includes/services/class-order-item-service.php
  - buygo-plus-one.php
  - includes/services/class-order-formatter.php
  - components/order/order-detail-modal.php
  - tests/bootstrap.php
  - includes/api/class-order-items-api.php
  - tests/bootstrap-unit.php
-->

---
### Requirement: Cancel button shown only for cancellable child orders

The system SHALL display a cancel button in the admin order detail page for child orders with `shipping_status = 'unshipped'` only.
The system SHALL NOT display the cancel button for child orders with any other `shipping_status` or with `status = 'cancelled'`.

#### Scenario: Cancel button visible for unshipped child order

- **WHEN** admin views the order detail page
- **AND** a child order has `shipping_status = 'unshipped'` and `status != 'cancelled'`
- **THEN** a cancel button SHALL be visible for that child order row

#### Scenario: Cancel button hidden for shipped child order

- **WHEN** admin views the order detail page
- **AND** a child order has `shipping_status` other than `unshipped`
- **THEN** no cancel button SHALL be visible for that child order row

#### Scenario: Cancel button triggers confirmation before proceeding

- **WHEN** admin clicks the cancel button for a child order
- **THEN** the system SHALL display a confirmation dialog before sending the DELETE request

#### Scenario: UI updates after successful cancellation

- **WHEN** admin confirms cancellation and the API returns HTTP 200
- **THEN** the child order row SHALL be removed from the UI or its status SHALL reflect `cancelled`
- **AND** the cancel button SHALL no longer be visible for that row


<!-- @trace
source: cancel-child-order
updated: 2026-04-17
code:
  - includes/api/class-api.php
  - tests/Unit/Services/OrderItemServiceTest.php
  - tests/Unit/Services/OrderFormatterChildOrderIdTest.php
  - includes/services/class-order-item-service.php
  - buygo-plus-one.php
  - includes/services/class-order-formatter.php
  - components/order/order-detail-modal.php
  - tests/bootstrap.php
  - includes/api/class-order-items-api.php
  - tests/bootstrap-unit.php
-->

---
### Requirement: Inventory released on child order cancellation

The system SHALL release the allocated inventory quota for a child order when it is cancelled.
Released inventory SHALL become available for allocation to other orders.

#### Scenario: Inventory quota released after cancellation

- **WHEN** a child order is successfully cancelled via the API
- **THEN** the `_allocated_qty` on the corresponding `fct_order_items` row SHALL be set to 0
- **AND** the allocation count query (which excludes `cancelled` orders) SHALL no longer count this child order's quantity

#### Scenario: Concurrent cancellation guard

- **WHEN** admin requests cancellation of a child order
- **AND** the child order `shipping_status` has changed to non-`unshipped` since the page loaded (race condition)
- **THEN** the system SHALL return HTTP 409 with `{ "success": false, "code": "STATUS_CONFLICT", "message": "..." }`
- **AND** no status change SHALL be persisted

<!-- @trace
source: cancel-child-order
updated: 2026-04-17
code:
  - includes/api/class-api.php
  - tests/Unit/Services/OrderItemServiceTest.php
  - tests/Unit/Services/OrderFormatterChildOrderIdTest.php
  - includes/services/class-order-item-service.php
  - buygo-plus-one.php
  - includes/services/class-order-formatter.php
  - components/order/order-detail-modal.php
  - tests/bootstrap.php
  - includes/api/class-order-items-api.php
  - tests/bootstrap-unit.php
-->

---
### Requirement: Cancel button visible in parent order detail page for unshipped items

The system SHALL display a cancel button for each order item in the parent order detail page when the corresponding child order has `shipping_status = 'unshipped'` and `status != 'cancelled'`.
The system SHALL NOT display the cancel button for items whose child order has any other `shipping_status` or `status = 'cancelled'`.

#### Scenario: Cancel button visible for unshipped item in parent order detail

- **WHEN** admin views the parent order detail page
- **AND** an order item's child order has `shipping_status = 'unshipped'` and `status != 'cancelled'`
- **THEN** a cancel button SHALL be visible for that item row in the order detail list

#### Scenario: Cancel button hidden for shipped item in parent order detail

- **WHEN** admin views the parent order detail page
- **AND** an order item's child order has `shipping_status` other than `unshipped`
- **THEN** no cancel button SHALL be visible for that item row

#### Scenario: Cancel button hidden for already-cancelled item in parent order detail

- **WHEN** admin views the parent order detail page
- **AND** an order item's child order has `status = 'cancelled'`
- **THEN** no cancel button SHALL be visible for that item row

#### Scenario: Cancel button triggers confirmation before proceeding

- **WHEN** admin clicks the cancel button for an order item in the parent order detail page
- **THEN** the system SHALL display a confirmation dialog before sending the DELETE request
- **AND** the system SHALL use the existing `showConfirm` pattern

#### Scenario: UI updates after successful cancellation from parent order detail

- **WHEN** admin confirms cancellation from the parent order detail page
- **AND** the API returns HTTP 200
- **THEN** the cancel button SHALL no longer be visible for that item row
- **AND** a success toast notification SHALL be shown


<!-- @trace
source: cancel-order-item-from-detail
updated: 2026-04-17
code:
  - includes/services/class-order-item-service.php
  - includes/services/class-order-formatter.php
  - buygo-plus-one.php
  - tests/bootstrap.php
  - tests/bootstrap-unit.php
  - components/order/order-detail-modal.php
  - tests/Unit/Services/OrderItemServiceTest.php
  - includes/api/class-order-items-api.php
  - includes/api/class-api.php
  - tests/Unit/Services/OrderFormatterChildOrderIdTest.php
-->

---
### Requirement: Order formatter includes child_order_id in item data

The system SHALL include `child_order_id` in each formatted order item returned by `OrderFormatter::formatOrderItem()`.
When a corresponding child order exists, `child_order_id` SHALL be the integer ID of that child order.
When no corresponding child order exists, `child_order_id` SHALL be `null`.

#### Scenario: child_order_id present when child order exists

- **WHEN** `formatOrder()` is called for a parent order with child orders
- **THEN** each item in the returned `items` array SHALL have `child_order_id` set to the corresponding child order's integer ID

#### Scenario: child_order_id null when no child order exists

- **WHEN** `formatOrder()` is called for an order with no child orders
- **THEN** each item in the returned `items` array SHALL have `child_order_id` set to `null`

<!-- @trace
source: cancel-order-item-from-detail
updated: 2026-04-17
code:
  - includes/services/class-order-item-service.php
  - includes/services/class-order-formatter.php
  - buygo-plus-one.php
  - tests/bootstrap.php
  - tests/bootstrap-unit.php
  - components/order/order-detail-modal.php
  - tests/Unit/Services/OrderItemServiceTest.php
  - includes/api/class-order-items-api.php
  - includes/api/class-api.php
  - tests/Unit/Services/OrderFormatterChildOrderIdTest.php
-->