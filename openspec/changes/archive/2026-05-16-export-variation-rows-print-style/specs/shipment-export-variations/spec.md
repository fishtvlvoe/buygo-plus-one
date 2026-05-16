## ADDED Requirements

### Requirement: Variation-level CSV rows

When a shipment contains items with product variations, the CSV export SHALL output one row per variation instead of grouping by product_id.

Each variation row SHALL display the product name in the format: `{product_name} - ({variation_identifier}) {variation_title}`.

Items without variations (single-product items) SHALL output as a single row with the original product name, maintaining backward compatibility.

The CSV header columns SHALL remain unchanged from the current format.

#### Scenario: Export shipment with multiple variations

WHEN a shipment contains product "產品測試" with variations "(A) 漢頓" qty 3 and "(C) 大耳狗" qty 3
THEN the CSV output SHALL contain two rows:
  - Row 1: product name "產品測試 - (A) 漢頓", quantity 3, unit price 1000, subtotal 3000
  - Row 2: product name "產品測試 - (C) 大耳狗", quantity 3, unit price 1000, subtotal 3000

#### Scenario: Export shipment with no variations

WHEN a shipment contains a product without variations, quantity 5, price 500
THEN the CSV output SHALL contain one row with the original product name, quantity 5, subtotal 2500

#### Scenario: Export shipment mixing variation and non-variation products

WHEN a shipment contains both variation products and non-variation products
THEN variation products SHALL be expanded into separate rows per variation
AND non-variation products SHALL remain as single rows
