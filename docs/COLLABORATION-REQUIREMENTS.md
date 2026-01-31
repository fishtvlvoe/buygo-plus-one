# BuyGo Plus One 協作需求報告

> 更新日期：2026-01-31
> 產生方式：Phase 19.2 驗證完成後自動分析

## 1. 專案概覽

### 相關專案清單

| 專案名稱 | 路徑 | 角色 |
|---------|-----|------|
| **buygo-plus-one** | `/Development/buygo-plus-one/` | 正式版（Production） |
| **buygo-plus-one-dev** | `/Development/buygo-plus-one-dev/` | 開發版（Development） |
| **fluentcart-payuni** | `/Development/fluentcart-payuni/` | PayUNi 金流整合 |
| **buygo-line-notify** | 外部相依 | LINE 通知基礎設施 |

---

## 2. 跨專案整合關係

### 2.1 BuyGo Plus One ↔ FluentCart（核心依賴）

BuyGo Plus One 深度整合 FluentCart 電商平台，使用其 Hooks 和資料結構：

#### 使用的 FluentCart Hooks

| Hook 名稱 | 類型 | 用途 | 位置 |
|----------|------|-----|------|
| `fluent_cart/checkout_address_fields` | Filter | 新增身分證欄位 | `CheckoutCustomizationService.php:40` |
| `fluent_cart/order_created` | Action | 儲存身分證 / LINE 訂單通知 | `CheckoutCustomizationService.php:41`, `LineOrderNotifier.php:73` |
| `fluent_cart/after_receipt_first_time` | Action | LINE 綁定收據顯示 | `LineBindingReceipt.php:36` |
| `fluent_cart/shipping_status_changed_to_shipped` | Action | 出貨通知觸發 | `LineOrderNotifier.php:76` |

#### 使用的 FluentCart 資料表

- `{prefix}_fct_order_meta` - 儲存身分證字號等訂單 meta
- `{prefix}_fct_orders` - 訂單主表
- `{prefix}_fct_products` - 商品主表

---

### 2.2 BuyGo Plus One ↔ buygo-line-notify（必要相依）

BuyGo Plus One 依賴 `buygo-line-notify` 外掛提供 LINE Messaging API 基礎設施。

#### 相依檢查點

```php
// 多處使用此檢查
if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) ) {
    // 顯示啟用提示
}
```

#### 監聽的 buygo-line-notify Hooks

| Hook 名稱 | 用途 | 處理類別 |
|----------|------|---------|
| `buygo_line_notify/webhook_message_image` | LINE 圖片上傳商品 | `LineWebhookHandler` |
| `buygo_line_notify/webhook_message_text` | LINE 文字訊息處理 | `LineWebhookHandler` |
| `buygo_line_notify/webhook_postback` | LINE Postback 處理 | `LineWebhookHandler` |

#### 使用的 buygo-line-notify 功能

- LINE Messaging API 封裝
- LINE 使用者 UID 管理
- Webhook 路由分發

---

### 2.3 BuyGo Plus One ↔ fluentcart-payuni（平行專案）

兩者都是 FluentCart 的延伸外掛，可獨立運作但可能有交互：

#### fluentcart-payuni 提供

- PayUNi 統一金流支付閘道
- 一次性付款（信用卡、ATM、超商代碼）
- 訂閱制定期扣款

#### 共用資源

- 都使用 FluentCart 訂單系統
- 都可能在結帳頁面注入 UI

---

## 3. 自訂 Hooks（由 BuyGo Plus One 觸發）

其他外掛可監聽這些 hooks：

### 訂單/出貨相關

| Hook 名稱 | 觸發時機 | 參數 |
|----------|---------|------|
| `buygo_shipping_status_changed` | 出貨狀態變更 | `$orderId, $oldStatus, $newStatus` |
| `buygo_order_shipped` | 訂單已出貨 | `$orderId` |
| `buygo_order_completed` | 訂單完成 | `$orderId` |
| `buygo_order_out_of_stock` | 訂單缺貨 | `$orderId` |
| `buygo_abnormal_status_change` | 異常狀態變更 | `$orderId, $oldStatus, $newStatus` |
| `buygo_send_admin_notification` | 發送管理員通知 | `$data` |

### 商品相關

| Hook 名稱 | 觸發時機 | 參數 |
|----------|---------|------|
| `buygo/product/created` | 商品建立完成 | `$product_id, $product_data, $line_uid` |

