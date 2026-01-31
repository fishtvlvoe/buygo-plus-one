# Codebase Concerns

**Analysis Date:** 2026-01-28

## Tech Debt

### Global Search Feature Not Implemented

**Area:** Search functionality
**Files:**
- `admin/partials/settings.php` (line 1028)
- `admin/js/components/CustomersPage.js` (line 218)
- `admin/js/components/ShipmentProductsPage.js` (line 85)

**Issue:** Multiple components have TODO comments indicating global search functionality is planned but not yet implemented. The UI components have placeholder text for search but actual search API integration is missing.

**Impact:**
- Users cannot search across global datasets efficiently
- Customers and shipment product pages have search boxes that don't function
- Future feature blockers depend on this implementation

**Fix approach:**
1. Implement `GlobalSearchAPI` endpoint in `includes/api/class-global-search-api.php`
2. Create unified search service across customers, products, orders
3. Wire up `handleSearch` methods in Vue components to call new API
4. Add proper error handling and debouncing for search queries

---

### Debug Admin Pages Not Finalized

**Area:** Admin/debugging interface
**Files:** `includes/admin/class-debug-page.php` (line 51)

**Issue:** Debug admin page references missing JavaScript and CSS files:
- `debug-admin.js` - not created
- `debug-admin.css` - not created

**Impact:** Debug pages may not render correctly or have poor styling when accessed by administrators.

**Fix approach:**
1. Create `admin/js/debug-admin.js` with debugging utilities
2. Create `admin/css/debug-admin.css` with debug page styles
3. Ensure debug pages follow design system conventions used elsewhere

---

### LIFF ID Configuration Not Automated

**Area:** LINE LIFF integration
**Files:** `includes/api/class-liff-login-api.php` (line 201)

**Issue:** LIFF ID is hardcoded or requires manual WordPress option configuration. The comment indicates intention to read from WordPress settings but implementation is incomplete.

**Impact:**
- Difficult to configure different LIFF IDs for dev/staging/production
- Manual database updates required if LIFF ID changes

**Fix approach:**
1. Migrate LIFF ID to `SettingsService` alongside other LINE credentials
2. Update LIFF login API to read from settings service
3. Add LIFF ID field to settings page admin interface

---

## Known Bugs

### Product Service Debug Logging Left in Production

**Issue:** Excessive error_log() calls remain in code
**Files:** `includes/services/class-product-service.php` (lines 205-283)

**Symptoms:**
- Debug logs fill up server logs even in production
- Performance impact from excessive logging
- Output of `print_r()` in logs can be large

**Files affected:**
- `includes/services/class-product-service.php` - 30+ error_log() calls
- `includes/services/class-debug-service.php` - 7+ error_log() calls

**Workaround:** None - logs will be verbose in production

**Fix approach:**
1. Replace `error_log()` with proper logging service calls (use `WebhookLogger` pattern)
2. Implement debug-mode-only logging (check `WP_DEBUG`)
3. Remove `print_r()` calls and use structured logging instead

---

## Security Considerations

### Webhook Signature Verification - Multiple Header Case Variations Tested

**Area:** LINE Webhook security
**Files:** `includes/api/class-line-webhook-api.php` (lines 134-139)

**Risk:** The code checks multiple header case variations to find the signature:
```php
$signature_alternatives = array(
    'x-line-signature' => ...,
    'X-LINE-Signature' => ...,
    'X-Line-Signature' => ...,
    'HTTP_X_LINE_SIGNATURE' => ...
);
```

**Current mitigation:**
- All variations are checked and the first non-empty one is used
- This is defensive programming for compatibility

**Recommendations:**
1. Document why multiple case variations are needed (server normalization differences)
2. Consider adding warning logs if non-standard header case is detected
3. Enforce lowercase header names in production (LINE sends lowercase)

---

### Channel Secret Storage Requires Encryption

**Area:** LINE credentials
**Files:**
- `includes/admin/class-settings-page.php` (line 70)
- `includes/services/class-settings-service.php`

