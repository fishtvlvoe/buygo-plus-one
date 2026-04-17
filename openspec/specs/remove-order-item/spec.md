# remove-order-item Specification

## Purpose

TBD - created by archiving change 'remove-order-item'. Update Purpose after archive.

## Requirements

### Requirement: Remove button visible for editable order items

The system SHALL display a remove button for each order item in the parent order detail page when the order has `status != 'completed'` and `status != 'cancelled'`.
The system SHALL NOT display the remove button for items in orders with `status = 'completed'` or `status = 'cancelled'`.

#### Scenario: Remove button visible for on-hold order items

- **WHEN** admin views the parent order detail page
- **AND** the order has `status = 'on-hold'`
- **THEN** a remove button SHALL be visible for each item row in the order detail list

#### Scenario: Remove button hidden for completed order

- **WHEN** admin views the parent order detail page
- **AND** the order has `status = 'completed'`
- **THEN** no remove button SHALL be visible for any item row

#### Scenario: Remove button hidden for cancelled order

- **WHEN** admin views the parent order detail page
- **AND** the order has `status = 'cancelled'`
- **THEN** no remove button SHALL be visible for any item row


<!-- @trace
source: remove-order-item
updated: 2026-04-17
code:
  - tests/Unit/Services/OrderItemServiceTest.php
  - components/order/order-detail-modal.php
  - buygo-plus-one.php
  - includes/api/class-api.php
  - includes/services/class-order-item-service.php
  - includes/api/class-order-items-api.php
-->

---
### Requirement: Remove button triggers confirmation before proceeding

The system SHALL display a confirmation dialog before sending the DELETE request when admin clicks the remove button.

#### Scenario: Confirmation dialog shown on remove click

- **WHEN** admin clicks the remove button for an order item
- **THEN** the system SHALL display a confirmation dialog before sending any API request
- **AND** the system SHALL NOT proceed if admin cancels the dialog


<!-- @trace
source: remove-order-item
updated: 2026-04-17
code:
  - tests/Unit/Services/OrderItemServiceTest.php
  - components/order/order-detail-modal.php
  - buygo-plus-one.php
  - includes/api/class-api.php
  - includes/services/class-order-item-service.php
  - includes/api/class-order-items-api.php
-->

---
### Requirement: Remove order item via API

The system SHALL expose `DELETE /wp-json/buygo-plus-one/v1/orders/{order_id}/items/{item_id}` to remove a single order item.
The system SHALL reject requests where the order has `status = 'completed'` or `status = 'cancelled'` with HTTP 422.
The system SHALL reject requests where `item_id` does not belong to `order_id` with HTTP 404.
The system SHALL delete the order item from `fct_order_items` and trigger FluentCart's order total recalculation on success.

#### Scenario: Successful item removal

- **WHEN** admin sends `DELETE /orders/{order_id}/items/{item_id}`
- **AND** the order status is not `completed` or `cancelled`
- **AND** the item belongs to the order
- **THEN** the system SHALL return HTTP 200
- **AND** the item SHALL be removed from `fct_order_items`
- **AND** the order total SHALL be recalculated

#### Scenario: Rejection for completed order

- **WHEN** admin sends `DELETE /orders/{order_id}/items/{item_id}`
- **AND** the order has `status = 'completed'`
- **THEN** the system SHALL return HTTP 422 with an error message

#### Scenario: Rejection for item not belonging to order

- **WHEN** admin sends `DELETE /orders/{order_id}/items/{item_id}`
- **AND** `item_id` does not belong to `order_id`
- **THEN** the system SHALL return HTTP 404


<!-- @trace
source: remove-order-item
updated: 2026-04-17
code:
  - tests/Unit/Services/OrderItemServiceTest.php
  - components/order/order-detail-modal.php
  - buygo-plus-one.php
  - includes/api/class-api.php
  - includes/services/class-order-item-service.php
  - includes/api/class-order-items-api.php
-->

---
### Requirement: UI updates after successful removal

The system SHALL reload the order detail data after a successful item removal.
The system SHALL show a success toast notification after successful removal.

#### Scenario: Order detail reloads after removal

- **WHEN** admin confirms removal and the API returns HTTP 200
- **THEN** the remove button for that item SHALL no longer be visible
- **AND** the order total displayed SHALL reflect the updated amount
- **AND** a success toast notification SHALL be shown

<!-- @trace
source: remove-order-item
updated: 2026-04-17
code:
  - tests/Unit/Services/OrderItemServiceTest.php
  - components/order/order-detail-modal.php
  - buygo-plus-one.php
  - includes/api/class-api.php
  - includes/services/class-order-item-service.php
  - includes/api/class-order-items-api.php
-->