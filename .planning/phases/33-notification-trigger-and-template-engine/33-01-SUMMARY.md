---
phase: 33
plan: 01
subsystem: notification-trigger
tags: [event-driven, hooks, notification, shipment]
requires:
  - phase: 32
    plan: 01
    reason: "使用 estimated_delivery_at 欄位"
provides:
  - "出貨通知觸發架構"
  - "NotificationHandler 事件監聽器"
  - "buygo/shipment/marked_as_shipped Action Hook"
affects:
  - phase: 33
    plan: 02
    reason: "NotificationHandler 需要模板引擎生成通知內容"
  - phase: 33
    plan: 03
    reason: "NotificationHandler 需要 NotificationService 發送通知"
tech-stack:
  added: []
  patterns:
    - "WordPress Action Hook"
    - "Singleton Pattern"
    - "Event-Driven Architecture"
key-files:
  created:
    - path: includes/services/class-notification-handler.php
      purpose: "出貨通知事件監聽器"
      exports: [NotificationHandler]
  modified:
    - path: includes/services/class-shipment-service.php
      changes: "新增 do_action Hook 觸發點"
    - path: includes/class-plugin.php
      changes: "註冊 NotificationHandler"
decisions:
  - id: D33-01-01
    what: "使用 WordPress Action Hook 實作事件驅動架構"
    why: "解耦出貨邏輯與通知邏輯，確保通知失敗不影響出貨流程"
    impact: "後續可輕鬆新增其他出貨事件監聽器（例如庫存更新、統計追蹤）"
  - id: D33-01-02
    what: "NotificationHandler 僅收集資料，不發送通知"
    why: "單一職責原則，模板引擎和發送邏輯由後續 plan 實作"
    impact: "33-02 實作模板引擎，33-03 實作通知發送"
  - id: D33-01-03
    what: "Hook 觸發位置：$shipped_count++ 之後，訂單狀態更新之前"
    why: "確保出貨成功才觸發通知，但在其他副作用之前觸發以保持事件語義清晰"
    impact: "監聽器可以在訂單狀態變更前收集完整資訊"
duration: "2 minutes"
completed: 2026-02-02
---

# Phase 33 Plan 01: 出貨通知觸發架構 Summary

**一句話總結:** 建立事件驅動的出貨通知觸發架構，使用 WordPress Action Hook 解耦出貨邏輯與通知邏輯

## 執行結果

### 任務完成度

✅ **全部完成 (3/3 tasks)**

| Task | 描述 | 狀態 | Commit |
|------|------|------|--------|
| 1 | 建立 NotificationHandler 類別 | ✅ 完成 | 1b4474e |
| 2 | ShipmentService 新增 Action Hook | ✅ 完成 | d111f69 |
| 3 | Plugin 類別註冊 NotificationHandler | ✅ 完成 | 97e849f |

### 交付成果

#### 1. NotificationHandler 類別 (includes/services/class-notification-handler.php)

**功能:**
- 單例模式事件監聽器
- 監聽 `buygo/shipment/marked_as_shipped` Hook
- 收集出貨單完整資訊（商品清單、物流方式、預計送達時間）
- 使用 try-catch 確保通知失敗不影響出貨流程

**核心方法:**
- `register_hooks()`: 註冊 WordPress Action Hook
- `handle_shipment_marked_shipped($shipment_id)`: 處理出貨事件
- `collect_shipment_data($shipment_id)`: 收集出貨單完整資訊

**收集的資訊包含:**
```php
[
    'shipment_id' => int,
    'shipment_number' => string,  // SH-YYYYMMDD-XXX
    'customer_id' => int,
    'seller_id' => int,
    'status' => string,
    'shipped_at' => datetime,
    'estimated_delivery_at' => datetime,  // Phase 32 新增欄位
    'shipping_method' => string,
    'items' => [
        [
            'product_id' => int,
            'product_title' => string,
            'product_price' => float,
            'quantity' => int,
            ...
        ],
        ...
    ]
]
```

#### 2. ShipmentService Hook 整合

**修改位置:** `ShipmentService::mark_shipped()` 方法

**新增程式碼:**
```php
// 觸發出貨通知事件（Phase 33: 出貨通知）
do_action('buygo/shipment/marked_as_shipped', $shipment_id);
```

**觸發時機:**
- 成功標記出貨（`$shipped_count++`）之後
- 更新訂單狀態之前
- 檢查父訂單完成之前

#### 3. Plugin 類別註冊

**修改位置:** `Plugin::register_hooks()` 方法

**新增程式碼:**
```php
// 初始化出貨通知處理器（Phase 33）
// 監聽 ShipmentService 出貨事件，觸發出貨通知
$notification_handler = \BuyGoPlus\Services\NotificationHandler::get_instance();
$notification_handler->register_hooks();
```

**載入順序:** Phase 31 (LineOrderNotifier) 之後

### 架構設計

```
ShipmentService::mark_shipped()
       │
       ├─> 更新出貨單狀態為 shipped
       │
       ├─> do_action('buygo/shipment/marked_as_shipped', $shipment_id)
       │        │
       │        └──> NotificationHandler::handle_shipment_marked_shipped()
       │                  │
       │                  ├─> collect_shipment_data()  // 收集完整資訊
       │                  │
       │                  ├─> [TODO 33-02] 模板引擎生成通知內容
       │                  │
       │                  └─> [TODO 33-03] NotificationService 發送通知
       │
       ├─> 更新訂單 shipping_status
       │
       └─> check_parent_completion()
```

### 驗證結果

所有驗證全部通過 ✅

