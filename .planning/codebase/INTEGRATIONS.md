# External Integrations

**Analysis Date:** 2026-01-28

## APIs & External Services

**LINE Messaging API:**
- Service: LINE (messaging, notifications, LIFF login)
- What it's used for:
  - Send purchase notifications and order updates to customers via LINE chat
  - Receive order/product creation requests from LINE Bot
  - User authentication via LIFF (LINE Front-end Framework)
  - Webhook signature verification for incoming messages
- SDK/Client: WordPress HTTP functions (`wp_remote_post`, `wp_remote_get`)
- Auth: Channel Secret (signature verification), Channel Access Token (API calls)
- Related files:
  - `includes/services/class-line-service.php` - User binding and lookup
  - `includes/services/class-line-webhook-handler.php` - Webhook processing
  - `includes/services/class-line-order-notifier.php` - Sending notifications
  - `includes/api/class-line-webhook-api.php` - REST endpoint for webhooks
  - `includes/api/class-liff-login-api.php` - LIFF login endpoint

**FluentCart E-commerce:**
- Service: FluentCart (WordPress e-commerce plugin)
- What it's used for:
  - Product management and catalog
  - Order processing and customer data
  - Payment processing
  - Customer information storage
- SDK/Client: Direct WordPress function hooks and database queries
- Integration: `includes/services/class-fluentcart-service.php`
- Related files:
  - Creates products via `wp_insert_post()` with post type `fluent-products`
  - Queries orders via FluentCart's custom tables
  - Syncs customer data from FluentCart

**LINE LIFF (Front-end Framework):**
- Service: LINE LIFF Web SDK
- What it's used for: Web-based user login and account linking via LINE
- LIFF ID: `buygo_line_liff_id` option (stored as plain text)
- Related files: `includes/api/class-liff-login-api.php`

## Data Storage

**Databases:**
- WordPress MySQL database (primary)
  - Connection: Via WordPress `$wpdb` global
  - Client: WordPress wpdb class (built-in abstraction)
  - FluentCart tables: `fc_orders`, `fc_products`, `fc_customers` (external)

**File Storage:**
- WordPress Media Library (via FluentCart)
  - Product images stored as WordPress attachments
  - Image URLs tracked via `image_attachment_id` in product data
  - Related: `includes/services/class-fluentcart-service.php`

**Caching:**
- WordPress Transients API
  - Cache keys with `buygo_` prefix
  - Cleared on plugin deactivation
  - Used for: Notification templates, settings cache

## Authentication & Identity

**Auth Provider:**
- LINE Login (OAuth 2.0 via LIFF)
  - Implementation: LIFF Web SDK (client-side)
  - User binding: `wp_buygo_line_bindings` table
  - Endpoint: `buygo-plus-one/v1/line/liff-login`

**Admin Auth:**
- WordPress native authentication
  - Permission checks: WordPress roles (administrator, editor, etc.)
  - Related: `includes/services/class-settings-service.php` (role management)

**API Authentication:**
- WordPress Nonce + Session
  - Nonce generation: `wp_create_nonce("wp_rest")`
  - Header: `X-WP-Nonce`
  - Used for: All REST API requests from frontend

**LINE Webhook Signature:**
- HMAC-SHA256 verification
  - Header: `X-LINE-Signature` (note: header name is case-sensitive in implementation)
  - Secret: Channel Secret from LINE Developers Console
  - Related: `includes/api/class-line-webhook-api.php::verify_signature()`

## Monitoring & Observability

**Error Tracking:**
- Custom logging via `DebugService`
  - Table: `wp_buygo_debug_logs`
  - Log files: `/Volumes/insta-mount/wp-content/buygo-plus-one.log` (InstaWP mount)
  - Related: `includes/services/class-debug-service.php`

**Logs:**
- WordPress error log + custom database tables
- Webhook logging: `wp_buygo_webhook_logs` table
  - Tracks: LINE webhook requests, signatures, body content
  - Related: `includes/services/class-webhook-logger.php`
- Workflow logging: `wp_buygo_workflow_logs` table
  - Tracks: Order processing, allocation, shipment flow

## CI/CD & Deployment

**Hosting:**
- Local by Flywheel (development)
  - URL: `http://buygo.local` or `https://test.buygo.me`
  - WordPress path: `/Users/fishtv/Local Sites/buygo/app/public/`
  - MySQL socket: `/Users/fishtv/Library/Application Support/Local/run/oFa4PFqBu/mysql/mysqld.sock`

