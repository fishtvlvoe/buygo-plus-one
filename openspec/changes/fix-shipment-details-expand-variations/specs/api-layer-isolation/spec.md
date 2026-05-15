## ADDED Requirements

### Requirement: Shipment Detail Endpoint MUST Return Variation Identification

The single-shipment REST endpoint (`GET /wp-json/buygo-plus-one/v1/shipments/{id}`) SHALL return, for each entry in `data.items`, three additional fields identifying the underlying product variation: `variation_id`, `variation_title`, and `variation_identifier`. When the underlying `fct_order_items.object_id` does not match any row in `fct_product_variations`, these three fields MUST each be `null`.

#### Scenario: Each shipment item exposes its variation title

- **WHEN** a caller invokes `GET /shipments/{shipment_id}` for a shipment containing items linked to `fct_order_items` rows whose `object_id` matches `fct_product_variations.id`
- **THEN** every entry in the response `data.items[]` MUST include the fields `variation_id` (int), `variation_title` (string), and `variation_identifier` (string)
- **AND** the field values MUST equal the matching `fct_product_variations` row's `id`, `variation_title`, and `variation_identifier` columns respectively

##### Example: Shipment SH-20260508-008 returns three variation titles

| `shipment_item.id` | `order_item.object_id` | Returned `variation_id` | Returned `variation_title` |
| ------------------ | ---------------------- | ----------------------- | -------------------------- |
| 444 | 976 | 976 | `(A) 薄荷巧克力` |
| 445 | 977 | 977 | (whatever `fct_product_variations.variation_title` for id=977 contains) |
| 446 | 978 | 978 | (whatever for id=978 contains) |
| 447 | 977 | 977 | (same as item 445) |

#### Scenario: Missing variation row yields null fields without 5xx

- **WHEN** a shipment item's `order_item.object_id` does not match any `fct_product_variations.id`
- **THEN** the response MUST still return HTTP 200
- **AND** that item's `variation_id`, `variation_title`, and `variation_identifier` MUST each be `null`
- **AND** the item's existing fields (`id`, `shipment_id`, `order_id`, `order_item_id`, `product_id`, `quantity`, `created_at`) MUST remain unchanged in name, type, and value

#### Scenario: Existing item field contract is preserved

- **WHEN** any caller relies on the prior shape of `data.items[]` entries
- **THEN** the fields `id`, `shipment_id`, `order_id`, `order_item_id`, `product_id`, `quantity`, and `created_at` MUST be present with the same types and meanings as before this requirement was added
- **AND** no existing field MUST be renamed, removed, or have its semantics changed

##### Example: Field contract before and after

| Field | Before this change | After this change |
| ----- | ------------------ | ----------------- |
| `id` | int | int (unchanged) |
| `shipment_id` | int | int (unchanged) |
| `order_id` | int | int (unchanged) |
| `order_item_id` | int | int (unchanged) |
| `product_id` | int | int (unchanged) |
| `quantity` | int | int (unchanged) |
| `created_at` | string (ISO datetime) | string (unchanged) |
| `variation_id` | (absent) | int or null (new) |
| `variation_title` | (absent) | string or null (new) |
| `variation_identifier` | (absent) | string or null (new) |
