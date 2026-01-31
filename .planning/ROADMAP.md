# Roadmap: BuyGo+1 v1.1

**Created:** 2026-01-31
**Milestone:** v1.1 部署優化與會員權限
**Starting Phase:** 23（延續 v1.0 的 Phase 22）

## Phase Overview

| # | Phase | Goal | Requirements | Success Criteria |
|---|-------|------|--------------|------------------|
| 23 | 部署優化 | 完成 GitHub 更新、Rewrite Flush、Portal 按鈕 | DEPLOY-01~03 | 3 |
| 24 | 資料架構與 Service | 建立 helpers 資料表和 Service 方法 | DATA-01~03, SVC-01~05 | 5 |
| 25 | API 權限過濾 | 所有 API 端點加入賣家權限檢查 | API-01~05, PERM-01~05 | 5 |
| 26 | 前端 UI（Portal） | Portal Settings 會員權限管理 UI | UI-01~07 | 4 |
| 27 | 賣家申請與 WP 後台 | 申請表單、審核系統、WP 後台設定頁 | APPLY-01~06, ADMIN-01~04 | 5 |

---

## Phase 23: 部署優化

**Goal:** 完成 GitHub 自動更新機制、Rewrite Rules 自動 Flush、Portal 快捷按鈕

**Requirements:**
- DEPLOY-01: 整合 plugin-update-checker 函式庫
- DEPLOY-02: 外掛啟用時自動 flush rewrite rules
- DEPLOY-03: 後台新增「前往 BuyGo Portal」按鈕

**Success Criteria:**
1. 用戶可在 WP 後台看到 BuyGo+1 更新通知（當 GitHub 有新 Release 時）
2. 外掛啟用後，自訂路由立即生效（無需手動 flush）
3. WP 後台有「前往 BuyGo Portal」按鈕，點擊可跳轉到 /buygo-portal/

**Technical Notes:**
- 使用 `yahnis-elsts/plugin-update-checker` via Composer
- 使用 flag-based transient 方法處理 rewrite rules
- ShortLinkRoutes 已有參考實作

**Dependencies:**
- 無外部依賴

---

## Phase 24: 資料架構與 Service

**Goal:** 建立 wp_buygo_helpers 資料表和 Settings_Service 擴充方法

**Requirements:**
- DATA-01: 建立 wp_buygo_helpers 資料表
- DATA-02: 驗證商品查詢的 post_author 過濾
- DATA-03: 驗證訂單查詢的賣家過濾
- SVC-01: Settings_Service.get_helpers(seller_id)
- SVC-02: Settings_Service.add_helper(user_id, seller_id)
- SVC-03: Settings_Service.remove_helper(user_id, seller_id)
- SVC-04: 權限檢查方法
- SVC-05: 整合 LineUserService

**Success Criteria:**
1. wp_buygo_helpers 資料表在外掛啟用時自動建立
2. get_helpers 返回正確的小幫手列表（含 LINE 綁定狀態）
3. add_helper/remove_helper 正確更新資料表
4. 權限檢查方法返回用戶可存取的 seller_ids 列表
5. 單元測試通過

**Technical Notes:**
- 資料表結構：id, user_id, seller_id, created_at, updated_at
- UNIQUE KEY (user_id, seller_id) 防止重複
- LineUserService::isUserLinked() soft dependency

**Dependencies:**
- buygo-line-notify 外掛（soft dependency）

---

## Phase 25: API 權限過濾

**Goal:** 所有 API 端點加入賣家權限檢查，確保多賣家隔離

**Requirements:**
- API-01: GET /settings/helpers（含 LINE 狀態）
- API-02: POST /settings/helpers（賣家限定）
- API-03: DELETE /settings/helpers/{user_id}（賣家限定）
- API-04: 商品 API post_author 過濾
- API-05: 訂單 API 賣家過濾
- PERM-01~05: 權限規則實作

**Success Criteria:**
1. 小幫手呼叫 POST/DELETE helpers API 返回 403
2. 賣家 A 無法查詢到賣家 B 的商品
3. 小幫手可以查詢被授權賣場的商品
4. 一個用戶作為多個賣場的小幫手時，可查詢所有授權賣場的商品
5. API 測試通過（可用 curl 或 Postman 驗證）

**Technical Notes:**
- check_admin_permission() 區分賣家和小幫手
- 商品查詢：WHERE post_author IN (可存取的 seller_ids)
- 訂單查詢：透過 order_items.post_id → posts.post_author

**Dependencies:**
- Phase 24 完成

