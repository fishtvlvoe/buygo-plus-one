## ADDED Requirements

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
