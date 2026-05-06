## ADDED Requirements

### Requirement: Allocated quantity meta stays consistent after shipment

The `_buygo_allocated` post meta for a product SHALL be recalculated after every shipment operation. When `OrderService::shipOrder()` completes successfully, the system SHALL call `AllocationWriteService::recalcAllocatedMeta($product_id)` to recompute the value from actual child order data. The `_allocated_qty` line meta on parent order items SHALL be recalculated by summing active child order quantities (excluding cancelled, refunded, and shipped) instead of using cumulative subtraction.

#### Scenario: _buygo_allocated updates after shipment

- **WHEN** a child order with quantity=3 is shipped for product_id=100
- **THEN** the system SHALL recalculate `_buygo_allocated` for product_id=100 by summing all active (non-cancelled, non-refunded) split child order quantities
- **AND** the dashboard product list SHALL display the updated allocated count

##### Example: Post-shipment recalculation

- **GIVEN** product_id=100, purchased=10
- **GIVEN** child order #1: quantity=3, status=shipped
- **GIVEN** child order #2: quantity=4, status=completed (active split)
- **GIVEN** child order #3: quantity=2, status=cancelled
- **WHEN** child order #1 shipment completes
- **THEN** `_buygo_allocated` SHALL be recalculated as 4 (only active non-shipped splits count)

#### Scenario: _allocated_qty uses recalculation not subtraction

- **WHEN** a shipment is processed for a parent order item
- **THEN** the system SHALL recalculate `_allocated_qty` by querying `SUM(child_oi.quantity) WHERE child_o.type='split' AND child_o.status NOT IN ('cancelled', 'refunded', 'shipped') AND child_o.parent_id = parent_order_id`
- **AND** the system SHALL NOT use `current_allocated - shipped_qty` subtraction

##### Example: Drift correction via recalculation

- **GIVEN** parent order item has `_allocated_qty` = 7 (drifted from correct value)
- **GIVEN** actual active child orders sum to quantity = 5
- **WHEN** any shipment triggers recalculation
- **THEN** `_allocated_qty` SHALL be corrected to 5

### Requirement: splitOrder executes within database transaction

`OrderService::splitOrder()` SHALL wrap all write operations (INSERT fct_orders, INSERT fct_order_items, UPDATE line_meta) within a MySQL transaction (`START TRANSACTION` / `COMMIT`). If any write operation fails, the system SHALL `ROLLBACK` the entire transaction and return a WP_Error. No partial writes (orphan orders without items) SHALL persist.

#### Scenario: Successful split within transaction

- **WHEN** admin splits an order item into a child order
- **THEN** the system starts a transaction, inserts the child order, inserts the child order item, updates parent line_meta, and commits

##### Example: Normal split flow

- **GIVEN** parent order #500, order_item for product_id=100 variation A, quantity=5
- **WHEN** admin requests split with quantity=3
- **THEN** system executes START TRANSACTION → INSERT fct_orders (child order #501) → INSERT fct_order_items (quantity=3, object_id=variation_A) → UPDATE parent line_meta._allocated_qty → COMMIT
- **AND** child order #501 exists with status=completed and correct items

#### Scenario: Failed insert triggers rollback

- **WHEN** the child order INSERT succeeds but the child order item INSERT fails
- **THEN** the system SHALL rollback the transaction
- **AND** no orphan child order SHALL exist in fct_orders
- **AND** the system SHALL return WP_Error with details of the failure

##### Example: Orphan prevention

- **GIVEN** parent order #500 with order_item for product_id=100
- **WHEN** INSERT fct_orders succeeds (would create child order #501) but INSERT fct_order_items fails due to DB constraint
- **THEN** system executes ROLLBACK
- **AND** SELECT * FROM fct_orders WHERE id=501 returns empty result (no orphan)
