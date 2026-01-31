# Requirements: BuyGo+1 v1.1

**Defined:** 2026-01-31
**Core Value:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

## v1.1 Requirements

Requirements for Milestone v1.1: 部署優化與會員權限。

### 部署優化 (DEPLOY)

- [ ] **DEPLOY-01**: 整合 plugin-update-checker 函式庫，透過 GitHub Releases 提供自動更新
- [ ] **DEPLOY-02**: 外掛啟用時自動 flush rewrite rules（使用 flag-based transient 方法）
- [ ] **DEPLOY-03**: 後台新增「前往 BuyGo Portal」快捷按鈕

### 資料架構 (DATA)

- [ ] **DATA-01**: 建立 `wp_buygo_helpers` 資料表（id, user_id, seller_id, created_at, updated_at）
- [ ] **DATA-02**: 確保商品查詢使用 `post_author` 進行賣家過濾（已有，需驗證所有 API 端點）
- [ ] **DATA-03**: 確保訂單查詢透過商品關聯進行賣家過濾

### Service Layer (SVC)

- [ ] **SVC-01**: Settings_Service.get_helpers(seller_id) — 取得特定賣家的小幫手列表
- [ ] **SVC-02**: Settings_Service.add_helper(user_id, seller_id) — 新增小幫手
- [ ] **SVC-03**: Settings_Service.remove_helper(user_id, seller_id) — 移除小幫手
- [ ] **SVC-04**: 權限檢查方法 — 判斷用戶可存取哪些賣場的商品（自己的 + 被授權的）
- [ ] **SVC-05**: 整合 LineUserService 查詢 LINE 綁定狀態（soft dependency）

### API Layer (API)

- [ ] **API-01**: GET /settings/helpers — 取得當前賣家的小幫手列表（含 LINE 綁定狀態）
- [ ] **API-02**: POST /settings/helpers — 新增小幫手（只有賣家可以，小幫手不行）
- [ ] **API-03**: DELETE /settings/helpers/{user_id} — 移除小幫手（只有賣家可以）
- [ ] **API-04**: 所有商品 API 端點加入 post_author 過濾（驗證現有實作）
- [ ] **API-05**: 所有訂單 API 端點加入賣家過濾（透過商品關聯）

### 前端 UI (UI)

- [ ] **UI-01**: Settings 頁面新增「會員權限管理」區塊
- [ ] **UI-02**: 小幫手列表顯示（姓名、Email、LINE 綁定狀態、新增時間）
- [ ] **UI-03**: 新增小幫手功能（從 WP 用戶搜尋/選擇）
- [ ] **UI-04**: 移除小幫手功能（確認對話框）
- [ ] **UI-05**: LINE 綁定狀態顯示（✅ 已綁定 / ⚠️ 未綁定 + 警告訊息）
- [ ] **UI-06**: LINE 綁定按鈕（小幫手登入後，未綁定時可點擊）
- [ ] **UI-07**: 小幫手角色隱藏「會員權限管理」區塊

### 權限控制 (PERM)

- [ ] **PERM-01**: 賣家可以看到自己的商品和小幫手上架的商品
- [ ] **PERM-02**: 小幫手可以看到賣家的商品（被授權的賣場）
- [ ] **PERM-03**: A 賣場無法看到 B 賣場的商品（隔離）
- [ ] **PERM-04**: 小幫手不能新增/移除其他小幫手
- [ ] **PERM-05**: 一個用戶可以同時是多個賣場的小幫手

### 賣家申請系統 (APPLY)

- [ ] **APPLY-01**: 申請表單（姓名、Email、LINE、申請理由）
- [ ] **APPLY-02**: 申請後自動批准為測試賣家（buygo_seller_type = 'test'）
- [ ] **APPLY-03**: Portal 申請入口（未成為賣家的用戶顯示申請按鈕）
- [ ] **APPLY-04**: Shortcode `[buygo_seller_application]` 可嵌入任意頁面
- [ ] **APPLY-05**: 申請成功後發送 Email 通知用戶
- [ ] **APPLY-06**: 記錄申請日期和狀態到 user_meta

### WP 後台管理 (ADMIN)

- [ ] **ADMIN-01**: WP 後台「BuyGo+1 設定」主選單頁面
- [ ] **ADMIN-02**: 賣家申請列表（顯示所有申請，可篩選測試/真實）
- [ ] **ADMIN-03**: 賣家升級功能（測試賣家 → 真實賣家，解除限制）
- [ ] **ADMIN-04**: 會員權限管理（與 Portal 同功能，在 WP 後台可操作）

## Out of Scope

| Feature | Reason |
|---------|--------|
| 資料遷移工具 | 現有商品會清空，不需遷移 |
| 賣家申請審核流程 | v1.1 自動批准為測試賣家，手動審核延後到未來 |
| 多樣式商品 | 延後到 v1.2 |
| LINE 通知功能 | 需先完成 buygo-line-notify v0.3 |

## Future Requirements (v1.2+)

### 多樣式商品

- **MULTI-01**: LINE 上架解析多樣式商品
- **MULTI-02**: FluentCart 多 variations 建立
- **MULTI-03**: 前台樣式選擇器

### LINE 通知

- **NOTIF-01**: 賣家收到訂單通知
- **NOTIF-02**: 買家收到狀態變更通知
- **NOTIF-03**: 依賴 buygo-line-notify v0.3


## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| DEPLOY-01 | Phase 23 | Pending |
| DEPLOY-02 | Phase 23 | Pending |
| DEPLOY-03 | Phase 23 | Pending |
| DATA-01 | Phase 24 | Pending |
| DATA-02 | Phase 24 | Pending |
| DATA-03 | Phase 24 | Pending |
| SVC-01 | Phase 24 | Pending |
| SVC-02 | Phase 24 | Pending |
| SVC-03 | Phase 24 | Pending |
| SVC-04 | Phase 24 | Pending |
| SVC-05 | Phase 24 | Pending |
| API-01 | Phase 25 | Pending |
| API-02 | Phase 25 | Pending |
| API-03 | Phase 25 | Pending |
| API-04 | Phase 25 | Pending |
| API-05 | Phase 25 | Pending |
| UI-01 | Phase 26 | Pending |
| UI-02 | Phase 26 | Pending |
| UI-03 | Phase 26 | Pending |
| UI-04 | Phase 26 | Pending |
| UI-05 | Phase 26 | Pending |
| UI-06 | Phase 26 | Pending |
| UI-07 | Phase 26 | Pending |
| PERM-01 | Phase 25 | Pending |
| PERM-02 | Phase 25 | Pending |
| PERM-03 | Phase 25 | Pending |
| PERM-04 | Phase 25 | Pending |
| PERM-05 | Phase 25 | Pending |
| APPLY-01 | Phase 27 | Pending |
| APPLY-02 | Phase 27 | Pending |
| APPLY-03 | Phase 27 | Pending |
| APPLY-04 | Phase 27 | Pending |
| APPLY-05 | Phase 27 | Pending |
| APPLY-06 | Phase 27 | Pending |
| ADMIN-01 | Phase 27 | Pending |
| ADMIN-02 | Phase 27 | Pending |
| ADMIN-03 | Phase 27 | Pending |
| ADMIN-04 | Phase 27 | Pending |

**Coverage:**
- v1.1 requirements: 35 total
- Mapped to phases: 35
- Unmapped: 0 ✓

---
*Requirements defined: 2026-01-31*
*Last updated: 2026-01-31 after initial definition*