### 訂單配置相關

| Hook 名稱 | 觸發時機 | 參數 |
|----------|---------|------|
| `buygo/child_order_created` | 子訂單建立 | `$child_order_id, $parent_order_id` |
| `buygo/parent_order_completed` | 母單完成 | `$parent_id` |

### LINE 綁定相關

| Hook 名稱 | 觸發時機 | 參數 |
|----------|---------|------|
| `buygo_line_binding_completed` | LINE 綁定完成 | `$user_id, $line_uid` |

---

## 4. 資料庫依賴

### BuyGo Plus One 自建資料表

| 資料表 | 用途 |
|-------|------|
| `{prefix}_buygo_shipments` | 出貨記錄 |
| `{prefix}_buygo_allocations` | 配貨記錄 |
| `{prefix}_buygo_line_bindings` | LINE 綁定 |
| `{prefix}_buygo_helpers` | 小幫手權限 |
| `{prefix}_buygo_keywords` | 關鍵字回應 |

### 依賴的外部資料表

| 資料表 | 來源 | 用途 |
|-------|------|------|
| `{prefix}_fct_orders` | FluentCart | 訂單查詢 |
| `{prefix}_fct_order_meta` | FluentCart | 訂單 meta 儲存 |
| `{prefix}_fct_products` | FluentCart | 商品查詢 |
| `{prefix}_users` | WordPress | 使用者管理 |
| `{prefix}_usermeta` | WordPress | 使用者 meta |

---

## 5. 待完成的協作功能

### 5.1 多規格商品支援（Phase 20）

**現狀**：目前只支援單一規格商品
**需求**：
- 整合 FluentCart 多規格（Variations）系統
- LINE 上傳流程需支援規格選擇

### 5.2 PayUNi 深度整合

**現狀**：fluentcart-payuni 獨立運作
**潛在需求**：
- BuyGo 出貨單與 PayUNi 付款狀態同步
- 退款流程整合

### 5.3 自動更新機制

**現狀**：手動更新
**需求**：
- 建立 GitHub Releases → WordPress 更新 API 橋接
- 三個外掛統一更新機制

---

## 6. 整合測試檢查清單

### FluentCart 整合

- [ ] 結帳欄位正確顯示
- [ ] 訂單 meta 正確儲存
- [ ] 出貨狀態變更觸發正確

### buygo-line-notify 整合

- [ ] LINE Webhook 正確路由
- [ ] 訂單通知正常發送
- [ ] LINE 綁定流程完整

### 跨外掛相容性

- [ ] 三個外掛同時啟用無衝突
- [ ] Hook 優先級無衝突
- [ ] 資料庫查詢效能正常

---

## 7. 架構圖

```
┌─────────────────────────────────────────────────────────────┐
│                      WordPress Core                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────────┐    ┌──────────────────────────────┐   │
│  │   FluentCart    │    │      buygo-line-notify       │   │
│  │  (E-Commerce)   │    │   (LINE Messaging API)       │   │
│  └────────┬────────┘    └──────────────┬───────────────┘   │
│           │                            │                    │
│           │ Hooks/Filters              │ Hooks              │
│           │                            │                    │
│           ▼                            ▼                    │
│  ┌────────────────────────────────────────────────────┐    │
│  │              BuyGo Plus One (主外掛)                │    │
│  │                                                     │    │
│  │  • 訂單管理        • LINE 商品上傳                   │    │
│  │  • 出貨配置        • LINE 訂單通知                   │    │
│  │  • 結帳自訂        • 使用者權限管理                  │    │
│  └────────────────────────────────────────────────────┘    │
│           │                                                 │
│           │ 共用 FluentCart 訂單系統                         │
│           │                                                 │
│  ┌────────▼────────┐                                       │
│  │ fluentcart-     │                                       │
│  │ payuni          │                                       │
│  │ (PayUNi 金流)   │                                       │
│  └─────────────────┘                                       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 8. 維護建議

1. **Hook 命名規範**：所有 BuyGo 觸發的 hook 使用 `buygo_` 或 `buygo/` 前綴
2. **版本相容性**：記錄每個整合點的最低版本需求
3. **錯誤處理**：外部相依不存在時優雅降級，不影響核心功能
4. **文件同步**：整合點變更時同步更新此文件

---

*本報告由 Claude Code 於 Phase 19.2 驗證完成後自動產生*
