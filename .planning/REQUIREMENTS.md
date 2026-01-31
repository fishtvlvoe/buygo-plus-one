# Requirements: BuyGo+1 v1.2

**Defined:** 2026-02-01
**Core Value:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

## v1.2 Requirements

Requirements for Milestone v1.2: LINE 通知觸發機制整合。

### 商品上架通知 (PROD)

- [ ] **PROD-01**: 當賣家透過 LINE 上架商品時，觸發通知事件
- [ ] **PROD-02**: 發送通知給商品擁有者（賣家）
- [ ] **PROD-03**: 發送通知給所有已綁定 LINE 的小幫手

### 訂單通知 (ORD)

- [ ] **ORD-01**: 新訂單建立時，通知賣家
- [ ] **ORD-02**: 新訂單建立時，通知所有已綁定 LINE 的小幫手
- [ ] **ORD-03**: 新訂單建立時，通知買家（如果買家有 LINE 綁定）
- [ ] **ORD-04**: 訂單狀態變更時，僅通知買家

### 身份識別 (IDENT)

- [ ] **IDENT-01**: 查詢 LINE UID 對應的 WordPress User ID
- [ ] **IDENT-02**: 判斷用戶角色（buygo_admin = 賣家、buygo_helper = 小幫手、其他 = 買家）
- [ ] **IDENT-03**: 判斷用戶是否有 LINE 綁定

### Bot 回應邏輯 (BOT)

- [ ] **BOT-01**: 賣家發送訊息時，bot 正常回應
- [ ] **BOT-02**: 小幫手發送訊息時，bot 正常回應
- [ ] **BOT-03**: 買家發送訊息時，bot 不回應（靜默）
- [ ] **BOT-04**: 未綁定用戶發送訊息時，bot 不回應（靜默）

### 與 buygo-line-notify 整合 (INTEG)

- [ ] **INTEG-01**: 監聽 buygo-line-notify 發出的 WordPress hooks
- [ ] **INTEG-02**: 呼叫 MessagingService 發送 LINE 推播
- [ ] **INTEG-03**: 移植並使用 NotificationTemplates 模板系統

## Out of Scope

| Feature | Reason |
|---------|--------|
| 多樣式商品功能 | 延後到 v1.3 |
| LINE 綁定提示 | 未綁定用戶不發送任何訊息 |
| FluentCart 後台操作通知 | 僅 LINE 上架觸發通知 |

## Future Requirements (v1.3+)

### 多樣式商品

- **MULTI-01**: LINE 上架解析多樣式商品
- **MULTI-02**: FluentCart 多 variations 建立
- **MULTI-03**: 前台樣式選擇器

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| IDENT-01 | Phase 28 | Pending |
| IDENT-02 | Phase 28 | Pending |
| IDENT-03 | Phase 28 | Pending |
| INTEG-01 | Phase 28 | Pending |
| INTEG-02 | Phase 28 | Pending |
| INTEG-03 | Phase 28 | Pending |
| BOT-01 | Phase 29 | Pending |
| BOT-02 | Phase 29 | Pending |
| BOT-03 | Phase 29 | Pending |
| BOT-04 | Phase 29 | Pending |
| PROD-01 | Phase 30 | Pending |
| PROD-02 | Phase 30 | Pending |
| PROD-03 | Phase 30 | Pending |
| ORD-01 | Phase 31 | Pending |
| ORD-02 | Phase 31 | Pending |
| ORD-03 | Phase 31 | Pending |
| ORD-04 | Phase 31 | Pending |

**Coverage:**
- v1.2 requirements: 17 total
- Mapped to phases: 17
- Unmapped: 0 ✓

---
*Requirements defined: 2026-02-01*
*Last updated: 2026-02-01 after initial definition*
