# Technology Stack

**Analysis Date:** 2026-01-28

## Languages

**Primary:**
- PHP 7.4+ - WordPress plugin backend logic
- JavaScript (ES6+) - Frontend and Vue components
- HTML/CSS - Frontend markup and styling

**Secondary:**
- SQL - Database queries via wpdb

## Runtime

**Environment:**
- WordPress 5.8+ - CMS runtime platform
- PHP-FPM - Application server

**Package Manager:**
- Composer - PHP dependency management
- CDN imports - JavaScript libraries (no npm/webpack build)

## Frameworks

**Core:**
- WordPress REST API - Backend HTTP API framework
- Vue 3 - Frontend component framework (CDN: `https://unpkg.com/vue@3/dist/vue.global.js`)
- Tailwind CSS - Utility-first CSS framework (CDN: `https://cdn.tailwindcss.com`)

**Frontend Libraries:**
- VueDraggable 4.1.0 - Drag-and-drop component (`https://cdn.jsdelivr.net/npm/vuedraggable@4.1.0/dist/vuedraggable.umd.min.js`)
- SortableJS 1.15.0 - Sorting library for VueDraggable (`https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js`)
- Google Fonts - Typography (Poppins, Open Sans)

**Testing:**
- PHPUnit 9.0 - Unit testing framework
- Yoast PHPUnit Polyfills 1.0 - WordPress compatibility layer

**Build/Dev:**
- No build system (raw files, no bundling)
- No CSS preprocessor (Tailwind utilities directly in templates)

## Key Dependencies

**Critical:**
- `phpunit/phpunit` ^9.0 - Unit test runner
- `yoast/phpunit-polyfills` ^1.0 - WordPress test utilities

**Infrastructure:**
- WordPress core - Database abstraction via `$wpdb` global
- FluentCart - E-commerce integration (integrated via wp-json, not composer)
- LINE Messaging API - Notification service (HTTP client via `wp_remote_post`)

## Configuration

**Environment:**
- WordPress `wp-config.php` - Database and environment variables
- Plugin constants defined in `buygo-plus-one.php`:
  - `BUYGO_PLUS_ONE_VERSION` - Plugin version
  - `BUYGO_PLUS_ONE_PLUGIN_DIR` - Plugin directory path
  - `BUYGO_PLUS_ONE_PLUGIN_URL` - Plugin directory URL
  - `BUYGO_PLUS_ONE_PLUGIN_FILE` - Main plugin file path

**Build:**
- No build configuration files (Tailwind is runtime via CDN)
- `phpunit-unit.xml` - PHPUnit configuration for unit tests
- `phpunit.xml.dist` - PHPUnit distribution configuration

**Key Configurations:**
- WordPress nonce (`wp_create_nonce("wp_rest")`) for API authentication
- Tailwind config inline in `template.php` with theme extensions (primary: #2563EB, accent: #F97316)

## Database

**Type:**
- MySQL (via WordPress wpdb abstraction)

**Tables Created by Plugin:**
- `wp_buygo_debug_logs` - Debug logging
- `wp_buygo_notification_logs` - Notification history
- `wp_buygo_workflow_logs` - Workflow tracking
- `wp_buygo_line_bindings` - LINE user bindings
- `wp_buygo_helpers` - Helper role/permission tracking
- `wp_buygo_shipments` - Shipment records
- `wp_buygo_shipment_items` - Shipment item details
- `wp_buygo_webhook_logs` - LINE webhook logging
- `wp_buygo_order_status_history` - Order status changes

**Version:** 1.2.0 (auto-upgraded on plugin activation)

## Platform Requirements

**Development:**
- Local by Flywheel (WordPress environment)
- PHP 7.4+ with CLI support
- Composer for dependency management
- Code editor (VS Code recommended)

**Production:**
- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+
- FluentCart plugin installed and active
- LINE Messaging API credentials (Channel ID, Channel Secret)

## API Architecture

**REST API:**
- Base namespace: `buygo-plus-one/v1`
- Authentication: WordPress nonce (`X-WP-Nonce` header)
- Response format: JSON

**Endpoints Structure:**
- `includes/api/` - All REST API endpoint handlers
  - Products API: `class-products-api.php`
  - Orders API: `class-orders-api.php`
  - Shipments API: `class-shipments-api.php`
  - Settings API: `class-settings-api.php`
  - Customers API: `class-customers-api.php`
  - LINE Webhook API: `class-line-webhook-api.php`

## File Organization

```
buygo-plus-one-dev/
├── composer.json                  # PHP dependencies
├── buygo-plus-one.php            # Main plugin entry point
├── includes/
│   ├── class-plugin.php          # Plugin initialization (Singleton)
│   ├── class-database.php        # Table creation and upgrades
│   ├── services/                 # Business logic layer (15+ services)
│   ├── api/                      # REST API endpoints (11 APIs)
│   └── views/                    # Vue template and composables
├── admin/
│   ├── class-admin.php           # Admin hooks and enqueue
│   ├── js/                       # Vue components and utilities
│   ├── css/                      # Admin stylesheets
│   └── partials/                 # Admin page templates
├── components/                   # Reusable Vue components
├── design-system/                # Global design system CSS/utilities
└── tests/                        # PHPUnit test suite
```

---

*Stack analysis: 2026-01-28*
