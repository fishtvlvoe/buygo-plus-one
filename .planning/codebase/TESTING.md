# Testing Patterns

**Analysis Date:** 2026-01-28

## Test Framework

**Runner:**
- PHPUnit 9.x (`phpunit/phpunit: ^9.0`)
- Config: `phpunit-unit.xml` (unit tests only, no WordPress dependency)
- Alternative config: `phpunit.xml.dist` (integration tests with WordPress)

**Assertion Library:**
- PHPUnit built-in assertions (`assertEquals`, `assertTrue`, `assertFalse`, etc.)
- Yoast PHPUnit Polyfills for compatibility (`yoast/phpunit-polyfills: ^1.0`)

**Run Commands:**
```bash
# Run all unit tests (silent)
composer test

# Run tests with verbose output
composer test:unit

# Generate HTML coverage report (outputs to coverage/)
composer test:coverage

# Setup test database (one-time for integration tests)
composer test:setup-db
```

## Test File Organization

**Location:**
- Co-located in `tests/` directory parallel to source code structure
- Unit tests: `tests/Unit/`
- Fixture structure mirrors source: `tests/Unit/Services/`, `tests/Unit/Views/`, etc.

**Naming:**
- Test class: `{ComponentName}Test` or `Test{ComponentName}`
- Test file: `{ComponentName}Test.php` or `class-{component-name}-test.php`
- Examples: `ProductServiceBasicTest.php`, `OrderItemsDisplayTest.php`

**Structure:**
```
tests/
├── bootstrap-unit.php              # PHPUnit bootstrap (pure PHP, no WP)
├── bootstrap.php                   # Alternative bootstrap (with WordPress)
├── Unit/
│   ├── Services/
│   │   ├── ProductServiceBasicTest.php
│   │   └── index.php
│   ├── Views/
│   │   ├── OrderItemsDisplayTest.php
│   │   └── index.php
│   └── index.php
└── test-sample.php                 # Legacy test files
```

## Test Structure

**Suite Organization:**

```php
<?php
namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class ProductServiceBasicTest extends TestCase
{
    /**
     * 測試：計算折扣後的價格
     */
    public function test_calculate_discounted_price()
    {
        // Arrange
        $originalPrice = 100;
        $discountPercent = 10;
        $expected = 90;

        // Act
        $result = $this->calculateDiscountedPrice($originalPrice, $discountPercent);

        // Assert
        $this->assertEquals($expected, $result);
    }

    /**
     * Helper method (private)
     */
    private function calculateDiscountedPrice($price, $discountPercent)
    {
        return $price - ($price * $discountPercent / 100);
    }
}
```

**Patterns:**
- **Setup/Teardown**: Use `setUp()` and `tearDown()` for test initialization (not shown in examples, can be added as needed)
- **Arrange-Act-Assert (AAA)**: Clear test structure with comments
- **Test naming**: `test_` prefix followed by descriptive name in snake_case (e.g., `test_calculate_discounted_price_full_discount`)
- **Method privacy**: Helper methods are private (`private function formatItemsDisplay()`)

## Mocking

**Framework:**
- PHPUnit built-in mocking: `createMock()`, `getMockBuilder()`
- No explicit mock library dependency detected

**Patterns:**
- **Services not mocked** in current codebase: helpers and local functions only
- **Composable mocking**: Not observed in current test files (focus on pure logic)

**What to Mock:**
- External API calls
- Database queries (when testing service layer logic separately)
- WordPress functions (`get_current_user()`, `wp_get_current_user()`)
- File I/O operations

**What NOT to Mock:**
- Business logic methods (test actual implementation)
- Simple utility functions
- Data transformation functions
- Calculation functions

## Fixtures and Factories

**Test Data:**

Test data provided inline in tests:
```php
public function test_single_item_display()
{
    $order = [
        'items' => [
            [
                'product_name' => 'LOGO',
                'quantity' => 50
            ]
        ]
    ];

    $result = $this->formatItemsDisplay($order);
    $this->assertEquals('LOGO x50', $result);
}
```

**Data Structures:**
- Arrays for simple test data
- PHPUnit's `@dataProvider` for parameterized tests (not used in examples, but supported)

**Location:**
- Test data inline in test methods
- Helper methods for common data setup (private methods in test class)
- No separate factory/fixture files observed

## Coverage

**Requirements:**
- Target: Service classes in `includes/Services/`
- Coverage config in `phpunit-unit.xml`:
  ```xml
  <coverage>
      <include>
          <directory suffix=".php">./includes/Services/</directory>
      </include>
  </coverage>
  ```
- No strict percentage enforced (coverage optional, not gated)

**View Coverage:**
```bash
# Generate coverage report
composer test:coverage

# Open HTML report
open coverage/index.html
```

## Test Types

**Unit Tests:**
- **Scope**: Pure PHP business logic, no WordPress dependency
- **Bootstrap**: `tests/bootstrap-unit.php` (simple Composer autoloader)
- **Approach**: Test isolated methods with input/output validation
- **Location**: `tests/Unit/Services/`, `tests/Unit/Views/`
- **Example**: `ProductServiceBasicTest.php` tests discount calculation, quantity validation, price formatting
- **Running**: `composer test` uses `phpunit-unit.xml`

**Integration Tests:**
- **Status**: Infrastructure exists but not actively used
- **Bootstrap**: `tests/bootstrap.php` (includes WordPress)
- **Database**: `wordpress_test` (Local by Flywheel)
- **Setup**: `composer test:setup-db` initializes test database
- **Not observed**: No active integration test examples in codebase

