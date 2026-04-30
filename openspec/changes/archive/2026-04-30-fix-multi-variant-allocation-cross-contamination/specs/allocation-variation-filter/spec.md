## ADDED Requirements

### Requirement: Child orders MUST preserve target variation identity

When the system creates a child order (split order) during stock allocation, the child order's `fct_order_items.object_id` SHALL exactly match the variation_id of the parent order item being allocated, regardless of how many sibling variations exist on the same parent order.

The system SHALL NOT use `IN (variation_id, ...)` with `LIMIT 1` semantics when locating the parent order item, because this implicitly selects the lowest variation_id and produces incorrect cross-variant child orders.

#### Scenario: Allocating BCD variants on a parent order that also has variant A

- **WHEN** a parent order #1687 contains four order_items: A (qty=3), B (qty=3), C (qty=3), D (qty=2)
- **AND** the admin allocates 2 units of variant D
- **THEN** a new child order SHALL be created with `type='split'` and `parent_id=1687`
- **AND** the child order's order_item SHALL have `object_id` equal to D's variation_id
- **AND** the child order's order_item SHALL NOT have `object_id` equal to A's variation_id

##### Example: Parent #1687 with ABCD variants, allocating D=2

- **GIVEN** parent order #1687 with order_items: `[{object_id: 1038, qty: 3}, {object_id: 1039, qty: 3}, {object_id: 1040, qty: 3}, {object_id: 1041, qty: 2}]`
- **WHEN** allocate D (variation_id=1041) quantity=2
- **THEN** new child order's order_item SHALL be `{object_id: 1041, qty: 2}`

#### Scenario: Allocating a single-variation product still works

- **WHEN** a parent order #500 contains one order_item with variation_id=900 (single-variation product)
- **AND** the admin allocates 5 units
- **THEN** a child order SHALL be created with order_item `{object_id: 900, qty: 5}`

### Requirement: Allocate-all for a customer MUST create independent child orders per variation

When the system runs "allocate all for customer" on a parent order containing multiple variations of the same product, the system SHALL produce one child order per variation that has remaining unallocated quantity, each carrying its own correct `object_id`.

The system SHALL NOT collapse multiple variations of the same parent order into a single allocation entry, because doing so silently overwrites earlier variations' needed quantities.

#### Scenario: One-click allocate on a 4-variation parent order

- **WHEN** parent order #1687 has four order_items A(3 needed)/B(3 needed)/C(3 needed)/D(2 needed)
- **AND** the admin clicks "allocate all" for that customer's order
- **THEN** the system SHALL create four child orders, each with a distinct `object_id` matching A/B/C/D
- **AND** each child order's order_item quantity SHALL match the corresponding variation's needed quantity
- **AND** the response SHALL report `total_allocated = 11` (3+3+3+2)

##### Example: Multi-variant allocate-all output

- **GIVEN** parent order #1687 with needs `{A: 3, B: 3, C: 3, D: 2}` and zero existing child orders
- **WHEN** allocate-all-for-customer is invoked
- **THEN** four child orders SHALL exist after the call:

| child_order | parent_id | object_id | quantity |
| ----------- | --------- | --------- | -------- |
| (id 1)      | 1687      | 1038 (A)  | 3        |
| (id 2)      | 1687      | 1039 (B)  | 3        |
| (id 3)      | 1687      | 1040 (C)  | 3        |
| (id 4)      | 1687      | 1041 (D)  | 2        |

### Requirement: Allocate stock API MUST accept per-item allocations carrying object_id

The `POST /wp-json/buygo-plus-one/v1/products/{id}/allocate` endpoint SHALL accept allocation entries that include `object_id` to disambiguate variations on the same parent order.

The endpoint SHALL preserve backward compatibility: the legacy formats `{ "<order_id>": <qty> }` (object map) and `[{order_id, allocated}]` (without `object_id`) SHALL continue to work for single-variation products by automatically resolving `object_id` from the parent order's first order_item for the requested product.

