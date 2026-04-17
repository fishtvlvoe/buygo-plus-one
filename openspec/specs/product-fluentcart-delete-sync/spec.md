# product-fluentcart-delete-sync Specification

## Purpose

TBD - created by archiving change 'fix-order-quantity-and-fluentcart-delete'. Update Purpose after archive.

## Requirements

### Requirement: Deleting a product in BuyGo moves the FluentCart product post to trash

When a seller deletes a product (or batch-deletes products) via the BuyGo admin backend, the system SHALL move the corresponding WordPress post (`fc_product` post type) to the trash using `wp_trash_post()` after marking the `ProductVariation.item_status` as `inactive`.

For single-variation products, the system SHALL move the post to trash immediately when the variation is set to inactive.

For multi-variation products, the system SHALL only move the post to trash when ALL variations sharing the same `post_id` have `item_status = 'inactive'` (including the variation just deleted). If at least one other variation with the same `post_id` remains `active`, the post SHALL NOT be trashed.

The trash operation SHALL be performed by `ProductService::deleteProductPost(int $variationId): bool`, not directly in the API layer.

If `wp_trash_post()` fails or the post does not exist, the system SHALL NOT fail the variation delete operation — the variation SHALL still be set to inactive and the API response SHALL still return success.

#### Scenario: Single-variation product deleted from BuyGo admin

- **WHEN** a seller selects a single-variation product and clicks delete in BuyGo admin
- **AND** the API `POST /products/batch-delete` is called with that product's ID
- **THEN** the `ProductVariation.item_status` SHALL be set to `inactive`
- **AND** the corresponding WordPress post SHALL appear in the WordPress/FluentCart trash
- **AND** the API response SHALL return `success: true`

#### Scenario: Multi-variation product — only one variation deleted

- **WHEN** a seller deletes one variation of a multi-variation product
- **AND** at least one other variation with the same `post_id` still has `item_status = 'active'`
- **THEN** the deleted variation's `item_status` SHALL be set to `inactive`
- **AND** the WordPress post SHALL NOT be moved to trash
- **AND** the remaining active variations SHALL continue to function normally

#### Scenario: Multi-variation product — all variations deleted

- **WHEN** a seller deletes the last active variation of a multi-variation product
- **AND** no other variation with the same `post_id` has `item_status = 'active'`
- **THEN** the last variation's `item_status` SHALL be set to `inactive`
- **AND** the WordPress post SHALL be moved to the trash

#### Scenario: wp_trash_post failure does not block variation delete

- **WHEN** `wp_trash_post()` returns false or throws an exception (e.g., post already deleted)
- **THEN** the variation SHALL still be set to `inactive` successfully
- **AND** the API response SHALL still return `success: true`
- **AND** the error SHALL be silently swallowed (no user-facing error for trash failure)

#### Scenario: Batch delete multiple products

- **WHEN** a seller selects multiple products and batch-deletes them
- **THEN** each variation SHALL be processed independently
- **AND** each product's post SHALL be moved to trash if all its variations are inactive after the batch operation

<!-- @trace
source: fix-order-quantity-and-fluentcart-delete
updated: 2026-04-17
code:
  - tests/Unit/Services/ProductServiceDeletePostTest.php
  - docs/bug/截圖 2026-04-17 中午12.47.23.png
  - includes/api/class-products-api.php
  - docs/bug/截圖 2026-04-17 中午12.47.36.png
  - includes/services/class-product-service.php
  - includes/views/composables/useProducts.js
  - tests/bootstrap-unit.php
-->