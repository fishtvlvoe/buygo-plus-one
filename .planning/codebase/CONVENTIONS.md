# Coding Conventions

**Analysis Date:** 2026-01-28

## Naming Patterns

**Files:**
- **PHP Services**: `class-service-name.php` (kebab-case, e.g., `class-product-service.php`)
- **PHP APIs**: `class-*-api.php` (kebab-case, e.g., `class-products-api.php`)
- **Vue/JS Components**: PascalCase in code, referenced via ids (e.g., `#products-page-template`)
- **CSS**: lowercase with hyphens, prefixed by page (e.g., `products-header.css`)

**PHP Classes:**
```php
namespace BuyGoPlus\Services;

class ProductService {
    // Class name: PascalCase
}

class OrderService {
    // File: class-order-service.php
    // Namespace: BuyGoPlus\Services
}
```

**Functions (PHP):**
- camelCase for private/protected methods: `private function formatOrder()`
- snake_case for WordPress hooks: `add_action('buygo_daily_cleanup')`
- Explicit, descriptive names: `getProductsWithOrderCount()` not `loadData()`

**Variables (PHP):**
- camelCase for instance variables: `$this->debugService`, `$this->shippingStatusService`
- camelCase for local variables: `$productId`, `$orderService`, `$isAdmin`
- UPPERCASE for constants: `const DB_VERSION = '1.2.0'`

**JavaScript Variables:**
- Explicit names with context: `const productsData = []` not `const data = []`
- Boolean prefixes: `isLoading`, `showMobileSearch`, `isSidebarCollapsed`
- Ref naming: `const currentView = ref('list')`, `const productsLoading = ref(false)`
- Composable names: `useApi()`, `useCurrency()`, `usePermissions()`

**CSS Classes:**
- Page-specific prefixes (mandatory): `products-header`, `orders-modal`, `customers-card`
- Never generic names: ❌ `.header`, `.modal`, `.card` → ✅ `.products-header`, `.orders-modal`
- Mobile/responsive variants: `md:pl-0`, `xs:p-4` (Tailwind utilities)

## Code Style

**Formatting:**
- **Tool**: No explicit linter configured (auto-format not enforced)
- **Indentation**: 4 spaces (PHP), 2-4 spaces (JavaScript)
- **Line Length**: No hard limit observed, but keep < 100 chars for readability

**Spacing:**
- Two blank lines between class methods
- One blank line between logical blocks within methods
- Space after control structures: `if ()`, `for ()`, `foreach ()`

**PHP Conventions:**
```php
// Opening brace on same line for classes/methods
class ProductService {
    public function getProducts() {
        // Method body
        $result = [];

        // Logical grouping with blank lines
        if ($condition) {
            // Logic here
        }

        return $result;
    }
}
```

**JavaScript/Vue Conventions:**
```javascript
// Destructuring from Vue
const { ref, reactive, computed, onMounted } = Vue;

// Composable pattern
const { formatPrice, systemCurrency } = useCurrency();

// State organization in setup()
const ref1 = ref(false);
const ref2 = ref([]);
const computed1 = computed(() => {});
const onMounted(() => {});
```

## Linting

**No explicit linter enforced.** Code follows conventions observed in codebase:
- PHPUnit for testing (PHP)
- Console.log for debugging (JavaScript)
- Manual code review via CODING-STANDARDS.md (documented in `docs/development/CODING-STANDARDS.md`)

## Import Organization

**PHP PSR-4 Namespaces:**
```php
// Order:
// 1. Namespace declaration
namespace BuyGoPlus\Services;

// 2. External framework imports
use FluentCart\App\Models\ProductVariation;
use FluentCart\App\Models\Product;

// 3. Internal package imports
use BuyGoPlus\Services\DebugService;
```

**JavaScript Global Variables (WordPress Environment):**
- No ES6 import/export (WordPress compatibility)
- Access via global window object: `window.buygoWpNonce`, `window.Vue`, `window.fetch`
- Composables as global functions: `function useApi()`, `function useCurrency()`
- Components as global objects: `const ProductsPageComponent = { ... }`

**API Namespace:**
- All REST endpoints: `/wp-json/buygo-plus-one/v1`
- API class registration: `new Products_API()`, `new Orders_API()`

## Error Handling

**PHP Pattern - Try/Catch with Logging:**
```php
try {
    // Business logic
    $result = $this->processData($data);
} catch (\Exception $e) {
    $this->debugService->log(
        'ServiceName',
        '操作失敗',
        ['error' => $e->getMessage()],
        'error'
    );
    throw new \Exception('User-facing message: ' . $e->getMessage());
}
```

**Logging Strategy:**
- `$this->debugService->log()` for application events
- `error_log()` for WordPress debug.log
- File logging: `file_put_contents($log_file, $message . "\n", FILE_APPEND)`
- Format: `[YYYY-MM-DD HH:mm:ss] [CONTEXT] Message`

**Permission Checking:**
```php
public static function check_permission(): bool {
    if (!is_user_logged_in()) {
        return false;
    }

    return current_user_can('manage_options') ||
           current_user_can('buygo_admin') ||
           current_user_can('buygo_helper');
}
```

**API Error Responses:**
```javascript
// Success
{ success: true, message: '操作成功', data: {...} }

// Failure
{ success: false, message: '錯誤訊息' }
```

