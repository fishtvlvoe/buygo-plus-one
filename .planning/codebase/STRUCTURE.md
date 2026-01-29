# Codebase Structure

**Analysis Date:** 2026-01-28

## Directory Layout

```
buygo-plus-one-dev/
├── buygo-plus-one.php                  # Plugin entry point (activation, deactivation hooks)
├── index.php                           # Empty security file
├── includes/
│   ├── class-plugin.php                # Plugin singleton, dependency loading
│   ├── class-database.php              # Table creation and schema management
│   ├── class-database-checker.php      # Database validation
│   ├── class-routes.php                # URL rewrite rules and template routing
│   ├── class-short-link-routes.php     # Short URL route handling
│   ├── class-loader.php                # Hook/action loader
│   ├── class-plugin-compatibility.php  # Version compatibility checks
│   ├── class-fluentcart-product-page.php  # FluentCart integration
│   ├── class-fluent-community.php      # FluentCommunity integration
│   ├── services/                       # Business logic layer (15+ classes)
│   │   ├── class-allocation-service.php       # Stock allocation with transactions
│   │   ├── class-debug-service.php            # Logging to file and database
│   │   ├── class-export-service.php           # Data export functionality
│   │   ├── class-fluentcart-service.php       # FluentCart data access
│   │   ├── class-line-binding-receipt.php     # LINE QR receipt display
│   │   ├── class-line-service.php             # LINE API communication
│   │   ├── class-line-order-notifier.php      # LINE order notifications
│   │   ├── class-line-webhook-handler.php     # Webhook event processing
│   │   ├── class-notification-templates.php   # Message template management
│   │   ├── class-order-service.php            # Order queries and formatting
│   │   ├── class-product-data-parser.php      # Product data transformation
│   │   ├── class-product-service.php          # Product CRUD and order count
│   │   ├── class-settings-service.php         # Settings and role management
│   │   ├── class-shipment-service.php         # Shipment management
│   │   ├── class-shipping-status-service.php  # Shipping status tracking
│   │   ├── class-webhook-logger.php           # Webhook logging
│   │   └── index.php                          # Empty security file
│   ├── api/                            # REST API endpoints (11+ classes)
│   │   ├── class-api.php                      # Base API with permission check
│   │   ├── class-products-api.php             # GET/POST/PUT/DELETE products
│   │   ├── class-orders-api.php               # GET orders, POST order actions
│   │   ├── class-shipments-api.php            # Shipment CRUD operations
│   │   ├── class-customers-api.php            # Customer list and details
│   │   ├── class-global-search-api.php        # Cross-module search
│   │   ├── class-debug-api.php                # Debug data endpoints
│   │   ├── class-settings-api.php             # Settings get/update
│   │   ├── class-keywords-api.php             # Autocomplete keywords
│   │   ├── class-line-webhook-api.php         # LINE webhook receiver
│   │   ├── class-liff-login-api.php           # LIFF login handler
│   │   └── index.php                          # Empty security file
│   ├── admin/                          # WordPress admin interface
│   │   ├── class-admin.php             # Admin page initialization
│   │   ├── class-settings-page.php     # Settings page UI and handlers
│   │   ├── class-debug-page.php        # Debug page with diagnostics
│   │   └── (other debug/check files)
│   ├── views/                          # Frontend templates and composables
│   │   ├── template.php                # Main HTML template (Vue mount point)
│   │   ├── composables/                # Vue 3 composables
│   │   │   └── useCurrency.js          # Currency formatting logic
│   │   ├── pages/                      # Page-specific Vue components
│   │   ├── assets/                     # Images and static assets
│   │   └── index.php
│   ├── core/                           # Core helper classes
│   │   ├── class-buygo-plus-core.php   # Core utility methods
│   │   └── index.php
│   ├── diagnostics/                    # WP-CLI diagnostic commands
│   │   ├── class-diagnostics-command.php
│   │   ├── class-product-diagnostics.php
│   │   └── index.php
│   ├── templates/                      # Email/message templates
│   └── index.php
├── admin/                              # Frontend UI files
│   ├── js/                             # Vue components and utilities
│   │   ├── RouterMixin.js              # Vue routing helpers
│   │   ├── DesignSystem.js             # Design system initialization
│   │   ├── admin-settings.js           # Settings page logic
│   │   ├── components/
│   │   │   ├── ProductsPage.js         # Products list/edit component
│   │   │   ├── OrdersPage.js           # Orders list component
│   │   │   ├── CustomersPage.js        # Customers list component
│   │   │   ├── ShipmentProductsPage.js # Shipment products component
│   │   │   └── ShipmentDetailsPage.js  # Shipment details component
│   ├── css/                            # Page-specific styles
│   │   ├── products.css
│   │   ├── orders.css
│   │   ├── customers.css
│   │   └── (other page styles)
│   └── partials/                       # Page templates (loaded by template.php)
│       ├── products.php                # Products page with Vue component def
│       ├── orders.php                  # Orders page
│       ├── customers.php               # Customers page
│       ├── settings.php                # Settings page
│       ├── shipment-products.php       # Shipment products page
│       ├── shipment-details.php        # Shipment details page
│       ├── dashboard.php               # Dashboard (if exists)
│       └── index.php
├── components/                         # Reusable Vue components
│   ├── shared/
│   │   ├── new-sidebar.php             # Main navigation sidebar
│   │   ├── smart-search-box.php        # Global search box
│   │   ├── page-header.php             # Page header with breadcrumbs
│   │   ├── pagination.php              # Pagination controls
│   │   ├── side-nav.php                # Legacy sidebar
│   │   └── index.php
│   ├── order/
│   │   ├── order-detail-modal.php      # Order detail modal component
│   │   └── index.php
│   └── index.php
├── design-system/                      # Design tokens and component styles
│   ├── index.css                       # Main design system CSS
│   ├── DESIGN-SYSTEM.md                # Design documentation
│   ├── USAGE.md                        # Usage guidelines
│   ├── tokens/                         # CSS custom properties
│   │   ├── colors.css
│   │   ├── typography.css
│   │   ├── spacing.css
│   │   └── (other token files)
│   └── components/                     # Design component styles
├── liff/                               # LINE LIFF app (if applicable)
├── languages/                          # Translation files
├── assets/                             # Global assets
│   ├── js/
│   ├── css/
│   └── images/
├── tests/                              # PHPUnit tests
│   ├── Unit/                           # Unit tests (no WP dependencies)
│   │   ├── Services/
│   │   │   ├── ProductServiceBasicTest.php
│   │   │   └── (other service tests)
│   │   ├── Views/
│   │   │   └── OrderItemsDisplayTest.php
│   │   └── index.php
│   ├── bootstrap-unit.php              # Test setup (no WordPress)
│   ├── bootstrap.php                   # Test setup (with WordPress)
│   └── (integration/e2e tests)
├── bin/                                # Utility scripts
│   └── setup-test-db.php               # Test database initialization
├── docs/                               # Project documentation
│   ├── INDEX.md                        # Documentation index
│   ├── development/
│   │   ├── CODING-STANDARDS.md
│   │   ├── ARCHITECTURE.md
│   │   └── (other dev docs)
│   ├── planning/
│   │   ├── IMPLEMENTATION-CHECKLIST.md
│   │   ├── TODO-BUYGO.md
│   │   └── (other planning docs)
│   ├── bugfix/
│   │   └── BUGFIX-CHECKLIST.md
│   └── (other docs)
├── .planning/                          # GSD phase planning
│   ├── phases/                         # Phase-specific plans
│   │   ├── 01-*.md
│   │   └── (other phase docs)
│   └── codebase/                       # Codebase analysis (generated)
│       ├── ARCHITECTURE.md             # This file
│       ├── STRUCTURE.md                # Directory structure
│       ├── CONVENTIONS.md              # Code conventions
│       ├── TESTING.md                  # Testing patterns
│       ├── STACK.md                    # Tech stack
│       ├── INTEGRATIONS.md             # External integrations
│       └── CONCERNS.md                 # Tech debt and issues
├── composer.json                       # PHP dependencies
├── composer.lock                       # Locked versions
├── package.json                        # (if applicable)
├── .phpunit.result.cache               # Test results
├── CHANGELOG.md                        # Release notes
├── README.md                           # Project readme
└── CLAUDE.md                           # Claude Code project guide
```