- InstaWP (cloud development environment)
  - URL: `https://test.buygo.me` (DNS A record)
  - Mount point: `/Volumes/insta-mount/`

**CI Pipeline:**
- None (local development + manual testing)
- PHPUnit tests run locally: `composer test`
- Test database: `wordpress_test` (MySQL, root/root)

**Version Control:**
- Git repository with GitHub integration
- Plugin versions tracked in `buygo-plus-one.php` and `readme.txt`

## Environment Configuration

**Required env vars:**
- No `.env` file (configuration via WordPress options table)
- Critical settings stored as encrypted options:
  - `buygo_line_channel_access_token` - LINE Bot API token
  - `buygo_line_channel_secret` - LINE Bot webhook secret
  - `buygo_line_login_channel_secret` - LINE Login webhook secret
  - `buygo_line_liff_id` - LINE LIFF application ID

**Secrets location:**
- WordPress options table (`wp_options`)
- Sensitive fields encrypted via `SettingsService::encrypt()` / `decrypt()`
- Encryption key: WordPress `AUTH_KEY` or similar (implementation in `class-settings-service.php`)

**Configuration Methods:**
- WordPress admin settings page: `/wp-admin/?page=buygo-plus-one-settings`
- REST API: `POST /buygo-plus-one/v1/settings` endpoints
- Direct option retrieval: `SettingsService::get_line_settings()`

## Webhooks & Callbacks

**Incoming Webhooks:**
- LINE Webhook: `POST /wp-json/buygo-plus-one/v1/line/webhook`
  - Source: LINE Messaging API
  - Triggers: Message events, follow/unfollow, postback actions
  - Verification: HMAC-SHA256 signature in `X-LINE-Signature` header
  - Handler: `includes/api/class-line-webhook-api.php::handle_webhook()`
  - Processing: Async via `shutdown` hook or `wp_schedule_single_event()`

**Outgoing Webhooks:**
- LINE Messaging API push messages
  - Endpoint: `https://api.line.me/v2/bot/message/push`
  - Use cases: Order notifications, shipment updates, status changes
  - Authentication: Bearer token (Channel Access Token)
  - Related: `includes/services/class-line-order-notifier.php`

- LINE LIFF Login callbacks
  - Endpoint: `buygo-plus-one/v1/line/liff-login` (local)
  - Receives: Line User ID, display name, email from LIFF SDK

## Data Flow

**Order Notification Flow:**
1. Order created in FluentCart
2. Plugin triggers `line_order_notifier` service
3. Notifier formats notification template (customizable via settings)
4. Sends via LINE Messaging API push message
5. Logs in `wp_buygo_notification_logs`

**LINE Command Flow (via Webhook):**
1. Customer sends message to LINE Bot
2. LINE Platform sends webhook to `buygo-plus-one/v1/line/webhook`
3. Signature verification via `X-LINE-Signature`
4. `LineWebhookHandler::process_events()` parses command
5. Service layer processes (create product, check order, etc.)
6. Response sent back via LINE Message API

**LINE Login Flow:**
1. User clicks "Login with LINE" on LIFF app
2. LIFF SDK returns Line User ID and profile
3. Frontend calls `buygo-plus-one/v1/line/liff-login` REST endpoint
4. Backend binds Line UID to WordPress user
5. Session created, user logged in

## Third-party CDN Dependencies

**JavaScript (loaded in frontend):**
- Vue 3: `https://unpkg.com/vue@3/dist/vue.global.js`
- VueDraggable: `https://cdn.jsdelivr.net/npm/vuedraggable@4.1.0/dist/vuedraggable.umd.min.js`
- SortableJS: `https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js`
- Tailwind CSS: `https://cdn.tailwindcss.com`

**Fonts (Google CDN):**
- Poppins (headings): `https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700`
- Open Sans (body): `https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600`

## API Credentials Required

**LINE Messaging API:**
- Channel ID: Set in WordPress admin settings
- Channel Access Token: Encrypted in options table
- Channel Secret: Encrypted in options table
- Webhook URL: `rest_url('buygo-plus-one/v1/line/webhook')`

**LINE Login (LIFF):**
- LIFF ID: Set in WordPress admin settings
- Channel Secret (for LIFF): Encrypted in options table

**FluentCart:**
- No explicit credentials needed (same WordPress installation)
- Must be installed and activated as plugin

---

*Integration audit: 2026-01-28*
