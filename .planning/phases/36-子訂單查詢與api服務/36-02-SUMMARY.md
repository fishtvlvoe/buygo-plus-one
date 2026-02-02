---
phase: 36-子訂單查詢與api服務
plan: 02
subsystem: api
tags: [rest-api, child-orders, customer-frontend, permission]
dependency-graph:
  requires: ["36-01"]
  provides: ["ChildOrders_API 端點"]
  affects: ["37-01", "37-02"]
tech-stack:
  added: []
  patterns: ["Customer frontend API pattern", "is_user_logged_in permission"]
key-files:
  created:
    - includes/api/class-child-orders-api.php
  modified:
    - includes/api/class-api.php
decisions:
  - id: DEC-36-02-01
    title: "使用 is_user_logged_in() 作為 permission_callback"
    context: "顧客前台 API 不需要後台權限"
    chosen: "is_user_logged_in() + Service 層 customer_id 驗證"
    rationale: "兩層驗證：API 層驗證登入狀態，Service 層驗證資料所屬權限"
  - id: DEC-36-02-02
    title: "require_once 放在 __construct() 中"
    context: "需要確保 ChildOrderService 可用"
    chosen: "在 API 類別的 __construct() 中 require_once"
    rationale: "簡單且符合現有 API 類別的模式"
metrics:
  duration: "1 min 19 sec"
  completed: "2026-02-02"
---

# Phase 36 Plan 02: ChildOrders_API REST 端點 Summary

**One-liner:** GET /child-orders/{parent_order_id} 端點，使用 is_user_logged_in 權限驗證，搭配 Service 層 customer_id 驗證實現雙層安全

## What Was Done

### Task 1: 建立 ChildOrders_API 類別 (5a31d32)

建立 `includes/api/class-child-orders-api.php`，包含：

- `register_routes()`: 註冊 GET /child-orders/{parent_order_id} 端點
- `check_customer_permission()`: 第一層權限驗證 (is_user_logged_in)
- `get_child_orders()`: 取得子訂單列表，整合 ChildOrderService

關鍵實作：
```php
register_rest_route($this->namespace, '/child-orders/(?P<parent_order_id>\d+)', [
    'methods' => 'GET',
    'callback' => [$this, 'get_child_orders'],
    'permission_callback' => [$this, 'check_customer_permission'],
    ...
]);
```

### Task 2: 整合 API 到 class-api.php (0375ed6)

修改 `includes/api/class-api.php`：
- 新增 `require_once` 載入 class-child-orders-api.php
- 新增 `ChildOrders_API` 實例化和 `register_routes()` 呼叫

### Task 3: 載入 ChildOrderService 依賴

已在 Task 1 中完成 - require_once 在 `__construct()` 中處理。

## Deviations from Plan

None - 計畫完全按照規劃執行。

## Decisions Made

| ID | Decision | Rationale |
|----|----------|-----------|
| DEC-36-02-01 | 使用 is_user_logged_in() 權限 | 顧客前台不需後台權限，雙層驗證更安全 |
| DEC-36-02-02 | require_once 在 __construct() | 符合現有 API 類別模式，簡單直接 |

## Verification Results

1. **語法檢查:**
   - `php -l class-child-orders-api.php` - No syntax errors
   - `php -l class-api.php` - No syntax errors

2. **API 整合確認:**
   - require_once 在第 21 行
   - ChildOrders_API 實例化在第 48 行

## Next Phase Readiness

**Ready for Phase 37 (前端 UI 元件):**
- API 端點已可用: `GET /wp-json/buygo-plus-one/v1/child-orders/{parent_order_id}`
- 回傳格式已定義，前端可直接使用

**前端可預期的回應格式:**
```json
{
    "success": true,
    "data": {
        "child_orders": [...],
        "count": 3,
        "currency": "TWD"
    }
}
```

**錯誤回應格式:**
```json
{
    "success": false,
    "code": "FORBIDDEN|NOT_FOUND|SERVER_ERROR",
    "message": "錯誤訊息"
}
```

## Files Changed

| File | Change Type | Lines |
|------|-------------|-------|
| includes/api/class-child-orders-api.php | Created | +131 |
| includes/api/class-api.php | Modified | +5 |