## Directory Purposes

**includes/services/:**
- Purpose: Business logic abstraction layer
- Contains: Service classes implementing CRUD operations, business rules, integrations
- Key files: ProductService, OrderService, AllocationService, LineService, SettingsService
- Pattern: Single responsibility, dependency injection via constructor, public methods return arrays or WP_Error

**includes/api/:**
- Purpose: WordPress REST API endpoints
- Contains: API classes registering routes, handling requests, calling services
- Key files: API (base), Products_API, Orders_API, Shipments_API, Customers_API, Line_Webhook_API
- Pattern: register_routes() method defines endpoints, callback methods validate and call services

**admin/js/components/:**
- Purpose: Vue 3 page components for frontend UI
- Contains: ProductsPage, OrdersPage, CustomersPage, ShipmentProductsPage, ShipmentDetailsPage
- Key feature: Communicate with REST API via fetch() with X-WP-Nonce header
- Pattern: Component receives page type, manages local state, emits actions to API

**admin/partials/:**
- Purpose: HTML page templates that define Vue components
- Contains: products.php, orders.php, customers.php, settings.php, shipment-*.php
- Loaded by: template.php after URL routing
- Pattern: Define custom Vue component with template, data(), methods, mounted()

**components/shared/:**
- Purpose: Reusable UI components used across all pages
- Key files: new-sidebar.php (navigation), smart-search-box.php (global search), page-header.php (breadcrumbs), pagination.php
- Pattern: Template definitions with Vue component lifecycle