**E2E Tests:**
- **Status**: Not used
- **Alternative**: Manual testing in Local by Flywheel development environment
- **Testing endpoint**: `https://test.buygo.me` or `http://buygo.local`

## Common Patterns

**Boundary Testing:**
```php
/**
 * Test discount calculation - edge cases
 */
public function test_calculate_discounted_price_no_discount()
{
    $result = $this->calculateDiscountedPrice(100, 0);
    $this->assertEquals(100, $result);
}

public function test_calculate_discounted_price_full_discount()
{
    $result = $this->calculateDiscountedPrice(100, 100);
    $this->assertEquals(0, $result);
}
```

**Array/Collection Testing:**
```php
public function test_multiple_items_display()
{
    $order = [
        'items' => [
            ['product_name' => '商品A', 'quantity' => 10],
            ['product_name' => '商品B', 'quantity' => 5]
        ]
    ];

    $result = $this->formatItemsDisplay($order);
    $this->assertEquals('商品A x10, 商品B x5', $result);
}

public function test_empty_items_shows_total()
{
    $order = ['items' => [], 'total_items' => 5];
    $result = $this->formatItemsDisplay($order);
    $this->assertEquals('5 件', $result);
}
```

**Null/Missing Data Testing:**
```php
public function test_null_items_shows_total()
{
    $order = ['total_items' => 10];
    $result = $this->formatItemsDisplay($order);
    $this->assertEquals('10 件', $result);
}

public function test_missing_product_name_shows_unknown()
{
    $order = ['items' => [['quantity' => 5]]];  // No product_name
    $result = $this->formatItemsDisplay($order);
    $this->assertEquals('未知商品 x5', $result);
}
```

**String Truncation Testing:**
```php
public function test_long_text_truncation()
{
    $order = [
        'items' => [
            ['product_name' => '超長商品名稱一二三四五六七八九十', 'quantity' => 10]
        ]
    ];

    $result = $this->formatItemsDisplay($order, 50);

    // Verify truncated + ellipsis
    $this->assertLessThanOrEqual(53, strlen($result));
    $this->assertStringEndsWith('...', $result);
}
```

**Localization Testing:**
```php
public function test_mixed_language_product_names()
{
    $order = [
        'items' => [
            ['product_name' => 'iPhone 15 Pro', 'quantity' => 2],
            ['product_name' => 'MacBook Pro 16吋', 'quantity' => 1]
        ]
    ];

    $result = $this->formatItemsDisplay($order, 100);
    $expected = 'iPhone 15 Pro x2, MacBook Pro 16吋 x1';
    $this->assertEquals($expected, $result);
}
```

## Test Bootstrap

**Unit Test Bootstrap (`tests/bootstrap-unit.php`):**
```php
<?php
// Composer autoloader
require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

// Define plugin constants
if (!defined('BUYGO_PLUS_ONE_VERSION')) {
    define('BUYGO_PLUS_ONE_VERSION', '0.0.1');
}

if (!defined('BUYGO_PLUS_ONE_PLUGIN_DIR')) {
    define('BUYGO_PLUS_ONE_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
}

echo "PHPUnit 單元測試環境已載入\n";
```

**PHPUnit Configuration (`phpunit-unit.xml`):**
```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap-unit.php"
    backupGlobals="false"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
>
    <testsuites>
        <testsuite name="BuyGo Plus One - Unit Tests">
            <directory>./tests/Unit/</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">./includes/Services/</directory>
        </include>
    </coverage>
</phpunit>
```

## Composer Scripts

**Defined in `composer.json`:**
```json
{
    "scripts": {
        "test": "phpunit -c phpunit-unit.xml",
        "test:unit": "phpunit -c phpunit-unit.xml --verbose",
        "test:coverage": "phpunit -c phpunit-unit.xml --coverage-html coverage",
        "test:setup-db": "php bin/setup-test-db.php"
    }
}
```

## Database Testing

**WordPress Integration Test Database:**
- Database: `wordpress_test`
- User: `root`
- Password: `root`
- Socket: `/Users/fishtv/Library/Application Support/Local/run/oFa4PFqBu/mysql/mysqld.sock`
- Configured in: `phpunit.xml.dist`

**Setup:**
```bash
composer test:setup-db
```

## Namespace Structure

**Test Namespaces:**
- Pattern: `BuyGoPlus\Tests\Unit\{Category}`
- Examples:
  - `namespace BuyGoPlus\Tests\Unit\Services;`
  - `namespace BuyGoPlus\Tests\Unit\Views;`

**Source Namespaces:**
- Pattern: `BuyGoPlus\{Category}`
- Examples:
  - `namespace BuyGoPlus\Services;`
  - `namespace BuyGoPlus\Api;`

## Test Naming

**Method Naming Convention:**
- Prefix: `test_`
- Format: `test_what_you_are_testing_expected_outcome`
- Examples:
  - `test_calculate_discounted_price`
  - `test_calculate_discounted_price_no_discount`
  - `test_single_item_display`
  - `test_missing_product_name_shows_unknown`
  - `test_long_text_truncation`

**Test Class Naming:**
- Suffix: `Test`
- Pattern: `{ClassName}Test`
- Examples:
  - `ProductServiceBasicTest`
  - `OrderItemsDisplayTest`

---

*Testing analysis: 2026-01-28*
