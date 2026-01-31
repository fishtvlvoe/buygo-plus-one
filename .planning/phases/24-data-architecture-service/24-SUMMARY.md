---
phase: 24-data-architecture-service
plan: 01
subsystem: services
tags: [helpers, permissions, multi-seller, line-binding]

# Dependency graph
requires:
  - phase: 23
    provides: 基礎部署完成
provides:
  - 多賣家權限隔離機制
  - SettingsService 權限方法
  - 商品/訂單賣家過濾
affects: [ProductService, OrderService, SettingsService]

# Tech tracking
tech-stack:
  patterns:
    - post_author 為賣家識別欄位
    - wp_buygo_helpers 為小幫手授權表
    - get_accessible_seller_ids() 為核心權限方法

key-files:
  modified:
    - includes/services/class-settings-service.php
    - includes/services/class-product-service.php
    - includes/services/class-order-service.php

key-decisions:
  - "使用 post_author 作為商品賣家識別"
  - "訂單過濾透過 order_items 關聯到商品的 post_author"
  - "小幫手可存取多個賣場的資料"

# Metrics
duration: 15min
completed: 2026-02-01
---

# Phase 24: 資料架構與 Service Summary

**Status:** ✅ COMPLETE

## What Was Done

### 1. 分析現有實作

發現以下功能已在先前版本實作：
- `wp_buygo_helpers` 資料表（class-database.php 第 361-383 行）
- `get_helpers()`, `add_helper()`, `remove_helper()` 方法（class-settings-service.php）

### 2. 新增權限檢查方法 (SVC-04)

在 SettingsService 新增：

```php
// 取得使用者可存取的 seller_ids
public static function get_accessible_seller_ids(?int $user_id = null): array

// 檢查是否為賣家
public static function is_seller(?int $user_id = null): bool

// 檢查是否為小幫手
public static function is_helper(?int $user_id = null): bool

// 檢查是否可管理小幫手
public static function can_manage_helpers(?int $user_id = null): bool
```

### 3. 整合 LINE 綁定狀態 (SVC-05)

在 SettingsService 新增：

```php
// 檢查 LINE 綁定狀態
public static function get_line_binding_status(int $user_id): array

// 取得小幫手列表含 LINE 狀態
public static function get_helpers_with_line_status(?int $seller_id = null): array
```

### 4. 更新商品查詢過濾 (DATA-02)

修改 ProductService.get_products()：
- 使用 `get_accessible_seller_ids()` 取得可存取的賣家列表
- 使用 `whereIn('post_author', $accessible_seller_ids)` 過濾商品

### 5. 更新訂單查詢過濾 (DATA-03)

修改 OrderService.getOrders()：
- 透過 SQL 查詢取得包含可存取賣家商品的訂單 ID
- 使用 `whereIn('id', $order_ids)` 過濾訂單

## Files Modified

1. **includes/services/class-settings-service.php**
   - 新增 6 個權限相關方法
   - 約 130 行新程式碼

2. **includes/services/class-product-service.php**
   - 修改 get_products() 權限過濾邏輯
   - 使用 get_accessible_seller_ids() 取代硬編碼角色檢查

3. **includes/services/class-order-service.php**
   - 新增訂單的多賣家權限過濾
   - 透過 order_items 關聯到商品的 post_author

## Decisions Made

**D24-01: 使用 post_author 作為賣家識別**
- 原因：WordPress 原生欄位，穩定可靠
- 影響：商品建立時需設定正確的 post_author

**D24-02: 訂單過濾使用 SQL JOIN**
- 原因：訂單本身沒有賣家欄位，需透過 order_items 關聯
- 影響：查詢效能可能較低，但符合資料結構

**D24-03: 小幫手可存取多個賣場**
- 原因：一個用戶可能協助多個賣家
- 影響：get_accessible_seller_ids() 返回陣列而非單一值

## Verification

- [x] wp_buygo_helpers 資料表存在
- [x] get_accessible_seller_ids() 正確處理賣家和小幫手
- [x] ProductService 使用新的權限過濾
- [x] OrderService 使用新的權限過濾
- [x] LINE 綁定狀態可正確取得

## Next Steps

- Phase 25: API 權限過濾（使用這些 Service 方法）
- 整合測試待 Phase 25 API 實作後進行

---

*Phase: 24-data-architecture-service*
*Completed: 2026-02-01*