**Risk:** Channel Secret must be encrypted in database (it's a sensitive authentication credential)

**Current mitigation:**
- Integration with `\BuyGo_Core::settings()->get()` which handles encryption
- WordPress sanitization applied to all inputs

**Recommendations:**
1. Verify encryption is always applied when storing credentials
2. Add test to ensure credentials are stored encrypted, not plain text
3. Review access logs if credentials are exposed in API responses

---

### Debug Mode Environment Detection

**Area:** Development vs production safety
**Files:** `includes/api/class-line-webhook-api.php` (referenced but not yet implemented)

**Risk:** `WP_DEBUG` environment checks determine whether security validations are skipped

**Current status:** Security optimization TODO lists this as planned improvement

**Fix approach:** Implement `is_development_mode()` method that checks:
1. `WP_DEBUG === true`
2. WordPress environment type (`wp_get_environment_type()`)
3. Server name (localhost detection)

---

## Performance Bottlenecks

### Large Admin Pages with Complex Tables

**Issue:** Large number of DOM elements impacts initial render
**Files:**
- `admin/partials/products.php` - 208+ div elements
- `admin/partials/orders.php` - 98+ div elements
- `admin/partials/customers.php` - 91+ div elements
- `admin/partials/shipment-products.php` - 54+ div elements
- `admin/partials/shipment-details.php` - 61+ div elements

**Impact:**
- Initial page load time increases with data volume
- Vue template compilation time grows
- Browser memory usage higher with many elements

**Improvement path:**
1. Implement virtual scrolling for large tables (render only visible rows)
2. Lazy-load modal content instead of rendering all modals upfront
3. Move complex calculations from templates to computed properties
4. Consider pagination for shipment details page

---

### Multiple Webhook Log Checks Without Indexes

**Issue:** Webhook logging queries may be slow under high volume
**Files:**
- `includes/services/class-line-webhook-handler.php` (permission checks)
- Database queries on `wp_buygo_helpers` table

**Impact:**
- Webhook processing time increases with data volume
- Permission checks query unindexed columns

**Improvement path:**
1. Add database indexes on frequently queried columns:
   - `wp_buygo_helpers` table: index on `user_id` and `module`
   - `buygo_webhook_logs` table: index on `created_at`
2. Implement result caching for permission checks (cache for 5 minutes)
3. Use LIMIT 1 on permission existence checks

---

### Error Logging Without Size Limits

**Issue:** Error logs can grow unbounded
**Files:** `includes/services/class-debug-service.php`

**Impact:**
- Disk space exhaustion risk
- Query performance degradation on `buygo_debug_logs` table
- Slow admin interface when viewing logs

**Current implementation:** Has `rotateLogFile()` and `cleanOldLogs()` methods but may not be called regularly

**Improvement path:**
1. Ensure cleanup cron job runs every day
2. Implement log size limits (e.g., keep only 30 days or 100MB)
3. Add warning when log usage exceeds threshold

---

## Fragile Areas

### LINE Webhook Integration - Complex Permission Check

**Component:** LINE webhook handler
**Files:** `includes/services/class-line-webhook-handler.php` (line 95-110)

**Why fragile:**
- Depends on `wp_buygo_helpers` database table structure
- Checks `module='uploadProduct'` permission by string match
- Uses SQL queries directly without abstraction
- Error handling silently returns false on permission denied

**Safe modification approach:**
1. Always test with both old and new `wp_buygo_helpers` data formats
2. Run webhook tests after modifying permission logic
3. Add logging to permission checks for debugging
4. Use try-catch to prevent silent failures

**Test coverage gaps:**
- No unit tests for permission checking logic
- No integration tests with actual webhook payloads
- No test data for various permission states

---

### Database Migration Logic - Version Checking

**Component:** Plugin initialization
**Files:** `includes/class-plugin.php` (database version constant not yet extracted)

**Why fragile:**
- Hardcoded database version number `'1.2.0'` in code
- Migration logic depends on exact version matching
- No rollback mechanism if migration fails

**Safe modification approach:**
1. Extract version to class constant (`Plugin::DB_VERSION`)
2. Always test migration on fresh database first
3. Keep migration history documented
4. Never modify old migration logic, add new migrations instead

**Test coverage gaps:**
- No migration testing from older versions
- No failure recovery testing
- No multi-step migration testing

---

### Vue Template Compilation - Recent Fixes

**Component:** Admin page templates
**Files:**
- `admin/partials/shipment-details.php` - had 8 recent HTML balance fixes
- `admin/partials/customers.php` - had tag nesting issues
- `admin/partials/orders.php` - template compilation errors fixed

**Why fragile:**
- Recently fixed HTML structure issues indicate previous parsing problems
- Large templates increase risk of tag mismatch bugs
- Vue template compilation is strict about structure

**Safe modification approach:**
1. Use automated HTML validation script before committing templates
2. Validate tag balance for: `<div>`, `<main>`, `<section>`, `<template>`
3. Test template rendering in browser after any structural changes
4. Use consistent indentation to make nesting visible

**Test coverage:** Appears to have automated validation now (Python script used for checking)

---

## Scaling Limits

### Webhook Processing - Synchronous Operations

**Resource/System:** LINE webhook handlers
**Files:** `includes/api/class-line-webhook-api.php` (lines 100-117)

**Current capacity:**
- Sequential event processing (events processed one at a time)
- `fastcgi_finish_request()` used for background processing
- WordPress Cron fallback if FastCGI not available

**Limit:** Webhook will timeout if processing takes >30 seconds (typical FastCGI timeout)

**Scaling path:**
1. Implement queue system (database or Redis) for webhook events
2. Process events asynchronously via WP-Cron or background job service
3. Add rate limiting to prevent webhook storm from overwhelming server
4. Monitor webhook processing time and add alerts

---

### Customer and Product Tables - No Pagination on Admin Load

**Resource/System:** Admin pages loading all data at once
**Files:**
- `admin/partials/products.php`
- `admin/partials/customers.php`
- `admin/partials/orders.php`

**Current capacity:** Pages load all matching records from API (depends on result count)

**Limit:** Pages with 1000+ records will be slow to load and render

**Scaling path:**
1. Implement backend pagination in API endpoints
2. Add client-side pagination UI with configurable page size
3. Add lazy-loading for modal/detail views
4. Implement search to reduce initial dataset size

---

## Dependencies at Risk

### No Dependencies Currently Flagged at Risk

**Note:** The codebase has minimal external dependencies beyond WordPress. All critical functionality uses WordPress core APIs.

**Review needed:**
1. Check PHP version compatibility (code uses PHP 7.4+ features)
2. Review WordPress minimum version requirement
3. Monitor LINE SDK for security updates

---

## Missing Critical Features

### Email Notifications Not Implemented

**Problem:** Orders and shipments generate no customer emails
**Blocks:**
- Customers don't receive order confirmations
- Shipment notifications don't reach customers

**Workaround:** None - users must manually check website

**Implementation path:**
1. Create `EmailService` for template management
2. Wire up email triggers in `OrderService::updateStatus()`
3. Implement email template UI in settings page
4. Add email log for debugging delivery

---

### Inventory Allocation - Edge Cases Untested

**Problem:** Allocation logic has potential NULL handling issues
**Blocks:** Orders with missing product allocations may fail silently

**Current implementation:** `AllocationService::allocate()` handles NULL values but not thoroughly tested

**Implementation path:**
1. Add comprehensive test cases for NULL allocations
2. Test allocation with various product state combinations
3. Add validation to prevent invalid allocation states

---

## Test Coverage Gaps

### No Unit Tests for Critical Services

**Untested area:** Permission checking logic
**Files:** `includes/services/class-line-webhook-handler.php` (permission checks)

**What's not tested:**
- Permission verification with different module types
- Behavior when user not found in database
- Graceful handling of malformed permission data

**Risk:** Permission checks could fail silently or give wrong results

**Priority:** High - this is security-related

---

### No Integration Tests for LINE Webhook Flow

**Untested area:** End-to-end webhook processing
**Files:**
- `includes/api/class-line-webhook-api.php`
- `includes/services/class-line-webhook-handler.php`

**What's not tested:**
- Full webhook payload processing
- Event parsing and dispatch
- Message sending and error recovery

**Risk:** Webhook failures only discovered when testing with real LINE

**Priority:** High - this is production-critical

---

### No Tests for Admin Page Security

**Untested area:** Admin menu and settings pages
**Files:**
- `includes/admin/class-settings-page.php`
- `includes/api/class-settings-api.php`

**What's not tested:**
- NONCE verification on form submissions
- Permission checks for non-admin users
- XSS protection on output

**Risk:** Security vulnerabilities could exist in admin pages

**Priority:** High - security-critical

---

### Database Migration Testing Missing

**Untested area:** Table creation and upgrades
**Files:**
- `includes/class-database.php`
- `includes/class-plugin.php::maybe_upgrade_database()`

**What's not tested:**
- Migration from version 1.0 to 1.2
- Handling of existing data during upgrade
- Failure recovery if migration fails mid-way

**Risk:** Failed migrations could corrupt data or leave database in invalid state

**Priority:** Medium - affects fresh installations less, but important for updates

---

**Analysis Summary:**

The codebase has solid foundational architecture but shows signs of rapid development with incomplete features (global search, LIFF config), debugging code left in place, and gaps in critical security and integration testing. The recent HTML structure fixes indicate previous issues with template complexity. Main concerns are: unimplemented search feature, excessive debug logging in production, fragile webhook permission checks, and missing email notification system.

*Last updated: 2026-01-28*
