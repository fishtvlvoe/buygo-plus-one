## ADDED Requirements

### Requirement: Products List Endpoint MUST NOT Cache Stat Fields

The products list REST endpoint (`GET /wp-json/buygo-plus-one/v1/products`) SHALL NOT read from or write to any cache (transient, object cache, or in-process memo) for fields derived from per-product stock state: `allocated`, `pending`, `reserved`, `ordered`, `purchased`, `shipped`. Each invocation MUST compute these values from the live data source.

This requirement exists because write paths that mutate `_buygo_allocated` post meta (e.g. `AllocationWriteService::saveAllocation`) do not currently invalidate any such cache, and adding namespace-wide invalidation is out of scope. Removing the cache is the contract.

#### Scenario: List endpoint reflects latest allocation immediately

- **WHEN** a caller mutates `_buygo_allocated` for product P via any write service
- **AND** a subsequent `GET /products` request is issued within the same second
- **THEN** the returned `allocated` value for product P MUST equal the just-written value
- **AND** no `get_transient('buygo_products_...')` or `set_transient('buygo_products_...')` call MUST be executed during the request

##### Example: allocation write then immediate list read

| Step | Action | Expected `allocated` for product 1055 |
| ---- | ------ | -------------------------------------- |
| 1 | `update_post_meta(1055, '_buygo_allocated', 0)` then `GET /products` | 0 |
| 2 | `update_post_meta(1055, '_buygo_allocated', 2)` then `GET /products` within 1s | 2 |
| 3 | Repeat step 2 within 30s window | 2 (never 0) |

#### Scenario: Source code contains no products-namespace transient operations

- **WHEN** a static scan inspects `includes/api/class-products-api.php`
- **THEN** the file MUST contain zero occurrences of the substring `get_transient('buygo_products_`
- **AND** the file MUST contain zero occurrences of the substring `set_transient('buygo_products_`

### Requirement: Reserved Quantity Formula MUST Be Consistent Across Endpoints

The `reserved` field returned by the products list endpoint MUST be computed with the same formula as the single-product endpoint: `reserved = max(0, ordered - purchased - allocated)` where `ordered`, `purchased`, and `allocated` are non-negative integers.

#### Scenario: List and single-product endpoints return identical reserved value

- **WHEN** product P has `ordered=O`, `purchased=U`, `allocated=A` for any non-negative integers O, U, A
- **AND** caller issues both `GET /products` (locating P in the array) and `GET /products/{P.id}`
- **THEN** both responses MUST return the same integer value for `reserved`
- **AND** that value MUST equal `max(0, O - U - A)`

##### Example: reserved calculation across cases

| `ordered` | `purchased` | `allocated` | `reserved` (both endpoints) |
| --------- | ----------- | ----------- | --------------------------- |
| 0 | 0 | 0 | 0 |
| 10 | 5 | 0 | 5 |
| 10 | 10 | 0 | 0 |
| 14 | 10 | 4 | 0 |
| 10 | 5 | 2 | 3 |
| 10 | 10 | 12 | 0 (floored at zero) |
| 2 | 2 | 2 | 0 |