The endpoint SHALL forward the `object_id` value into the AllocationService layer when present, so that downstream child-order creation uses the explicit variation.

#### Scenario: Multi-variant allocation request with explicit object_id

- **WHEN** client sends `POST /products/2650/allocate` with body `{"product_id": 2650, "allocations": [{"order_id": 1687, "object_id": 1040, "allocated": 3}, {"order_id": 1687, "object_id": 1041, "allocated": 2}]}`
- **THEN** the system SHALL create two child orders: one with object_id=1040 qty=3, one with object_id=1041 qty=2
- **AND** the API SHALL return `success: true` with two entries in `child_orders`

#### Scenario: Legacy single-variant allocation request without object_id still works

- **WHEN** client sends `POST /products/500/allocate` with body `{"product_id": 500, "allocations": {"123": 5}}`
- **AND** parent order #123 contains exactly one order_item for the product
- **THEN** the system SHALL create one child order with the resolved `object_id` from the parent order_item

### Requirement: Cross-variant purchased pool validation MUST remain enforced

The total allocated quantity across all variations of the same product SHALL NOT exceed the total purchased quantity across all variations. The system SHALL keep the existing cross-variant pool semantic — purchased counts SHALL be shared across variations of the same `post_id` — while still creating per-variation child orders.

#### Scenario: Allocation rejected when total exceeds purchased pool

- **WHEN** product post_id=2650 has total purchased=11 across A/B/C/D
- **AND** existing child orders sum to 9 across all variations
- **AND** admin attempts to allocate 3 more units (total would become 12)
- **THEN** the system SHALL reject with error code `INSUFFICIENT_STOCK`
- **AND** SHALL NOT create any new child orders

##### Example: Cross-variant overflow

- **GIVEN** purchased totals `{A: 7, B: 4, C: 0, D: 0}` (sum=11), existing allocated total=9
- **WHEN** request to allocate +3 units
- **THEN** validation SHALL fail with `INSUFFICIENT_STOCK`, message includes total=12 vs purchased=11

### Requirement: One-time data repair SHALL fix existing cross-variant contaminated child orders

When the data repair WP-CLI command is executed, the system SHALL identify child orders whose `object_id` does not match any order_item of the same `parent_id`, and SHALL relabel each contaminated child order's `object_id` to the most-needy variation on the parent order at execution time.

The repair SHALL run in two phases: a `--dry-run` phase that prints planned changes without writing to the database, and a `--commit` phase that performs the actual writes inside a transaction.

The repair SHALL also recompute `wp_postmeta._buygo_allocated` for affected products and supplement missing `_buygo_purchased` meta for variations that lack one (using a value provided by the operator).

#### Scenario: Dry-run prints repair plan without writing

- **WHEN** operator runs `wp buygo fix-cross-variant-child-orders --dry-run`
- **THEN** the command SHALL output a plan listing each contaminated child_order_id, its current object_id, and the proposed new object_id
- **AND** SHALL NOT modify any database row

#### Scenario: Commit applies the plan inside a transaction

- **WHEN** operator runs `wp buygo fix-cross-variant-child-orders --commit`
- **THEN** the command SHALL update each contaminated child order's order_item `object_id` to the chosen variation
- **AND** SHALL recompute `wp_postmeta._buygo_allocated` for each affected post_id
- **AND** SHALL exit with success code if all updates committed, or rollback the transaction if any update fails

##### Example: Repairing 5 contaminated child orders on post_id=2650

- **GIVEN** 5 child orders all with `object_id=1038` (variant A) but their parent orders' actual needs span B/C/D
- **WHEN** repair commit runs
- **THEN** each child order's `object_id` SHALL be updated to one of {1039, 1040, 1041} based on the most-needy variation at the time of repair
- **AND** `wp_postmeta._buygo_allocated` for post_id=2650 SHALL be recomputed from the corrected child orders
