# Roadmap: BuyGo+1 v1.2

**Created:** 2026-02-01
**Milestone:** v1.2 LINE 通知觸發機制整合
**Starting Phase:** 28（延續 v1.1 的 Phase 27）

## Phase Overview

| # | Phase | Goal | Requirements | Success Criteria |
|---|-------|------|--------------|------------------|
| 28 | 基礎架構與整合 | 建立身份識別和 buygo-line-notify 整合基礎 | IDENT-01~03, INTEG-01~03 | 4 |
| 29 | Bot 回應邏輯 | 實作 bot 對不同身份的回應規則 | BOT-01~04 | 4 |
| 30 | 商品上架通知 | 透過 LINE 上架商品時發送通知 | PROD-01~03 | 3 |
| 31 | 訂單通知 | 訂單建立和狀態變更通知 | ORD-01~04 | 4 |

---

## Phase 28: 基礎架構與整合

**Goal:** 建立身份識別服務和 buygo-line-notify 整合基礎，為後續通知功能打下基礎

**Requirements:**
- IDENT-01: 查詢 LINE UID 對應的 WordPress User ID
- IDENT-02: 判斷用戶角色（賣家/小幫手/買家）
- IDENT-03: 判斷用戶是否有 LINE 綁定
- INTEG-01: 監聽 buygo-line-notify 發出的 WordPress hooks
- INTEG-02: 呼叫 MessagingService 發送 LINE 推播
- INTEG-03: 移植並使用 NotificationTemplates 模板系統

**Success Criteria:**
1. IdentityService 可以根據 LINE UID 查詢並返回用戶角色
2. NotificationService 可以成功呼叫 buygo-line-notify 的 MessagingService
3. NotificationTemplates 模板系統可以產生格式化的通知訊息
4. 單元測試通過

**Technical Notes:**
- 建立 `IdentityService` 類別處理身份識別
- 建立 `NotificationService` 類別處理通知發送
- 移植 `NotificationTemplates` 從舊外掛
- 使用 WordPress hooks 監聽 buygo-line-notify 事件
- Soft dependency：buygo-line-notify 未啟用時優雅降級

**Dependencies:**
- buygo-line-notify 外掛（soft dependency）
- v1.1 完成的 wp_buygo_helpers 資料表

---

## Phase 29: Bot 回應邏輯

**Goal:** 根據用戶身份決定 bot 是否回應訊息

**Requirements:**
- BOT-01: 賣家發送訊息時，bot 正常回應
- BOT-02: 小幫手發送訊息時，bot 正常回應
- BOT-03: 買家發送訊息時，bot 不回應（靜默）
- BOT-04: 未綁定用戶發送訊息時，bot 不回應（靜默）

**Success Criteria:**
1. 賣家傳訊息給 bot，bot 能正常處理並回應
2. 小幫手傳訊息給 bot，bot 能正常處理並回應
3. 買家傳訊息給 bot，bot 不發送任何訊息
4. 未綁定用戶傳訊息給 bot，bot 不發送任何訊息

**Technical Notes:**
- 在 buygo-line-notify 的 webhook handler 加入 filter hook
- buygo-plus-one 監聽 filter 並根據身份決定是否繼續處理
- 返回 `false` 表示不處理（靜默）
- 使用 Phase 28 建立的 IdentityService

**Dependencies:**
- Phase 28 完成（IdentityService）

---

## Phase 30: 商品上架通知

**Goal:** 當賣家透過 LINE 上架商品時，通知相關人員

**Requirements:**
- PROD-01: 當賣家透過 LINE 上架商品時，觸發通知事件
- PROD-02: 發送通知給商品擁有者（賣家）
- PROD-03: 發送通知給所有已綁定 LINE 的小幫手

**Success Criteria:**
1. 透過 LINE 上架商品後，賣家收到「商品上架成功」通知
2. 透過 LINE 上架商品後，所有已綁定 LINE 的小幫手收到通知
3. FluentCart 後台新增商品時，不發送通知

**Technical Notes:**
- 監聽 `buygo_product_created_via_line` hook（或類似）
- 使用 SettingsService.get_helpers() 取得小幫手列表
- 使用 IdentityService 判斷誰有 LINE 綁定
- 使用 NotificationService 發送通知
- 通知模板：product_created

**Dependencies:**
- Phase 28 完成（NotificationService, IdentityService）
- buygo-line-notify 的商品建立 hook

---

## Phase 31: 訂單通知

**Goal:** 訂單建立和狀態變更時發送通知給相關人員

**Requirements:**
- ORD-01: 新訂單建立時，通知賣家
- ORD-02: 新訂單建立時，通知所有已綁定 LINE 的小幫手
- ORD-03: 新訂單建立時，通知買家（如果買家有 LINE 綁定）
- ORD-04: 訂單狀態變更時，僅通知買家

**Success Criteria:**
1. 新訂單建立時，賣家收到「新訂單」通知
2. 新訂單建立時，小幫手收到「新訂單」通知
3. 新訂單建立時，買家收到「訂單已建立」通知（如有 LINE 綁定）
4. 訂單狀態變更（如：已出貨、已完成），僅買家收到通知

**Technical Notes:**
- 監聽 FluentCart 訂單 hooks：
  - `fluent_cart/order/created` — 新訂單
  - `fluent_cart/order/status_changed` — 狀態變更
- 訂單通知需要找出商品的 post_author（賣家）
- 使用 SettingsService.get_helpers() 取得小幫手
- 通知模板：
  - seller_order_created
  - helper_order_created
  - buyer_order_created
  - buyer_order_status_changed

**Dependencies:**
- Phase 28 完成（NotificationService, IdentityService）
- Phase 30 完成（商品通知模式可複用）

---

## Milestone Success Criteria

v1.2 完成時，系統應具備：

1. **身份識別**
   - 可根據 LINE UID 識別用戶身份
   - 可判斷用戶是賣家、小幫手、買家或未綁定

2. **Bot 回應邏輯**
   - 賣家/小幫手可與 bot 互動
   - 買家/未綁定用戶發訊息時 bot 靜默

3. **商品上架通知**
   - LINE 上架商品 → 賣家 + 小幫手收到通知
   - FluentCart 後台操作不觸發通知

4. **訂單通知**
   - 新訂單 → 賣家 + 小幫手 + 買家 收到通知
   - 狀態變更 → 僅買家收到通知

5. **整合**
   - 與 buygo-line-notify 正確整合
   - 模板系統可自訂訊息內容

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| buygo-line-notify API 變更 | Low | High | 使用版本檢查，soft dependency |
| LINE API 限制（發送頻率） | Medium | Medium | 實作佇列機制，批次發送 |
| 通知太頻繁造成用戶困擾 | Medium | Low | 提供通知設定選項（未來功能）|

---

*Roadmap created: 2026-02-01*
*Last updated: 2026-02-01 after initial creation*
