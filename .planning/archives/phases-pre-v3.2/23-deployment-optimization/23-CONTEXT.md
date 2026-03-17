# Phase 23: 部署優化 - Context

**Gathered:** 2026-01-31
**Status:** Ready for planning

<domain>
## Phase Boundary

完成三個部署和使用者體驗優化：
1. **GitHub 自動更新機制** - 透過 plugin-update-checker 函式庫實現
2. **Rewrite Rules 自動 Flush** - 外掛啟用時自動讓自訂路由生效
3. **Portal UI 社群連結** - 在 BuyGo Portal 前台右上角新增前往社群的圖示按鈕

**重要範圍調整:**
原 DEPLOY-03 是「WordPress 後台新增前往 Portal 按鈕」，經討論後改為「Portal 前台新增前往社群按鈕」。

</domain>

<decisions>
## Implementation Decisions

### Portal 社群按鈕（DEPLOY-03 調整後）

**位置與設計:**
- 在 BuyGo Portal 前台右上角（搜尋框和幣別選擇旁邊）
- 只有社群 icon，不含文字
- 使用自定義 SVG 圖示（與 BuyGo 品牌一致）
- 點擊後在新分頁打開 FluentCommunity 社群頁面

**權限:**
- 無權限限制（Portal 前台功能，所有登入的賣家/小幫手都能看到）

### GitHub 自動更新機制（DEPLOY-01）

**更新通知位置:**
- 在 WordPress 外掛頁面顯示更新通知（標準做法）

**更新行為:**
- 自動更新（檢測到新版本後自動下載安裝）

**檢查頻率:**
- 每 24 小時檢查一次

**通知內容:**
- 由 Claude 決定必要的資訊（版本號碼、更新說明、發佈時間等）

### Rewrite Rules Flush（DEPLOY-02）

**Flush 時機:**
- 由 Claude 決定必要的時機（通常是啟用時、更新後、停用時）

**Flush 失敗處理:**
- 阻擋外掛啟用（不讓外掛在路由無法正常工作的情況下啟用）
- 顯示明確的錯誤訊息

**手動 Flush 按鈕:**
- 由 Claude 決定是否需要（可能在 BuyGo+1 設定頁面提供「重建路由」按鈕）

### 錯誤處理與回退機制

**GitHub API 連線失敗:**
- 顯示通知（在 WordPress 後台顯示 admin notice）
- 保留時間：手動關閉（通知一直顯示直到管理員手動關閉）

**更新後出現問題的回退:**
- 由 Claude 決定最實用的方式（可能提供回退按鈕或依賴 WordPress 自帶的回退機制）

### Claude's Discretion

- GitHub 更新通知的具體內容和格式
- Rewrite Rules 的具體 flush 時機
- 是否需要提供手動 flush 按鈕
- 更新回退機制的實作方式
- 錯誤通知的樣式和文案
- SVG 圖示的具體設計（只要符合 BuyGo 品牌風格）

</decisions>

<specifics>
## Specific Ideas

**Portal 社群按鈕:**
- 目的：讓賣家和小幫手能夠快速從 Portal 進入 FluentCommunity 社群
- 位置參考：在 Portal header 的右上角區域，與搜尋框、幣別選擇器對齊
- 簡潔設計：只用 icon 不用文字，保持介面乾淨

**GitHub 更新機制:**
- 使用 `yahnis-elsts/plugin-update-checker` 函式庫（研究文件已確認）
- 參考 ShortLinkRoutes 的 flag-based flush 實作（已有成功案例）

</specifics>

<deferred>
## Deferred Ideas

**WordPress 後台的 Portal 按鈕:**
原本的 DEPLOY-03 是在 WP 後台新增「前往 BuyGo Portal」按鈕，這個需求在討論中被替換為「Portal 前台社群按鈕」。

如果未來仍需要 WP 後台的 Portal 快捷按鈕，可以作為獨立的小改進加入後續 Phase。

</deferred>

---

*Phase: 23-deployment-optimization*
*Context gathered: 2026-01-31*