**JavaScript Error Handling:**
```javascript
const request = async (url, options = {}) => {
    try {
        const response = await window.fetch(url, fetchConfig);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || '操作失敗');
        }

        return result;
    } catch (err) {
        error.value = err.message;
        if (showError && window.showToast) {
            window.showToast(err.message, 'error');
        }
        throw err;
    }
};
```

## Comments

**When to Comment:**
- Method/function docblocks (required for public methods)
- Complex logic requiring explanation
- WordPress-specific workarounds
- TODO/FIXME for incomplete features

**PHPDoc Format:**
```php
/**
 * 取得商品列表（包含下單數量）
 *
 * @param array $filters 篩選條件
 * @param string $viewMode 顯示模式 frontend|backend
 * @return array
 */
public function getProductsWithOrderCount(array $filters = [], string $viewMode = 'frontend'): array
```

**JavaScript Comment Format:**
```javascript
/**
 * API Composable - 統一 API 調用管理
 *
 * 功能:
 * - 自動管理 wpNonce 和 headers
 * - 統一 loading/error 狀態管理
 *
 * @version 1.0.0
 * @date 2026-01-24
 */
```

**Inline Comments (Chinese):**
- Use for complex business logic
- Explain "why" not "what"
- Example: `// 權限篩選 (暫時移除 post_author 過濾，因為 REST API 的登入狀態不穩定)`

## Function Design

**Size Guidelines:**
- Keep methods < 50 lines when possible
- Separate concerns: data retrieval, processing, formatting
- Break into private helpers for reusable logic

**Parameters:**
- Prefer explicit named parameters over arrays: `getOrders(int $page, int $perPage)`
- Use type declarations: `function updateProduct(int $productId, array $data): bool`
- Defaults for optional params: `function getOrders(array $params = [])`

**Return Values:**
- Explicit return types: `: array`, `: bool`, `: \WP_User|null`
- Consistent return structure (especially for APIs):
  ```php
  return [
      'orders' => [],
      'total' => 0,
      'page' => 1,
      'per_page' => 10,
      'pages' => 0
  ];
  ```
- Null for "not found": `return null;` instead of empty array

**Function Naming Conventions:**
- Getters: `getProducts()`, `getOrders()`
- Setters: `updateProduct()`, `saveSettings()`
- Validation: `validateProductQuantity()`
- Transformers: `formatOrder()`, `formatItemsDisplay()`
- Checkers: `isAdmin()`, `hasPermission()`

## Module Design

**Exports (PHP):**
- Namespaced classes only: no global functions in services
- All dependencies via constructor injection: `public function __construct()`
- Singleton pattern for shared services: `DebugService::get_instance()`

**Singleton Implementation:**
```php
class DebugService {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Prevent external instantiation
    }
}
```

**Barrel Files:**
- `includes/index.php` and subdirectory `index.php` files exist but contain only comments
- No re-exports or aggregation pattern used
- Each component imports directly from target file

**Service Layer Pattern (located in `includes/services/`):**
- 16+ service classes: ProductService, OrderService, ShipmentService, etc.
- Each service handles specific domain logic
- Services injected via constructor: `new ProductService()`
- Logging via DebugService: `$this->debugService->log()`

**API Layer Pattern (located in `includes/api/`):**
- REST endpoints using WordPress `register_rest_route()`
- Permission checking via `API::check_permission()`
- Input validation with `sanitize_callback` and `validate_callback`
- Consistent response format: `{ success: bool, message: string, data: mixed }`

## Vue 3 Component Structure

**Template Pattern:**
```javascript
const PageComponent = {
    name: 'ProductsPage',
    components: {
        'smart-search-box': BuyGoSmartSearchBox,
        'pagination': BuyGoPagination
    },
    template: '#products-page-template',
    setup() {
        const { ref, reactive, computed, onMounted } = Vue;

        // State organization
        const uiState = ref(...);
        const dataState = ref(...);
        const computed1 = computed(() => {});

        const loadData = async () => { /* ... */ };
        const onMounted = () => { /* ... */ };

        return {
            // UI state
            uiState,
            // Data
            dataState,
            // Methods
            loadData
        };
    }
};

// Register component globally
window.ProductsPageComponent = PageComponent;
```

**HTML Template Structure (Critical):**
- Header must be sibling to content, not nested
- Multiple views (list, detail, edit) at same level
- Use `v-show="currentView === 'list'"` for view switching
- Required structure for layout:
  ```html
  <main>
      <header v-show="currentView === 'list'">...</header>
      <div class="flex-1 overflow-auto">
          <div v-show="currentView === 'list'">List content</div>
          <div v-show="currentView === 'detail'">Detail content</div>
      </div>
  </main>
  ```

## WordPress Integration

**Nonce Handling:**
- Generated server-side: `wp_create_nonce('wp_rest')`
- Stored in window: `window.buygoWpNonce`
- Sent via header: `'X-WP-Nonce': wpNonce`

**Permission Checks:**
- REST endpoints: `'permission_callback' => [API::class, 'check_permission']`
- Admin pages: `current_user_can('manage_options')`
- BuyGo-specific: `current_user_can('buygo_admin')`, `current_user_can('buygo_helper')`

**Database Access:**
- Global `$wpdb` for raw queries: `global $wpdb;`
- Parameterized queries: `$wpdb->prepare("SELECT * FROM ... WHERE id = %d", $id)`
- FluentCart models for ORM: `Product::query()->where(...)`

---

*Convention analysis: 2026-01-28*