**1. 檔案結構驗證**
```bash
✅ class-notification-handler.php 存在
```

**2. PHP 語法驗證**
```bash
✅ class-notification-handler.php: No syntax errors
✅ class-shipment-service.php: No syntax errors
✅ class-plugin.php: No syntax errors
```

**3. Hook 連結驗證**
```bash
✅ ShipmentService 觸發: do_action('buygo/shipment/marked_as_shipped', $shipment_id)
✅ NotificationHandler 監聽: add_action('buygo/shipment/marked_as_shipped', ...)
✅ Plugin 註冊: NotificationHandler::get_instance()->register_hooks()
```

**4. 必要方法驗證**
```bash
✅ register_hooks() 存在
✅ handle_shipment_marked_shipped() 存在
✅ collect_shipment_data() 存在
```

## 決策紀錄

### D33-01-01: 事件驅動架構

**決策:** 使用 WordPress Action Hook 實作事件驅動架構

**理由:**
- 解耦出貨邏輯與通知邏輯
- 確保通知失敗不影響出貨流程
- 符合單一職責原則

**影響:**
- ShipmentService 只負責出貨業務邏輯
- NotificationHandler 只負責通知觸發
- 後續可輕鬆新增其他監聽器（庫存更新、統計追蹤等）

### D33-01-02: 單一職責分離

**決策:** NotificationHandler 僅收集資料，不發送通知

**理由:**
- 單一職責原則
- 模板引擎和發送邏輯複雜度高，應獨立實作
- 測試更容易（可單獨測試資料收集邏輯）

**影響:**
- 33-02: 實作模板引擎生成通知內容
- 33-03: 實作 NotificationService 發送通知
- NotificationHandler 保持簡單，只做事件監聽和資料準備

### D33-01-03: Hook 觸發位置

**決策:** 在 `$shipped_count++` 之後，訂單狀態更新之前觸發 Hook

**理由:**
- 確保出貨成功（`$result !== false`）才觸發通知
- 在訂單狀態變更前觸發，保持事件語義清晰
- 監聽器可以收集完整的原始資料

**影響:**
- NotificationHandler 可以查詢未被修改的訂單狀態
- 通知內容更準確（基於出貨時的狀態，而非後續變更後的狀態）

## 計畫偏差

### 無偏差

計畫執行完全按照 PLAN.md 規格，無任何偏差。

## 下一步行動

### 33-02: 出貨通知模板引擎

**目標:** 實作 NotificationTemplates 類別和格式化方法

**待辦:**
1. 建立 `class-notification-templates.php`
2. 實作 `get_shipment_notification_template()` 方法
3. 實作變數格式化方法（日期、金額、物流方式）
4. 在 NotificationHandler 中整合模板引擎

### 33-03: 通知發送整合

**目標:** 整合 NotificationService 發送出貨通知

**待辦:**
1. 在 NotificationHandler 中調用 NotificationService
2. 發送給買家和賣家（含小幫手）
3. 處理發送失敗情況
4. 記錄發送結果

## 技術備註

### 錯誤處理

NotificationHandler 使用 try-catch 包裹所有邏輯：

```php
try {
    // 收集資料
    // 生成通知
    // 發送通知
} catch (\Exception $e) {
    // 記錄錯誤，但不中斷出貨流程
    $this->debugService->log('NotificationHandler', '處理失敗', [...], 'error');
}
```

**好處:**
- 通知失敗不影響出貨業務邏輯
- 出貨單狀態已正確更新
- 錯誤被記錄到 Debug Service，可事後追查

### DebugService 使用

所有關鍵步驟都使用 DebugService 記錄：

```php
// 事件觸發
$this->debugService->log('NotificationHandler', '收到出貨事件', [...]);

// 資料收集成功
$this->debugService->log('NotificationHandler', '資料收集成功', [...]);

// 錯誤記錄
$this->debugService->log('NotificationHandler', '收集失敗', [...], 'error');
```

**用途:**
- 開發階段除錯
- 生產環境問題追查
- 監控通知觸發率和成功率

## 相依關係

### 依賴 (Requires)

- **Phase 32-01**: 資料庫基礎升級
  - 使用 `estimated_delivery_at` 欄位
  - NotificationHandler 從出貨單/訂單查詢預計送達時間

### 提供 (Provides)

- **出貨通知觸發架構**: 事件驅動的基礎設施
- **NotificationHandler**: 可重用的事件監聽器模式
- **Action Hook**: `buygo/shipment/marked_as_shipped`

### 影響 (Affects)

- **Phase 33-02**: NotificationHandler 需要模板引擎
- **Phase 33-03**: NotificationHandler 需要 NotificationService

## 檔案清單

### 新增檔案 (1)

| 檔案 | 行數 | 用途 |
|------|------|------|
| includes/services/class-notification-handler.php | 217 | 出貨通知事件監聽器 |

### 修改檔案 (2)

| 檔案 | 變更 | 原因 |
|------|------|------|
| includes/services/class-shipment-service.php | +3 行 | 新增 Hook 觸發點 |
| includes/class-plugin.php | +5 行 | 註冊 NotificationHandler |

### 總計

- 新增: 217 行
- 修改: 8 行
- 總計: 225 行

## Commits

```
1b4474e feat(33-01): 建立 NotificationHandler 類別
d111f69 feat(33-01): ShipmentService 新增出貨通知 Hook
97e849f feat(33-01): Plugin 類別註冊 NotificationHandler
```

## 完成時間

**Duration:** 2 minutes
**Completed:** 2026-02-02

---

*Generated by GSD Plan Executor*
