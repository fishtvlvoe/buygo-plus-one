## ADDED Requirements

### Requirement: API handlers must not execute DDL statements

API handler methods SHALL NOT execute any DDL (Data Definition Language) statements including ALTER TABLE, CREATE TABLE, DROP TABLE, or any schema modification.

All schema changes SHALL be managed exclusively through the database migration mechanism in class-database.php.

#### Scenario: Customer note update without DDL

- **WHEN** admin calls update_customer_note API endpoint
- **THEN** the system SHALL update the note value in the existing column
- **AND** the system SHALL NOT check INFORMATION_SCHEMA or execute ALTER TABLE
- **AND** the note column SHALL already exist via migration hook

##### Example: API handler contains no DDL

- **GIVEN** class-customers-api.php update_note method
- **WHEN** scanning the method source code
- **THEN** zero occurrences of ALTER TABLE, CREATE TABLE, DROP TABLE, or INFORMATION_SCHEMA SHALL be found

#### Scenario: Database migration ensures column exists

- **WHEN** the plugin is activated or upgraded
- **THEN** the upgrade_tables() method SHALL check for the note column in fct_customers
- **AND** if the column does not exist, it SHALL be added via ALTER TABLE ADD COLUMN
- **AND** subsequent plugin loads SHALL skip the ALTER TABLE if column already exists

##### Example: Migration idempotency

- **GIVEN** fct_customers table without note column
- **WHEN** plugin activates and upgrade_customers_table() runs
- **THEN** SHOW COLUMNS FROM fct_customers returns note column after execution
- **GIVEN** fct_customers table already has note column
- **WHEN** plugin loads again and upgrade_customers_table() runs
- **THEN** no ALTER TABLE is executed (idempotent)

### Requirement: Allocation updates use batch database operations

The updateOrderAllocations method SHALL query child order allocation sums in a single batch query before the processing loop, instead of executing individual queries per order item inside the loop.

The method SHALL update line_meta values in batch after the processing loop, instead of executing individual updates per order item inside the loop.

#### Scenario: Batch query replaces per-item queries

- **WHEN** admin confirms allocation for a product with N pending orders
- **THEN** the system SHALL execute at most 2 database operations for allocation sync (1 batch SELECT + 1 batch UPDATE)
- **AND** the system SHALL NOT execute any SELECT or UPDATE inside the per-item loop

##### Example: 5 order items batch performance

| Approach | DB Operations | Expected |
|----------|--------------|----------|
| Before (N+1) | 2 x 5 = 10 | not acceptable |
| After (batch) | 2 | required |

#### Scenario: Batch results are equivalent to per-item results

- **WHEN** batch query returns child allocation sums grouped by order_id
- **THEN** each order item's _allocated_qty in line_meta SHALL match the value that would have been computed by the per-item query
- **AND** no data accuracy SHALL be lost due to batching

##### Example: Batch vs per-item equivalence

- **GIVEN** 3 parent orders: #100 (child_allocated=2), #200 (child_allocated=5), #300 (child_allocated=0)
- **WHEN** batch query runs `SELECT order_id, SUM(quantity) FROM ... GROUP BY order_id`
- **THEN** result map SHALL be {100: 2, 200: 5, 300: 0}
- **AND** line_meta._allocated_qty for order #100 SHALL be 2
- **AND** line_meta._allocated_qty for order #200 SHALL be 5
- **AND** line_meta._allocated_qty for order #300 SHALL be 0
