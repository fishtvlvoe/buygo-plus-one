## ADDED Requirements

### Requirement: Allocation page stats reflect selected variation filter

When the admin views the allocation page, the statistics panel (ordered quantity, purchased, allocatable, allocated) SHALL update to reflect the currently selected variation filter.
When "All" is selected, the system SHALL display aggregate totals across all variations.
When a specific variation is selected, the system SHALL display stats for that variation only.

#### Scenario: All variations selected shows aggregate stats

- **WHEN** admin is on the allocation page
- **AND** the variation filter is set to "All" (empty value)
- **THEN** the "ordered" stat SHALL show total ordered quantity across all variations
- **AND** the "purchased" stat SHALL show total purchased quantity across all variations
- **AND** the "allocatable" stat SHALL equal total purchased minus total allocated across all variations
- **AND** the "allocated" stat SHALL show total allocated quantity across all variations

#### Scenario: Specific variation selected shows variation-only stats

- **WHEN** admin selects a specific variation (e.g. "1號") from the filter dropdown
- **THEN** the "ordered" stat SHALL show the ordered quantity for that variation only (sum of item quantities, not order count)
- **AND** the "purchased" stat SHALL show the purchased quantity for that variation only
- **AND** the "allocatable" stat SHALL equal that variation's purchased minus allocated
- **AND** the "allocated" stat SHALL show the allocated quantity for that variation only

#### Scenario: Switching variation filter updates stats immediately

- **WHEN** admin switches the variation filter from one variation to another
- **THEN** the stats panel SHALL update to reflect the newly selected variation
- **AND** during the API fetch, the stats SHALL be cleared to zero to avoid showing stale data

#### Scenario: Switching back to All restores aggregate stats

- **WHEN** admin switches the variation filter back to "All"
- **THEN** the stats panel SHALL revert to showing the aggregate totals from `selectedProduct`
- **AND** no additional API call SHALL be required (data already loaded)

#### Scenario: Order list filter remains independent

- **WHEN** admin changes the variation filter
- **THEN** the order list below SHALL continue to filter correctly by variation (existing behavior)
- **AND** the order list filter SHALL not be affected by the stats panel changes
