# SPA 效能升級計畫 — 1 秒內載入

## 現況 vs 目標

| | 現在 | 目標 |
|---|---|---|
| 首次載入 | 3 秒 | < 1 秒 |
| 頁面切換 | 重新載入整頁 | 即時切換（SPA） |
| 架構 | CDN + inline JS + REST API | 本地打包 + 資料直出 + lazy load |

## 參考對象：FluentCommunity 的做法

FluentCommunity 能 1 秒內載入，因為：
1. Vite 打包所有 JS/CSS 到本地（零 CDN 依賴）
2. 初始資料用 `wp_localize_script` 直接塞進 HTML（不等 API）
3. JS 用 `type="module" defer` 不阻塞頁面
4. 按路由 lazy load，首屏只載入需要的程式碼

## BuyGo 目前的瓶頸（按影響大小排序）

| # | 瓶頸 | 延遲 | 原因 |
|---|------|------|------|
| 1 | Tailwind CDN 在 `<head>` 阻塞 | 1~2 秒 | 357KB JS 要下載+執行才能繼續渲染 |
| 2 | 所有 JS 同步 inline 載入 | 0.5~1 秒 | 13 個 composable + 9 個 partial 依序解析 |
| 3 | 初始資料走 REST dispatch | 0.3~0.5 秒 | 7 次 rest_do_request 串行執行 |
| 4 | 所有頁面 JS 一次全載 | 0.3 秒 | 511KB partials 不管用戶看哪頁都載入 |
| 5 | Google Fonts + jsDelivr CDN | 0.2~0.5 秒 | 額外的 DNS 查詢和跨國下載 |

## 升級策略：逐步遷移，一次一頁

### 原則
- **不全部重寫**，一頁一頁遷移
- 每一步都有測試，確認沒壞才進下一步
- 新舊架構可以並存（漸進式升級）
- 先在本機測試，確認 OK 再推

### Phase 1：建立打包環境（基礎建設）
**目標：** 建好 Vite + Tailwind 打包環境，但不動現有程式碼

做的事：
- 安裝 Vite + Tailwind CSS（dev dependency）
- 建立 `vite.config.js` 和 `tailwind.config.js`
- 設定掃描範圍（所有 PHP/JS 模板檔案）
- 跑一次打包，產出 `dist/app.css`（Tailwind 靜態 CSS）
- 把 `dist/app.css` 跟 Tailwind CDN 的輸出做 diff 比對

測試方式：
- 截圖工具對比每個頁面：CDN 版 vs 本地打包版
- 如果有 class 漏掉，補進 `tailwind.config.js` 的 content 掃描範圍
- 不影響現有程式碼，只是新增打包產出

完成條件：
- `dist/app.css` 涵蓋所有現有頁面的樣式
- 截圖對比 0 差異

### Phase 2：替換 Tailwind CDN（最大效果）
**目標：** 用本地 CSS 取代 CDN，省 1~2 秒

做的事：
- `template.php` 的 `<script src="cdn.tailwindcss.com">` 改成 `<link href="dist/app.css">`
- 移除 Google Fonts CDN，改用本地字型或 `font-display: swap`

測試方式：
- 逐頁確認樣式沒壞（dashboard、products、orders、settings）
- 用瀏覽器 DevTools 的 Performance 面板量測載入時間

完成條件：
- 所有頁面樣式正常
- 載入時間從 3 秒降到 ~1.5 秒

### Phase 3：JS defer + 資料直出（再省 1 秒）
**目標：** JS 不阻塞 + 資料不等 API

做的事：
- `vue.global.prod.js` 加 `defer`
- SortableJS、VueDraggable 本地化 + `defer`
- `buygo_get_initial_data()` 裡的 `rest_do_request()` 改為直接呼叫 Service
- 用 `wp_localize_script` 注入初始資料（跟 FluentCommunity 一樣）

測試方式：
- 確認 Vue mount 後資料已在 `window.buygoInitialData`
- 確認所有頁面的互動功能正常（按鈕、篩選、分頁）

完成條件：
- 載入時間降到 ~1 秒

### Phase 4：按頁面拆分 JS（optional，進一步優化）
**目標：** 首屏只載入當前頁面的 JS

做的事：
- 9 個 partial 改為獨立 JS 模組
- 用 Vite 打包成 code-split chunks
- 路由切換時 dynamic import 載入

測試方式：
- 每個頁面功能逐一測試
- 確認 lazy load 的模組正確載入

完成條件：
- 首屏 JS 從 511KB 降到 ~100KB
- 載入時間 < 0.8 秒

## 每個 Phase 的安全機制

1. **Git 分支隔離**：每個 Phase 開一個 `feature/spa-phase-X` 分支
2. **截圖對比**：改動前後每個頁面截圖比對
3. **功能測試**：核心流程（商品列表、訂單、分配、出貨）手動走一遍
4. **可回滾**：出問題直接切回 main，不影響線上
5. **不動線上**：全部在本機測完，你確認才部署

## 不影響的部分

- LINE 訊息收發（純後端 PHP，跟前端架構無關）
- REST API 端點（不改 API，只改前端怎麼拿資料）
- 資料庫結構（完全不動）
- 現有的商業邏輯（services/ 完全不動）

## 時間估計

| Phase | 工時 | 效果 |
|-------|------|------|
| 1 建立打包環境 | 2 小時 | 無（基礎建設） |
| 2 替換 Tailwind CDN | 1 小時 | 3 秒 → 1.5 秒 |
| 3 JS defer + 資料直出 | 2 小時 | 1.5 秒 → 1 秒 |
| 4 按頁面拆分（optional）| 3 小時 | 1 秒 → 0.8 秒 |

Phase 1+2 效果最大、風險最低，建議先做。
