## ADDED Requirements

### Requirement: Parent order shipping_status synchronizes when child order is marked as shipped

When a child order's shipping_status is updated to `shipped` via the shipment workflow, the system SHALL automatically recalculate and update the parent order's shipping_status based on the aggregate state of all child orders.

#### Scenario: Single child order shipped updates parent to shipped

- **WHEN** a child order (type='split', parent_id = 1747) has its shipping_status set to `shipped` via `ShipmentService::mark_shipped()`
- **THEN** the parent order (id = 1747) shipping_status SHALL be updated to `shipped`

#### Scenario: Multiple child orders with partial shipment updates parent to preparing

- **GIVEN** parent order 2000 has 3 child orders with shipping_status: [unshipped, shipped, unshipped]
- **WHEN** `ShipmentService::mark_shipped()` marks one child order as shipped
- **THEN** the parent order shipping_status SHALL be updated to `preparing` (because not all children are shipped)

#### Scenario: All child orders shipped updates parent to shipped

- **GIVEN** parent order 3000 has 2 child orders with shipping_status: [shipped, shipped]
- **WHEN** the second child order is marked as shipped
- **THEN** the parent order shipping_status SHALL be updated to `shipped`

#### Scenario: Shipment service failure does not block parent sync

- **WHEN** `ShipmentService::mark_shipped()` successfully updates the shipment record but parent sync encounters an error
- **THEN** the shipment SHALL still be treated as successful
- **AND** the error SHALL be logged via DebugService
