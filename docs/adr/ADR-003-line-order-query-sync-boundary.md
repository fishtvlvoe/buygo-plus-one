# ADR-003: LINE 訂單查詢與本地 Schema 同步邊界

| 欄位 | 值 |
|------|-----|
| 決策日期 | 2026-03-31 |
| 狀態 | Accepted |
| 相關 | SPEC-007 (訂單取消), docs/features/line-order-query.md |
| 影響範圍 | LINE 整合、IdentityService、OrderService、ShipmentService |

## 背景

客戶在 LINE 官方帳號輸入 `/訂單`，即時顯示該客戶的訂單狀態摘要。需要決定：
- 資料流向（推送 vs 拉取）
- LINE userId ↔ BuyGo 會員 ID 映射
- 安全邊界（哪些資訊可向 LINE 暴露）
- 狀態定義如何同步

## 選項考量

### 選項 A — BuyGo 主動推送訂單更新到 LINE

流程：訂單狀態變更 → BuyGo 監聽 webhook → 推送到 LINE

**優點**：
- LINE 端即時掌握最新狀態
- 可主動通知客戶

**缺點**：
- 複雜度高（webhook 監聽 + 錯誤重試 + 網路延遲）
- 狀態定義必須同步（一旦不同步，資訊錯誤）
- 依賴 LINE Messaging API 可用性

### 選項 B — LINE 查詢時同步拉取 BuyGo 資料

流程：
```
客戶輸入 /訂單
      ↓
LINE 官方帳號 webhook
      ↓
LineHub 外掛接收
      ↓
BuyGo API 查詢（即時）
      ↓
組裝摘要 + LIFF 連結回覆
```

**優點**：
- 簡單可控（同步呼叫，易測試）
- 一致性好（BuyGo 資料庫是唯一真實來源）
- 易除錯（不涉及異步推送機制）

**缺點**：
- 如 API 故障，無法回覆
- 不可主動通知客戶（只能被動回答）

## 決策

**選擇 Option B — 拉取式同步**

理由：
1. 團隊規模小，簡單可維護
2. BuyGo 資料庫為唯一真實來源，無同步問題
3. LineHub 已可接收 webhook，集成點清晰
4. 狀態定義一次在 BuyGo 定義，不需雙向同步

## 狀態定義清單（客戶端顯示）

| 顯示文案 | shipping_status | 備註 | Icon |
|---------|----------------|------|------|
| 待分配 | unshipped + 無子訂單或 _allocated_qty = 0 | 剛下單 | ⏳ |
| 已分配 | unshipped + 有子訂單或 _allocated_qty > 0 | 庫存分配完成 | ✅ |
| 備貨中 | preparing | 商家備貨中 | 📦 |
| 出貨中 | shipped | 物流進行中 | 🚚 |
| 已出貨 | completed 或 delivered | 已簽收 | ✅ |

> 定義來源：`ShippingStatusService::SHIPPING_STATUSES`

## 安全邊界

### 允許向 LINE 暴露

- 商品名稱（非敏感）
- 訂單狀態 icon + 文案（已定義清單）
- 推薦查看完整明細的 LIFF 連結

### 禁止暴露

- 單價、總價（僅在 LIFF 登入後顯示）
- 收貨地址（僅在 LIFF 登入後顯示）
- 購買量（摘要可包，詳細在 LIFF）
- 會員 ID、內部訂單 ID（對 LINE 無用）

## 整合流程

```
1. 會員綁定：LINE userId ↔ WordPress User ID
   表：wp_buygo_line_bindings
   - user_id (WP User ID)
   - line_uid (LINE User ID)
   - status (pending / completed / expired / unbound)

2. Webhook 流程：
   客戶 → LINE 官方帳號 (/訂單)
        → LineHub webhook receiver
        → BuyGo Hook: line_hub/webhook/message/text
        → IdentityService::getUserIdByLineUid(line_uid)
           └─ 找到 user_id
           └─ OrderService::getOrders(user_id)
              └─ ShipmentService::get_shipment()
                 └─ 組裝摘要 + LIFF 連結
           └─ 回覆 LINE Flex 訊息

3. LIFF 頁面：
   需登入確認身份 → 顯示完整訂單詳情（含價格、地址）
```

## 相關 Service

| Service | 用途 |
|---------|------|
| IdentityService::getUserIdByLineUid() | LINE userId 對應 |
| OrderService::getOrders(user_id) | 查詢會員訂單列表 |
| ShipmentService::get_shipment() | 出貨狀態查詢 |
| NotificationDefinitions | 通知模板系統（支援後台編輯摘要文案） |

## 後果

### 正面影響

- LINE 客戶體驗：即時查詢，無延遲
- 開發簡單：同步呼叫，易測試
- 運維簡單：無 webhook 推送隊列管理

### 負面影響

- 無主動通知能力（只能被動回答）
- 依賴 BuyGo API 可用性

### 後續行動

1. 實裝 `IdentityService::getUserIdByLineUid()` 查詢
2. 建立 `line_hub/webhook/message/text` hook 監聽
3. 建立 `/api/orders/summary?user_id=X` endpoint（給 LINE 專用）
4. 建立 LIFF 頁面認證流程
5. 定義通知模板（可在後台編輯狀態文案）

## Changelog

| 版本 | 日期 | 變更 |
|------|------|------|
| v0.1 | 2026-04-27 | 初稿（從 docs/features/line-order-query.md 反向萃取） |

---

Retrofit 產生於 2026-04-27，來源：docs/features/line-order-query.md