**design-system/:**
- Purpose: Centralized design tokens and component styles
- Contains: CSS variables, Tailwind extensions, component-specific styles
- Key files: index.css (main), tokens/* (theme values), components/* (component styles)
- Usage: Applied globally in template.php, overrideable per-page

## Key File Locations

**Entry Points:**
- `buygo-plus-one.php`: Main plugin file - activation/deactivation hooks, plugin initialization
- `includes/class-plugin.php`: Plugin singleton instance, dependency loading, hook registration
- `includes/class-routes.php`: URL routing for `/buygo-portal/*` paths

**Configuration:**
- `composer.json`: PHP package dependencies
- `design-system/index.css`: Global design system stylesheet
- `includes/views/template.php`: Main HTML template with Vue mount point

**Core Logic:**
- `includes/services/`: 15+ service classes for business logic
- `includes/api/`: 11+ REST API endpoint classes
- `includes/class-database.php`: Database table definitions and migrations

**Frontend Components:**
- `admin/js/components/`: Vue 3 page components
- `admin/partials/`: Page templates with component definitions
- `components/shared/`: Reusable UI components
- `includes/views/composables/useCurrency.js`: Currency formatting logic

**Testing:**
- `tests/Unit/Services/`: Unit test files for services
- `tests/bootstrap-unit.php`: PHPUnit configuration (no WordPress)
- `tests/bootstrap.php`: PHPUnit configuration (with WordPress)

## Naming Conventions

**Files:**
- PHP classes: `class-{module}-{purpose}.php` (e.g., `class-product-service.php`, `class-products-api.php`)
- Vue components: `{Module}{Purpose}Page.js` (e.g., `ProductsPage.js`, `OrdersPage.js`)
- Page templates: `{page-slug}.php` (e.g., `products.php`, `shipment-details.php`)
- CSS files: `{page-name}.css` (e.g., `products.css`, `customers.css`)

**Directories:**
- Module-based organization: `services/`, `api/`, `admin/`, `components/`
- Type-based sub-directories: `components/shared/`, `components/order/`, `admin/js/`, `admin/css/`
- Follows WordPress plugin structure conventions

**PHP Classes:**
- Namespace: `BuyGoPlus\`, `BuyGoPlus\Services\`, `BuyGoPlus\Api\`
- Class name: PascalCase with clear purpose (ProductService, Orders_API)
- Methods: camelCase (getProductsWithOrderCount, allocateStock)
- Constants: SCREAMING_SNAKE_CASE (DB_VERSION)

**Vue Components:**
- Component name: `{PascalCase}Page` or `{PascalCase}Component` (ProductsPage, SmartSearchBox)
- Data properties: camelCase (currentPage, showMobileSearch, selectedItems)
- Methods: camelCase (handleSearch, loadData, toggleCurrency)
- Emitted events: kebab-case (@search, @select, @update-status)

**CSS Classes:**
- Page-specific prefix: `.products-`, `.orders-`, `.customers-` (e.g., `.products-header`, `.orders-list`)
- Component scope: `.{component-name}-{element}` (e.g., `.smart-search-box-input`)
- Design tokens: CSS custom properties `--color-primary`, `--spacing-unit`

**REST API Routes:**
- Pattern: `/wp-json/buygo-plus-one/v1/{resource}/{action}`
- Examples: `/products`, `/orders/{id}`, `/shipments/list`, `/global-search`

## Where to Add New Code

**New Feature (e.g., New Page):**
1. **Service layer**: Create `includes/services/class-{feature}-service.php` with business logic
2. **API layer**: Create `includes/api/class-{feature}-api.php` with REST endpoint
3. **Frontend**: Create `admin/js/components/{Feature}Page.js` Vue component
4. **Template**: Create `admin/partials/{feature}.php` page template
5. **Routes**: Register route in `includes/class-routes.php` if new URL needed
6. **Styles**: Create `admin/css/{feature}.css` for page-specific styles

**New Component/Module:**
- Shared component: Add to `components/shared/{component-name}.php`
- Page-specific component: Add to `components/{module}/{component-name}.php` directory
- Vue component definition: Create in adjacent `.js` file if needed
- Style: Add classes prefixed with component name, use design-system tokens

**Utilities/Helpers:**
- Shared functions: Add static methods to `includes/core/class-buygo-plus-core.php`
- Service helpers: Add private methods to relevant service class
- Vue composables: Add to `includes/views/composables/{feature}.js`
- CSS utilities: Add to `design-system/index.css` or relevant token file

**Database Changes:**
- New tables: Add create method in `includes/class-database.php`
- Schema changes: Add upgrade method in `includes/class-database.php::upgrade_tables()`
- Increment `DB_VERSION` constant in `includes/class-plugin.php`

**Tests:**
- Unit tests: Create `tests/Unit/{Module}/{Purpose}Test.php` matching service location
- Test pattern: `{ClassName}BasicTest` or `{ClassName}IntegrationTest`
- Fixtures: Store test data in test class `setUp()` method

## Special Directories

**`.planning/codebase/`:**
- Purpose: Codebase analysis documents generated by GSD mapper
- Generated: Yes
- Committed: Yes
- Files: ARCHITECTURE.md, STRUCTURE.md, CONVENTIONS.md, TESTING.md, STACK.md, INTEGRATIONS.md, CONCERNS.md

**`docs/`:**
- Purpose: Developer documentation, guidelines, architecture notes
- Generated: Manual authoring
- Committed: Yes
- Key files: INDEX.md (navigation), development/*, planning/*, bugfix/*, testing/*, reference/*

**`design-system/`:**
- Purpose: Centralized design tokens and component styles
- Generated: Manual authoring
- Committed: Yes
- Key files: index.css (main stylesheet), tokens/* (CSS variables), components/* (component styles)

**`tests/`:**
- Purpose: PHPUnit unit and integration tests
- Generated: Manual authoring
- Committed: Yes
- Key files: bootstrap-unit.php (no WP), bootstrap.php (with WP), Unit/* (test suites)

**`node_modules/`, `composer.lock`, `.git/`:**
- Purpose: Dependencies, build artifacts, version control
- Generated: Yes
- Committed: composer.lock committed, node_modules not committed

---

*Structure analysis: 2026-01-28*
