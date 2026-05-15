## ADDED Requirements

### Requirement: Allocated Quantity MUST Be Computed From Child Orders

All read paths that surface "已分配 / allocated" for a product or for a customer's order line SHALL compute the value from `fct_orders` rows where `parent_id IS NOT NULL`, `type='split'`, and `status NOT IN ('cancelled','refunded')`, summed via `fct_order_items.quantity` grouped by `object_id`. Read paths MUST NOT depend on `wp_postmeta._buygo_allocated` or `fct_order_items.line_meta._allocated_qty` for this value.

Affected read paths:
- `GET /wp-json/buygo-plus-one/v1/products` — each item's `allocated` field
- `GET /wp-json/buygo-plus-one/v1/products/{id}` — `allocated` field
- `GET /wp-json/buygo-plus-one/v1/products/{id}/buyers` — each order entry's `allocated_quantity` field

#### Scenario: List endpoint reports allocated equal to live child-order sum

- **WHEN** product P has child orders (parent_id set, type='split', status NOT IN ('cancelled','refunded')) whose `fct_order_items` rows summed by `object_id` total N for P's variation ids
- **AND** `wp_postmeta._buygo_allocated` for P is empty or stale
- **THEN** `GET /products` MUST return `allocated = N` for product P
- **AND** the same N MUST be returned regardless of post_meta value

##### Example: post_meta empty, child orders sum to 2

| Source | Value |
| ------ | ----- |
| `wp_postmeta._buygo_allocated` for product 1055 | `''` (empty) |
| Parent `fct_order_items.line_meta._allocated_qty` sum | 0 |
| Child orders (id 1753 status=shipped qty=1, id 1754 status=shipped qty=1) sum | 2 |
| API `allocated` field returned | 2 |

#### Scenario: Buyers endpoint per-order allocated equals child-order sum scoped to that parent order

- **WHEN** parent order O contains a line for variation V with quantity Q
- **AND** child orders with `parent_id = O.id`, `type='split'`, `status NOT IN ('cancelled','refunded')` have order_items with `object_id = V` summing to A
- **THEN** the buyers endpoint MUST return `allocated_quantity = A` for that order entry
- **AND** `pending_quantity = max(0, Q - A)`

##### Example: parent order 1746 (qty=1) with one shipped child order 1754 (qty=1)

| Parent Order | Variation | Quantity | Child Orders | Allocated Qty | Pending Qty |
| ------------ | --------- | -------- | ------------ | ------------- | ----------- |
| 1746 | 1055 | 1 | #1754 (shipped, qty=1) | 1 | 0 |
| 1747 | 1055 | 1 | #1753 (shipped, qty=1) | 1 | 0 |

#### Scenario: Cancelled or refunded child orders are excluded from allocated

- **WHEN** a child order exists with `parent_id` set, `type='split'`, and `status IN ('cancelled','refunded')`
- **THEN** that child order's `quantity` MUST NOT be counted in `allocated` for the list endpoint
- **AND** MUST NOT be counted in `allocated_quantity` for the buyers endpoint
- **AND** MUST NOT be counted in `allocated` for the single-product endpoint

#### Scenario: All three read paths return the same allocated value for the same product

- **WHEN** a caller fetches `GET /products` (locating product P), `GET /products/{P.id}`, and sums `allocated_quantity` across all entries in `GET /products/{P.id}/buyers`
- **THEN** all three values MUST be equal
- **AND** all three MUST equal the live child-order sum for P's variation ids
