# BuyGo+1 待完成任務清單

> 更新日期：2026-01-22

## 已完成任務

- [x] 修復一鍵分配 Bug - allocated_quantity 應存在 line_meta 中
- [x] 重新設計下單名單 UI - 顯示每筆獨立訂單而非整合
- [x] Phase B：Grid View 模式（商品列表大圖展示）
- [x] 為手機版加入 Grid View 切換功能（與電腦版一致）
- [x] 優化庫存分配頁面 - 移除下單時間、改橫向排列、修復輸入框問題
- [x] 修復已出貨訂單仍可分配的邏輯 Bug
- [x] 功能：訂單頁「批次轉備貨」- 勾選多筆訂單後一次性轉為備貨中狀態
- [x] 修復：父訂單有子訂單時隱藏轉備貨按鈕
- [x] 修復：批次轉備貨應處理子訂單而非父訂單
- [x] 修復：子訂單狀態變更時同步更新父訂單 shipping_status
- [x] 修復 Bug：出貨頁面應使用子分頁，不應出現彈跳視窗
- [x] 修復：批次轉備貨應檢查訂單是否已分配庫存（無分配不可轉備貨）

---

## 待完成任務

### 1. Phase C：多樣式產品 UI + 狀態追蹤邏輯
**優先級：高**

**功能描述：**
支援多樣式（多規格）產品的 UI 顯示與狀態追蹤。例如：同一商品有不同顏色、尺寸等變體。

**需要實作：**
1. 商品列表顯示多樣式產品的方式
2. 每個樣式的獨立庫存追蹤
3. 分配時可以選擇特定樣式
4. 訂單中顯示購買的具體樣式

**相關檔案：**
- `/includes/views/pages/products.php`
- `/includes/services/class-product-service.php`
- `/includes/services/class-allocation-service.php`

---

## 技術筆記

### 訂單狀態流程
```
未出貨 (unshipped) → 備貨中 (preparing) → 待出貨 (processing) → 已出貨 (shipped) → 交易完成 (completed)
                                                                      ↓
                                                                   斷貨 (out_of_stock)
```

### 父子訂單邏輯
1. 父訂單有子訂單時，「轉備貨」按鈕不顯示在父訂單上
2. 批次轉備貨會自動處理子訂單而非父訂單
3. 子訂單狀態變更會自動同步更新父訂單的 shipping_status

### 關鍵服務類別
- `AllocationService` - 庫存分配邏輯
- `OrderService` - 訂單管理
- `ShipmentService` - 出貨單管理
- `ShippingStatusService` - 運送狀態管理
- `ProductService` - 商品管理
