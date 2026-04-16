## ADDED Requirements

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
