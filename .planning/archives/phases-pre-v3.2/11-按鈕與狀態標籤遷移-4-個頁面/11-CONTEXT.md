---
phase: 11-按鈕與狀態標籤遷移-4-個頁面
created: 2026-01-28
status: ready_for_planning
---

# Phase 11: 按鈕與狀態標籤遷移 - Implementation Context

## Phase Goal

所有頁面的按鈕和狀態標籤遷移到設計系統,統一使用 `.btn` 和 `.status-tag` classes

## 面向 1: 按鈕類型與用途劃分

### Primary 按鈕使用規則

**適用範圍**: 所有確認性操作

包含但不限於:
- 儲存/提交表單
- 確認操作
- 主要的 CTA (Call-to-Action)
- 建立新項目

**設計系統 class**: `.btn .btn-primary`

### Secondary 按鈕使用規則

**適用範圍**:
- 取消操作
- 返回上一頁
- 輔助性功能 (如匯出、篩選)
- 與主要操作並列但優先級較低的操作

**設計系統 class**: `.btn .btn-secondary`

### Danger 按鈕使用規則

**適用範圍**:
- 刪除操作
- 取消訂單
- 所有不可逆的操作
- 需要二次確認的危險操作

**設計系統 class**: `.btn .btn-danger`

### 頁面多操作按鈕的層級規劃

當頁面有多個操作按鈕時 (例如 products.php 的分配/編輯/刪除):

**視覺層級策略**:
- 危險操作 (刪除) 使用紅色 danger 樣式
- 其他操作根據使用頻率和重要性決定樣式
- 靠位置區分優先級 (主要操作在前,次要操作在後)

**商品頁面特殊按鈕設計** (PROD-03):
1. **分配按鈕**: 改用三個疊加的小 icon 呈現
2. **編輯按鈕**: 使用文字顯示
3. **刪除按鈕**: 未來實作時使用 danger 樣式

**互動細節保留**:
- 點擊「下單數量」→ 查看當前下單人數
- 點擊「產品名稱」→ 進入編輯頁面
- 點擊「圖片」→ 上傳新圖片

## 面向 2: 狀態標籤顏色語義

### 綠色狀態標籤 (Success)

**適用範圍**: 正面/成功的狀態

包含:
- 已上架
- 已出貨
- 已完成
- 其他表示完成/成功的狀態

**設計系統 class**: `.status-tag .status-success`

### 灰色狀態標籤 (Neutral)

**適用範圍**: 中性的狀態

包含:
- 未出貨
- 待處理 (初始狀態)
- 其他中性狀態

**設計系統 class**: `.status-tag .status-neutral`

### 淡藍色狀態標籤 (Info)

**適用範圍**: 待處理

包含:
- 待處理 (需要採取行動的狀態)

**設計系統 class**: `.status-tag .status-info`

### 黃色狀態標籤 (Warning)

**適用範圍**: 處理中

包含:
- 處理中 (進行中的狀態)
- 其他需要注意的狀態

**設計系統 class**: `.status-tag .status-warning`

### 紅色狀態標籤 (Danger)

**適用範圍**: 已取消或錯誤

包含:
- 已取消
- 失敗
- 錯誤狀態

**設計系統 class**: `.status-tag .status-danger`

## 面向 3: 狀態標籤 Icon 使用策略

### 使用原則

**視情況而定** - 根據以下條件決定是否使用 icon:

1. **空間限制**: 當頁面文字過長或欄寬受限時,優先使用 icon 取代文字
2. **快速識別**: 對於需要快速識別的狀態,加上 icon 提升辨識度
3. **圖像化優勢**: 人類對圖像的識別速度快於文字,特定狀態應該加上 icon

### 建議加上 Icon 的狀態

- 已上架/已完成: ✓ (勾勾)
- 處理中: ⟳ (旋轉箭頭)
- 已取消: ✕ (叉叉)
- 警示狀態: ⚠ (警告符號)

### 實作建議

- 設計系統提供 `.status-tag-icon` class
- Icon 可選配,不影響核心遷移範圍
- Phase 11 遷移時保持現有行為,icon 優化可列入後續改進

## 面向 4: 頁面特定樣式保留

### shipment-products.php (SP-04)

**保留項目**:
- 商品展開/收起功能的按鈕樣式
- 內部展開內容的佈局和樣式
- Vue directives (@click, v-if, v-for 等)

**遷移範圍**:
- 主要操作按鈕 → `.btn` classes
- 狀態標籤 → `.status-tag` classes

### shipment-details.php (SD-04)

