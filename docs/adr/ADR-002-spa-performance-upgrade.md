# ADR-002: SPA 效能升級遷移路徑

| 欄位 | 值 |
|------|-----|
| 決策日期 | 2026-03-31 |
| 狀態 | Accepted |
| 相關 | ADR-001 (Shell 快取), docs/features/spa-performance-upgrade.md |
| 影響範圍 | 賣家後台全部頁面 |

## 背景

賣家後台首次載入 3 秒，目標降至 < 1 秒。瓶頸分析（按影響排序）：

| # | 瓶頸 | 延遲 | 原因 |
|---|------|------|------|
| 1 | Tailwind CDN | 1~2s | 357KB JS + 執行時間 |
| 2 | 同步 inline JS | 0.5~1s | 13 composable + 9 partial 依序解析 |
| 3 | REST dispatch | 0.3~0.5s | 7 次串行 API 呼叫 |
| 4 | 全頁加載 JS | 0.3s | 511KB partial 不管用戶看哪頁都載 |
| 5 | CDN (Fonts/jsDelivr) | 0.2~0.5s | DNS 查詢 + 跨國下載 |

## 選項考量

### 選項 A — 一次全部重寫 (Vite SPA)

**優點**：
- 架構統一，長期維護簡單

**缺點**：
- 成本高（幾週開發）
- 風險大（現有頁面可能有遺漏 edge case）
- 無法逐步驗證

### 選項 B — 逐步遷移（保留現有，Phase 1-4 漸進升級）

架構：
```
Phase 1: 建 Vite + Tailwind 打包環境（無程式碼改動）
Phase 2: 替換 Tailwind CDN（最大效果）
Phase 3: JS defer + 資料直出（wp_localize_script）
Phase 4: 按頁面拆分 JS（optional 進一步優化）
```

**優點**：
- 低風險：每一步都有測試點
- 可追蹤進度：各 Phase 都能量測改善
- 參考 FluentCommunity 已驗證可行
- 舊新架構可並存（漸進式升級）

**缺點**：
- 分階段投入（無一次完成快感）
- 短期維護代碼重複（舊新並存）

## 決策

**選擇 Option B — 逐步遷移**

理由：
1. Phase 1 無風險（純新增打包產出，不改現有碼）
2. Phase 2 最快見效（光替換 CSS 就省 1~2s）
3. 團隊規模小，逐步交付更可控
4. 參考案例已驗證（FluentCommunity）

## 各 Phase 詳細

### Phase 1 — 建立打包環境（基礎建設）

目標：Vite + Tailwind 本地打包，但不動現有程式碼

做的事：
- 安裝 Vite + Tailwind CSS (dev dependency)
- 配置 `vite.config.js` 和 `tailwind.config.js`
- 掃描所有 PHP/JS 模板檔案
- 產出 `dist/app.css` (靜態 Tailwind 輸出)
- 比對 CDN 版 vs 本地版的差異

### Phase 2 — 替換 Tailwind CDN（最大效果）

目標：用本地 CSS 取代 CDN，省 1~2 秒

做的事：
- 改 `<script src="cdn.tailwindcss.com">` → `<link href="dist/app.css">`
- 移除 Google Fonts CDN（改 `font-display: swap` 或本地字型）

預期：載入時間 3s → 1.5s

### Phase 3 — JS defer + 資料直出（再省 1 秒）

目標：JS 不阻塞 + 資料不等 API

做的事：
- Vue + SortableJS + VueDraggable 加 `defer`
- `buygo_get_initial_data()` 改呼叫 Service 直接返回（不走 REST dispatch）
- 用 `wp_localize_script` 注入 `window.buygoInitialData`

預期：載入時間 1.5s → 1.0s

### Phase 4 — 按頁面拆分 JS（Optional，進一步優化）

目標：首屏只載入當前頁面 JS

做的事：
- 9 個 partial 改為獨立 JS 模組
- Vite code-split
- 路由切換時 dynamic import

預期：首屏 JS 511KB → ~100KB

## 後果

### 正面影響

- 首次載入 3s → < 1s
- 頁面切換更快（SPA 特性）
- 開發體驗改善（Vite 熱更新）

### 負面影響 / 風險

- 構建步驟增加（npm run build）
- 測試範圍擴大（CSS + JS 打包需驗證）
- 暫時代碼重複（舊新並存）

### 後續行動

1. Phase 1 完成後評估 Tailwind 覆蓋度
2. Phase 2 逐頁測試（dashboard / products / orders / settings）
3. Phase 3 驗證資料初始化
4. Phase 4 根據實際需求決定是否執行

## Changelog

| 版本 | 日期 | 變更 |
|------|------|------|
| v0.1 | 2026-04-27 | 初稿（從 docs/features/spa-performance-upgrade.md 反向萃取） |

---

Retrofit 產生於 2026-04-27，來源：docs/features/spa-performance-upgrade.md
