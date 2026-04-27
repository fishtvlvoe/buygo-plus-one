## ADDED Requirements

### Requirement: Allocation demand quantity uses child order actual quantity

When validating allocation limits for a child order (type='split'), the system SHALL use the child order's own `fct_order_items.quantity` as the demand quantity, NOT the parent order's original line item quantity.

The demand quantity for an order item SHALL be determined as follows:
- For child orders (parent_id IS NOT NULL): the quantity from the child order's own `fct_order_items` record
- For parent orders (parent_id IS NULL): the quantity from the parent order's `fct_order_items` record

The system SHALL NOT produce a "demand exceeded" warning when the allocated quantity is within the child order's actual demand.

#### Scenario: Child order demand reflects its own quantity

- **WHEN** a parent order with quantity 5 is split into child orders
- **AND** child order #A has quantity 3 for a specific variation
- **THEN** the demand quantity for child order #A SHALL be 3
- **AND** allocating 3 units to child order #A SHALL NOT trigger a "demand exceeded" warning

##### Example: Split order allocation validation

| Order | Type | Item Quantity | Allocate | Expected Warning |
|-------|------|--------------|----------|-----------------|
| #1420 | split (child) | 3 | 3 | none |
| #1420 | split (child) | 3 | 4 | "exceeds demand" |
| #1000 | parent | 5 | 5 | none |
| #1000 | parent | 5 | 6 | "exceeds demand" |

#### Scenario: Multiple child orders from same parent each have independent demand

- **WHEN** a parent order with quantity 5 is split into three child orders
- **AND** child order #A has quantity 1, #B has quantity 1, #C has quantity 3
- **THEN** each child order's demand SHALL be evaluated independently
- **AND** allocating 3 units to child order #C SHALL NOT trigger a warning
- **AND** the sum of child order quantities (1+1+3=5) SHALL equal the parent order quantity

##### Example: Independent demand validation per child

- **GIVEN** parent order #100 with item quantity 5
- **AND** child order #101 (quantity=1), #102 (quantity=1), #103 (quantity=3)
- **WHEN** admin allocates 1 to #101, 1 to #102, 3 to #103
- **THEN** no "demand exceeded" warning SHALL appear for any child order

#### Scenario: API returns correct demand for child orders in allocation list

- **WHEN** the allocation page API returns pending orders for a product
- **THEN** each order item's demand quantity SHALL reflect its own order's quantity
- **AND** child orders SHALL NOT inherit or reference the parent order's quantity for demand calculation

##### Example: API response with mixed parent and child orders

- **GIVEN** product variation ID 966
- **AND** parent order #1400 (quantity=5) split into child orders #1425 (quantity=1), #1422 (quantity=1), #1420 (quantity=3)
- **WHEN** admin opens allocation page for variation 966
- **THEN** API response SHALL contain:

| order_id | type | quantity (demand) | allocated |
|----------|------|-------------------|-----------|
| #1425 | split | 1 | 0 |
| #1422 | split | 1 | 1 |
| #1420 | split | 3 | 0 |
