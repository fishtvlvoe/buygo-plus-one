## MODIFIED Requirements

### Requirement: Admin can cancel an unshipped child order

The system SHALL allow an admin to cancel a child order whose `shipping_status` is `unshipped`.
Upon cancellation, the system SHALL set the child order `status` to `cancelled` and release the allocated inventory for that child order.
The system SHALL NOT allow cancellation of child orders with `shipping_status` other than `unshipped`.

When calculating split quantity in `OrderService::splitOrder()`, the system SHALL exclude child orders with status `cancelled` or `refunded` from the sum. This ensures that cancelled child orders do not consume allocation quota, allowing the freed quantity to be re-allocated.

#### Scenario: Successful cancellation of unshipped child order

- **WHEN** admin sends `DELETE /wp-json/buygo-plus-one/v1/child-orders/{child_order_id}` with valid admin credentials
- **AND** the child order exists with `shipping_status = 'unshipped'`
- **THEN** the system SHALL update the child order `status` to `cancelled`
- **AND** the system SHALL clear the `_allocated_qty` meta on the child order item
- **AND** the system SHALL return HTTP 200 with `{ "success": true }`

#### Scenario: Cancelled child order does not consume split quota

- **WHEN** a parent order has quantity=5 for variation A
- **AND** a child order with quantity=3 exists but has status `cancelled`
- **THEN** `splitOrder()` SHALL calculate available_quantity as 5 (not 5-3=2)
- **AND** the admin SHALL be able to create a new child order with up to quantity=5

##### Example: Re-allocation after cancellation

- **GIVEN** parent order item: product_id=100, variation A, quantity=5
- **GIVEN** child order #1: quantity=3, status=cancelled
- **GIVEN** child order #2: quantity=1, status=completed
- **WHEN** admin requests split for quantity=4
- **THEN** system calculates split_quantity = 1 (only non-cancelled child orders)
- **THEN** available = 5 - 1 = 4, request is accepted

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
