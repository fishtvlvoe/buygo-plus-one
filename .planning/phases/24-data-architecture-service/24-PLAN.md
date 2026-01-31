# Plan 24: 資料架構與 Service

```yaml
wave: 1
depends_on: []
files_modified:
  - includes/class-database.php
  - includes/services/class-settings-service.php
  - includes/services/class-product-service.php
  - includes/services/class-order-service.php
autonomous: true
```

## Goal

建立 wp_buygo_helpers 資料表和 Settings_Service 擴充方法，實現多賣家權限隔離。

## Requirements

### 資料架構 (DATA)
- DATA-01: 建立 wp_buygo_helpers 資料表 ✅（已存在）
- DATA-02: 驗證商品查詢的 post_author 過濾 ✅
- DATA-03: 驗證訂單查詢的賣家過濾 ✅

### Service 方法 (SVC)
- SVC-01: Settings_Service.get_helpers(seller_id) ✅（已存在）
- SVC-02: Settings_Service.add_helper(user_id, seller_id) ✅（已存在）
- SVC-03: Settings_Service.remove_helper(user_id, seller_id) ✅（已存在）
- SVC-04: 權限檢查方法 ✅
- SVC-05: 整合 LineUserService ✅

## Tasks

### Task 1: 分析現有實作 ✅

發現 DATA-01、SVC-01~03 已在先前版本實作完成：
- `create_helpers_table()` 在 class-database.php
- `get_helpers()`, `add_helper()`, `remove_helper()` 在 class-settings-service.php

### Task 2: 實作 SVC-04 權限檢查方法 ✅

新增以下方法到 SettingsService：
- `get_accessible_seller_ids(user_id)` - 返回用戶可存取的 seller_ids
- `is_seller(user_id)` - 檢查是否為賣家
- `is_helper(user_id)` - 檢查是否為小幫手
- `can_manage_helpers(user_id)` - 檢查是否可管理小幫手

### Task 3: 實作 SVC-05 LINE 綁定狀態 ✅

新增以下方法到 SettingsService：
- `get_line_binding_status(user_id)` - 檢查 LINE 綁定狀態
- `get_helpers_with_line_status(seller_id)` - 取得小幫手列表含 LINE 狀態

### Task 4: 更新 ProductService 權限過濾 (DATA-02) ✅

修改 `get_products()` 方法：
- 使用 `get_accessible_seller_ids()` 取得可存取的賣家列表
- 使用 `whereIn('post_author', $accessible_seller_ids)` 過濾商品

### Task 5: 更新 OrderService 權限過濾 (DATA-03) ✅

修改 `getOrders()` 方法：
- 透過 order_items -> posts 關聯過濾訂單
- 只顯示包含可存取賣家商品的訂單

## Verification Criteria

- [x] wp_buygo_helpers 資料表存在
- [x] get_accessible_seller_ids() 返回正確的 seller_ids
- [x] 商品查詢使用 post_author 過濾
- [x] 訂單查詢過濾只顯示相關賣家的訂單
- [x] LINE 綁定狀態可正確取得

## Notes

- 單元測試需要 WordPress 環境，待 Phase 25 API 實作後進行整合測試
- 權限邏輯複雜度低，透過代碼審查驗證

---

*Plan: 24*
*Created: 2026-02-01*
