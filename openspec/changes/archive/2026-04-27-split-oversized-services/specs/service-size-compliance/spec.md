## ADDED Requirements

### Requirement: AllocationService child classes must not exceed 300 lines

After splitting AllocationService, each resulting Service class SHALL contain no more than 300 lines of PHP code (as measured by `wc -l`).

#### Scenario: AllocationQueryService line count within limit

- **WHEN** `wc -l includes/services/class-allocation-query-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

#### Scenario: AllocationWriteService line count within limit

- **WHEN** `wc -l includes/services/class-allocation-write-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

#### Scenario: AllocationCalculator line count within limit

- **WHEN** `wc -l includes/services/class-allocation-calculator.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

### Requirement: ProductService child classes must not exceed 300 lines

After splitting ProductService, each resulting Service class SHALL contain no more than 300 lines of PHP code (as measured by `wc -l`).

#### Scenario: ProductQueryService line count within limit

- **WHEN** `wc -l includes/services/class-product-query-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

#### Scenario: ProductWriteService line count within limit

- **WHEN** `wc -l includes/services/class-product-write-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

#### Scenario: ProductVariationService line count within limit

- **WHEN** `wc -l includes/services/class-product-variation-service.php` is executed
- **THEN** the reported line count SHALL be ≤ 300

### Requirement: Facade preserves backward compatibility for AllocationService callers

The original AllocationService class SHALL remain instantiable and SHALL delegate all public method calls to the appropriate child Service without changing method signatures or return types.

#### Scenario: Facade method delegation succeeds

- **WHEN** a caller invokes any public method on AllocationService (e.g. `getProductOrders`, `updateOrderAllocations`, `validateAdjustment`, `adjustAllocation`, `cancelChildOrder`)
- **THEN** the facade SHALL delegate to the corresponding child Service and return an identical result

##### Example: getProductOrders delegation

- **GIVEN** AllocationService facade delegates `getProductOrders()` to AllocationQueryService
- **WHEN** `$allocationService->getProductOrders($productId)` is called
- **THEN** the return value SHALL be identical to calling `$allocationQueryService->getProductOrders($productId)` directly

### Requirement: Facade preserves backward compatibility for ProductService callers

The original ProductService class SHALL remain instantiable and SHALL delegate all public method calls to the appropriate child Service without changing method signatures or return types.

#### Scenario: Facade method delegation succeeds

- **WHEN** a caller invokes any public method on ProductService (e.g. `getProductsWithOrderCount`, `updateProduct`, `getVariations`, `getVariationStats`)
- **THEN** the facade SHALL delegate to the corresponding child Service and return an identical result

### Requirement: All existing tests must pass after refactoring

The full PHPUnit test suite SHALL pass with zero failures after the split and facade introduction.

#### Scenario: Composer test suite passes

- **WHEN** `composer test` is executed from the project root
- **THEN** all test cases SHALL pass with exit code 0 and zero failures reported
