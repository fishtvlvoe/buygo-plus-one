# LINE 訂單查詢功能 — 需求與技術交接

## 需求摘要

客戶在 LINE 官方帳號輸入 `/訂單`，即時顯示該客戶目前的訂單狀態摘要。

## 前提條件

- 客戶下單時已綁定 LINE 帳號（LINE userId ↔ BuyGo 會員已關聯）
- 透過 LineHub 外掛接收 LINE Webhook，轉發到 BuyGo API
- 不需要客戶手動輸入會員編號

## 訂單狀態定義（6 階段）

```
已下單 ──▶ 待分配 ──▶ 已分配 ──▶ 備貨中 ──▶ 出貨中 ──▶ 已出貨
 🛒        ⏳        ✅        📦        🚚        ✅
```

每個商品項目各自有獨立進度。

## 回覆模板設計（混合方案）

LINE 回覆「摘要」+ LIFF 連結：

```
📦 您目前有 3 筆進行中訂單

・商品A → 已出貨 ✅
・商品B → 備貨中 📦
・商品C → 待分配 ⏳

👉 查看完整明細：
[點我查看] ← LIFF 連結
```

- 摘要：客製化即時組裝（商品名 + 狀態 icon）
- 詳細：導向 LIFF 頁面（子訂單編號、物流單號等）
- 安全性：摘要只顯示商品名 + 狀態，不含敏感資訊

## 整合流程

```
客戶輸入 /訂單
      │
      ▼
LINE 官方帳號 → LineHub (Webhook 接收)
      │
      ▼
LineHub 透過 WordPress hooks 觸發 BuyGo
      │
      ▼
BuyGo 用 LINE userId 查找會員
      │
      ├─ 找不到 → 回覆「尚未綁定帳號」
      │
      └─ 找到 → 查詢該會員的訂單 + 子訂單
                  │
                  ▼
              組裝摘要訊息 + LIFF 連結
                  │
                  ▼
              透過 LINE Messaging API 回覆
```

## 代碼分析結果（2026-03-31 完成）

### 現有基礎（全部已有，不需新建）

| 能力 | 現有程式碼 |
|------|----------|
| LINE userId → 會員 | `IdentityService::getUserIdByLineUid()` |
| 查訂單列表 | `OrderService::getOrders()` |
| 查出貨狀態 | `ShipmentService::get_shipment()` |
| 接收 LINE 文字訊息 | `line_hub/webhook/message/text` hook |
| 發送 LINE Flex 訊息 | `line_hub/send/flex` hook |
| 狀態定義 | `ShippingStatusService::SHIPPING_STATUSES`（6 階段） |
| 通知模板系統 | `NotificationDefinitions::definitions()` + 後台可編輯 |

### 會員綁定表

```
wp_buygo_line_bindings
  - user_id  → WordPress User ID
  - line_uid → LINE 用戶 ID
  - status   → pending | completed | expired | unbound
```

### 狀態映射

| 顯示給客戶 | shipping_status | icon |
|-----------|----------------|------|
| 待分配 | unshipped + 無子訂單 | ⏳ |
| 已分配 | unshipped/preparing + 有子訂單或 _allocated_qty > 0 | ✅ |
| 備貨中 | preparing | 📦 |
| 已出貨 | shipped | 🚚 |

---

## 實作計畫

### 回覆格式（Flex Message 卡片，跟 /id 風格一致）

```
📦 您目前有 2 筆進行中訂單

#1405
・長腿Kitty (C)日曬豹紋
  1 × ¥2,900 → 待分配 ⏳

#1403
・長腿Kitty (A)日曬花裙
  1 × ¥2,900 → 已出貨 🚚
・長腿Kitty (D)粉格裙
  1 × ¥2,900 → 已出貨 🚚
  小計：¥5,800

合計：¥8,700
如有問題請聯絡客服 🙏
```

### 需要新建的檔案

| 檔案 | 用途 | 行數估計 |
|------|------|---------|
| `includes/services/class-line-order-query-service.php` | 查詢訂單 + 組裝 Flex Message | ~150 行 |

### 需要修改的檔案

| 檔案 | 修改內容 |
|------|---------|
| `class-line-keyword-responder.php` | 新增 `/訂單` 命令路由 |
| `class-line-text-router.php` | 把 `/訂單` 加入 `$system_commands` 陣列 |
| `class-notification-definitions.php` | 新增 `order_query` 模板定義（後台可編輯） |

### 步驟

1. **新建 `LineOrderQueryService`**
   - `getOrderSummary(int $user_id): array` — 查詢進行中訂單
   - 用 `OrderService::getOrders()` 取訂單
   - 用 `fct_order_items` + `fct_product_variations` 取商品名和 variant
   - 用 `_allocated_qty` + `shipping_status` 判斷狀態
   - 組裝 Flex Message（bubble 格式）

2. **修改 `LineKeywordResponder`**
   - switch case 新增 `/訂單`
   - 呼叫 `LineOrderQueryService::getOrderSummary()`

3. **修改 `LineTextRouter`**
   - `$system_commands` 陣列加入 `/訂單`

4. **修改 `NotificationDefinitions`**
   - 新增 `order_query` 模板（後台「LINE 通知模板管理」可編輯）

### 不做的事

- 不建 LIFF 訂單頁面（純 LINE 文字/卡片回覆）
- 不顯示物流資訊（不知道物流狀況）
- 不顯示已完成/已取消的訂單

## 架構約束（來自 CLAUDE.md）

- 商業邏輯只放 `includes/services/`
- API 層（`includes/api/`）只做驗證和路由
- 與 LineHub 的整合透過 WordPress hooks，禁止直接 new 或 require 其他外掛的 class
- 整合程式碼放 `includes/integrations/`
