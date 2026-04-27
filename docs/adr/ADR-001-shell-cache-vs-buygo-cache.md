# ADR-001: Shell 快取 vs 傳統 Server-Rendered

| 欄位 | 值 |
|------|-----|
| 決策日期 | 2026-03-31 |
| 狀態 | Accepted |
| 相關 | SPEC-007, SPEC-008, docs/features/shell-cache-architecture.md |
| 影響範圍 | 買家入口、賣家後台所有頁面 |

## 背景

BuyGo 首次載入耗時 2.5+ 秒，主要因為 PHP 需要組裝 HTML + 查資料庫。目標是降至 0.6 秒以內。

**現狀問題**：
- Cloudflare 快取效果受限（每次都等 PHP 回應）
- 資料變化快（訂單狀態即時更新），難以長期快取完整頁面
- 使用者感受：點開頁面要等 2.5 秒才看到東西

## 選項考量

### 選項 A — 保留 Server-Rendered (PHP 直接回傳完整頁面)

**優點**：
- 架構簡單，現有程式碼無須重寫
- 無 SPA 複雜度，JavaScript 邏輯簡單

**缺點**：
- 每次載入都等 PHP 2.5 秒，無法改善使用者體驗
- 快取效率低（資料變化頻繁，難以設長期 TTL）

### 選項 B — Shell 快取 + API 資料分離

架構：
```
HTML 殼（固定框架）→ Cloudflare 快取（0.1 秒）
        ↓
        JS 打 API 載入資料（0.5 秒）
        ↓
        資料填入殼（完成）
```

**優點**：
- Cloudflare 快取殼（0.1 秒）
- API 資料獨立（0.5 秒），可優化 Redis + DB query
- 總耗時 0.6 秒，提升 75%
- 資料變化快時，只需更新 API，殼保持快取

**缺點**：
- 前端改造：Vue 需實作非同步資料載入
- 需建立 REST API endpoints（成本）
- 首次載入體驗短暫有骨架屏（可接受）

## 決策

**選擇 Option B — Shell 快取 + API 資料分離**

理由：
1. 對使用者感受改善最大（2.5s → 0.6s）
2. 買賣家頁面需求明確，可逐頁遷移降低風險
3. API 投資回報高（支援 LINE 訂單查詢等其他客戶端）
4. 參考 FluentCommunity 已驗證可行

## 後果

### 正面影響

- 首次載入時間大幅降低
- API 層可獨立擴展（支援移動端、小程式等）
- 資料更新無須重新組裝 HTML，更靈活

### 負面影響 / 風險

- 開發成本：買家頁面 API + 賣家後台 API 都需建立
- 前端測試增加：需驗證骨架屏、資料非同步載入
- 快取策略複雜化：需管理 Cloudflare + Redis + API 層快取

### 後續行動

1. 識別所有買家頁面和賣家頁面的資料接口
2. 逐頁建立 REST API endpoint（`GET /api/customer/dashboard`、`GET /api/dashboard/stats` 等）
3. 使用 `wp_localize_script` 注入初始資料（減少首次 API 呼叫）
4. 用 Cloudflare Cache-Control header 管理殼快取（TTL 24h）
5. API 資料層配置 Redis 快取（TTL 5-10m）

## Changelog

| 版本 | 日期 | 變更 |
|------|------|------|
| v0.1 | 2026-04-27 | 初稿（從 docs/features/shell-cache-architecture.md 反向萃取） |

---

Retrofit 產生於 2026-04-27，來源：docs/features/shell-cache-architecture.md
