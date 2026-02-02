---
phase: 36
plan: 01
subsystem: services
tags: [child-order, fluentcart, eloquent, eager-loading]

dependency-graph:
  requires: [phase-35]
  provides: [child-order-query-service]
  affects: [phase-36-02-api]

tech-stack:
  added: []
  patterns: [service-layer, eager-loading, customer-id-conversion]

file-tracking:
  created:
    - includes/services/class-child-order-service.php
  modified: []

decisions:
  - id: D36-01-01
    title: "靜態方法用於 customer_id 轉換"
    choice: "getCustomerIdFromUserId 為靜態方法"
    rationale: "方便在 API 層直接呼叫，無需實例化 Service"
  - id: D36-01-02
    title: "Eager Loading 策略"
    choice: "使用 with(['order_items']) 預載入商品"
    rationale: "避免 N+1 查詢，確保效能"

metrics:
  duration: "1 分 23 秒"
  completed: "2026-02-02"
---

# Phase 36 Plan 01: ChildOrderService 子訂單查詢服務 Summary

**One-liner:** 建立 ChildOrderService 類別，提供子訂單查詢、格式化、權限驗證，使用 Eager Loading 避免 N+1

## What Was Built

### ChildOrderService (`includes/services/class-child-order-service.php`)

完整實作子訂單查詢服務，包含以下核心方法：

1. **getCustomerIdFromUserId(int $user_id): ?int**
   - 靜態方法，解決 customer_id 與 user_id 混淆問題
   - 從 `wp_fct_customers` 表查詢對應的 FluentCart customer_id
   - 找不到時返回 null

2. **getChildOrdersByParentId(int $parent_order_id, int $customer_id): array**
   - 驗證父訂單存在（404 錯誤處理）
   - 驗證權限（403 錯誤處理）
   - 使用 Eager Loading 查詢子訂單和商品
   - 返回格式化的子訂單陣列

3. **formatChildOrder(Order $order): array**
   - 格式化子訂單資料
   - 包含：id, invoice_no, payment_status, shipping_status, fulfillment_status
   - 金額轉換為元（total_amount / 100）
   - 包含賣家名稱和商品清單

4. **getSellerNameFromItems($items): string**
   - 從第一個商品的 post_id 取得賣家
   - 支援 variation 商品（取得 parent 的 post_author）
   - 返回 display_name 或 '未知賣家'

5. **formatItems($items): array**
   - 格式化商品項目清單
   - 金額轉換為元（unit_price, line_total / 100）

## Key Patterns Used

### Pattern 1: Service Layer 分離
遵循專案標準，商業邏輯放在 Service 類別，API 層（Phase 36-02）負責路由和格式化回應。

### Pattern 2: Eager Loading
```php
$child_orders = Order::where('parent_id', $parent_order_id)
    ->with(['order_items'])
    ->orderBy('created_at', 'desc')
    ->get();
```

### Pattern 3: 三層權限驗證（第二層）
```php
if ((int)$parent_order->customer_id !== $customer_id) {
    throw new \Exception('無權限存取此訂單', 403);
}
```

## Decisions Made

| ID | Decision | Rationale |
|----|----------|-----------|
| D36-01-01 | getCustomerIdFromUserId 為靜態方法 | 方便在 API 層直接呼叫，無需實例化 Service |
| D36-01-02 | 使用 with(['order_items']) 預載入 | 避免 N+1 查詢，確保效能 |

## Verification Results

| Check | Result |
|-------|--------|
| PHP 語法檢查 | No syntax errors detected |
| 方法完整性 | 5/5 方法存在 |
| Eager Loading | `->with(['order_items'])` 已使用 |
| 金額轉換 | 5 處 `/ 100` 轉換 |

## Deviations from Plan

None - 計畫執行完全符合預期。

Task 2（新增 customer_id 轉換方法）在 Task 1 實作時已一併完成，因為該方法是 ChildOrderService 類別的核心組成部分。

## Commits

| Hash | Message | Files |
|------|---------|-------|
| cd6e182 | feat(36-01): 建立 ChildOrderService 子訂單查詢服務 | includes/services/class-child-order-service.php |

## Next Phase Readiness

**Phase 36-02 前置條件已滿足：**
- [x] ChildOrderService 類別已建立
- [x] getChildOrdersByParentId() 方法可供 API 呼叫
- [x] getCustomerIdFromUserId() 靜態方法可用於權限轉換
- [x] 格式化輸出符合 API 預期格式

**下一步：** 執行 36-02-PLAN.md 建立 ChildOrders_API REST 端點