**保留項目**:
- Tab 切換按鈕的特殊樣式 (已在 Phase 10 優化過)
- 「查看」「出貨」按鈕的功能
- Vue directives

**遷移範圍**:
- 操作按鈕 → `.btn` classes
- 狀態標籤 → `.status-tag` classes

### orders.php (ORD-04)

**保留項目**:
- 父子訂單展開/收起功能
- 子訂單視覺層級 (藍色背景和邊框 - Phase 10 已處理)
- 狀態下拉選單的 Vue 功能
- 商品展開功能

**遷移範圍**:
- 狀態下拉選單的按鈕樣式 → `.btn` classes
- 操作按鈕 → `.btn` classes
- 狀態標籤 → `.status-tag` classes (如果有獨立的狀態標籤)

### products.php (PROD-03)

**保留項目**:
- View Mode 切換功能 (Table/Grid)
- Grid View 不遷移 (Phase 10 決策)
- checkbox 功能
- 狀態切換按鈕的 Vue 功能 (@click="toggleStatus")
- 採購數量 input 的內嵌樣式 (inline-edit-input)

**遷移範圍**:
- 分配按鈕 → 改用三個疊加的小 icon (特殊設計)
- 編輯按鈕 → `.btn` classes (使用文字)
- 刪除按鈕 → `.btn .btn-danger` (未來實作時)
- 狀態標籤 → `.status-tag` classes

**特殊設計**: 分配按鈕使用 icon 而非標準 `.btn` class,需要特殊處理

### settings.php (SET-02)

**保留項目**:
- Portal 按鈕 (頁面特定功能,非共用組件)
- 設定表單的內部結構
- Vue directives

**遷移範圍**:
- 儲存/取消按鈕 → `.btn` classes
- 其他操作按鈕 → `.btn` classes

## 後續優化項目 (不在 Phase 11 範圍)

以下項目記錄為後續優化,待 Phase 11 完成後處理:

### 1. 介面等比例縮放

**需求**: 電腦版在縮放側邊欄或視窗時,內容區域能等比例縮放

**影響範圍**: 全域響應式佈局

**建議時機**: Phase 13 (功能測試) 或專門的優化 phase

### 2. 頁面寬度對齊

**需求**: 所有頁面 (包含 Header) 的內容區域寬度完全一致

**問題**:
- 商品頁的搜尋框、表格都有對齊
- 訂單頁、出貨頁的底部分頁器沒有對齊
- 出貨頁面內容區域會變短

**影響範圍**: 全域響應式佈局和容器寬度

**建議時機**: Phase 13 (功能測試) 或專門的優化 phase

## Migration Strategy

### Wave 1: 設計系統樣式補充 (如需要)

檢查 `design-system/components/button.css` 和 `design-system/components/status-tag.css` 是否已包含所有需要的樣式:
- `.btn .btn-primary`
- `.btn .btn-secondary`
- `.btn .btn-danger`
- `.status-tag .status-success/.status-neutral/.status-info/.status-warning/.status-danger`

如果缺少,建立 Plan 11-01 補充樣式。

### Wave 2: 頁面遷移 (可平行執行)

每個頁面一個 plan:
- Plan 11-02: shipment-products.php 按鈕與狀態標籤遷移
- Plan 11-03: shipment-details.php 按鈕與狀態標籤遷移
- Plan 11-04: orders.php 按鈕與狀態標籤遷移
- Plan 11-05: products.php 按鈕與狀態標籤遷移 (含分配按鈕特殊設計)
- Plan 11-06: settings.php 按鈕遷移

### Wave 3: 驗證

建立 Plan 11-07 驗證所有頁面的按鈕和狀態標籤功能正常

## Success Criteria

Phase 11 完成的標準:

1. ✅ 所有頁面的主要操作按鈕使用 `.btn .btn-primary`
2. ✅ 所有頁面的次要操作按鈕使用 `.btn .btn-secondary`
3. ✅ 所有頁面的危險操作按鈕使用 `.btn .btn-danger`
4. ✅ 所有狀態標籤使用 `.status-tag` + 顏色修飾符 class
5. ✅ 商品頁面的分配按鈕使用三個疊加的小 icon 設計
6. ✅ 所有 Vue 功能正常 (不破壞現有功能)
7. ✅ 桌面版和手機版樣式都正確

## Notes

- Phase 11 聚焦於按鈕和狀態標籤的 class 遷移
- 不改變現有功能和互動行為
- 特殊設計 (如分配 icon) 需要額外處理
- 後續優化項目 (等比例縮放、寬度對齊) 不在此 phase 範圍

---
*Context created: 2026-01-28*
*Ready for planning*