---

## Phase 26: 前端 UI（Portal）

**Goal:** BuyGo Portal Settings 頁面會員權限管理 UI

**Requirements:**
- UI-01: 「會員權限管理」區塊
- UI-02: 小幫手列表（姓名、Email、LINE 狀態、新增時間）
- UI-03: 新增小幫手（WP 用戶搜尋）
- UI-04: 移除小幫手（確認對話框）
- UI-05: LINE 綁定狀態顯示
- UI-06: LINE 綁定按鈕
- UI-07: 小幫手角色隱藏此區塊

**Success Criteria:**
1. 賣家可在 Portal Settings 看到「會員權限管理」區塊
2. 小幫手列表正確顯示，含 LINE 綁定狀態圖示
3. 可成功新增小幫手（從 WP 用戶選擇）
4. 可成功移除小幫手（有確認對話框）
5. 未綁定 LINE 的小幫手顯示警告訊息
6. 小幫手登入後看不到「會員權限管理」區塊

**Technical Notes:**
- Vue 3 元件
- 使用現有設計系統元件（.data-table, .btn, .status-tag）
- LINE 綁定按鈕連結到 buygo-line-notify 的綁定頁面

**Dependencies:**
- Phase 25 完成（API 端點）

---

## Phase 27: 賣家申請與 WP 後台

**Goal:** 用戶可自助申請成為測試賣家，管理員在 WP 後台管理

**Requirements:**

### 賣家申請系統 (APPLY)
- APPLY-01: 申請表單（姓名、Email、LINE、申請理由）
- APPLY-02: 申請後自動批准為測試賣家（buygo_seller_type = 'test'）
- APPLY-03: Portal 申請入口（未成為賣家的用戶看到申請按鈕）
- APPLY-04: Shortcode `[buygo_seller_application]` 可放任意頁面
- APPLY-05: 申請成功後發送 Email 通知
- APPLY-06: 記錄申請日期和狀態

### WP 後台管理頁面 (ADMIN)
- ADMIN-01: WP 後台「BuyGo+1 設定」主選單
- ADMIN-02: 賣家申請列表（顯示所有申請，可篩選測試/真實）
- ADMIN-03: 賣家升級功能（測試賣家 → 真實賣家，解除商品限制）
- ADMIN-04: 會員權限管理（同 Portal，在 WP 後台也可操作）

**Success Criteria:**
1. 一般用戶可在 Portal 或 Shortcode 頁面申請成為賣家
2. 申請後自動成為測試賣家（有商品數量限制）
3. 管理員可在 WP 後台看到所有申請
4. 管理員可升級測試賣家為真實賣家
5. WP 後台可管理小幫手（與 Portal 功能相同）

**Technical Notes:**
- 測試賣家商品限制：從 user_meta `buygo_product_limit` 讀取（預設 10 件）
- 真實賣家：`buygo_product_limit` = 0（無限制）
- WP 後台使用 WordPress Settings API
- 申請狀態：pending（待審核）、approved（已批准）、rejected（已拒絕）

**Dependencies:**
- Phase 24 完成（Service Layer）
- Phase 25 完成（API 端點）

---

## Milestone Success Criteria

v1.1 完成時，系統應具備：

1. **部署優化**
   - GitHub Releases 自動更新可用
   - 外掛啟用後路由立即生效
   - Portal 快捷按鈕可用

2. **多賣家隔離**
   - 賣家 A 無法看到賣家 B 的商品/訂單
   - 小幫手可看到被授權賣場的資料
   - 一個用戶可作為多個賣場的小幫手

3. **會員權限管理**
   - 賣家可新增/移除小幫手
   - 小幫手不能管理其他小幫手
   - LINE 綁定狀態清楚顯示

4. **賣家申請系統**
   - 一般用戶可自助申請成為測試賣家
   - 申請後自動批准（測試賣家有商品限制）
   - 管理員可在 WP 後台升級為真實賣家

5. **WP 後台管理**
   - 管理員可在 WordPress 後台管理所有功能
   - 賣家申請列表清楚顯示
   - 會員權限管理與 Portal 同步

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| FluentCart API 變更 | Low | High | 使用 post_author 是穩定的 WordPress 原生欄位 |
| buygo-line-notify 未啟用 | Medium | Low | Soft dependency，顯示提示訊息 |
| 權限邏輯複雜度 | Medium | Medium | 單元測試覆蓋權限檢查方法 |

---

*Roadmap created: 2026-01-31*
*Last updated: 2026-01-31 after initial creation*
