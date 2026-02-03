# 出貨通知系統 Bug 修復任務

## 📅 任務日期
2026-02-03

## 🎯 任務概述
修復出貨頁面與 Excel 報表的多個問題，並更新出貨通知的 LINE 訊息模板。

---

## 🔍 Bug 清單

### Bug 1: 出貨頁面 - 客戶身分證字號缺失

**現況**：
- Excel 報表有顯示身分證字號
- 出貨頁面沒有顯示身分證字號

**需求**：
- 在出貨頁面的「客戶資訊」區塊顯示身分證字號
- 格式維持原樣，不需要變色或特殊樣式

**資料來源**：
- 客戶詳情頁有此資料（`wp_usermeta.buygo_id_number`）
- 參考截圖：客戶詳情頁黃色標示區域

**相關檔案**：
- `/admin/partials/shipment-details.php` - 出貨頁面前端
- 可能需要修改的 API：`/includes/api/class-shipments-api.php`

---

### Bug 2: 出貨頁面 - 欄位順序與內容調整

**現況**：
```
└── 到貨時間：年/月/日 [日期選擇器]
```

**需求**：
```
出貨設定區塊
├── 📦 出貨時間：2026-02-03 18:18:35
│   └── 系統自動填入（確認出貨時的當下時間）
│   └── 不可編輯，唯讀顯示
│
├── 📅 到貨時間：年/月/日 [日期選擇器]
│   └── 買家預計收貨日期
│   └── 賣家手動選擇
│
└── 🚚 物流方式：[下拉選單]
    ├── 易利
    ├── 千森
    ├── OMI
    ├── 多賀
    ├── 賀來
    ├── 神奈川
    ├── 新日本
    └── EMS
```

**格式要求**：
- 物流方式下拉選單樣式要與訂單頁面的「運送狀態」下拉選單一致
- 參考頁面：`https://test.buygo.me/buygo-portal/orders/`
- 參考截圖：圖 6-7（運送狀態下拉選單）

**資料庫變更**：
- 表名：`wp_buygo_shipments`
- 新增欄位：
  - `shipping_method` VARCHAR(50) - 物流方式
  - `estimated_delivery` DATE - 預計到貨日期
- 確認現有欄位：
  - `shipped_at` - 出貨時間（應該已存在）

**相關檔案**：
- `/admin/partials/shipment-details.php` - 前端表單
- `/includes/api/class-shipments-api.php` - API 處理
- `/includes/services/class-shipment-service.php` - 商業邏輯
- 可能需要資料庫遷移腳本

---

### Bug 3: Excel 報表 - 欄位缺失與順序錯誤

**現況問題**：
1. LINE 名稱欄位存在但沒有內容（顯示空白）
2. 缺少「客戶身分證字號」欄位
3. 缺少「到貨日期」欄位
4. 缺少「物流方式」欄位
5. 欄位順序不符需求

**需求的 Excel 欄位順序**：
```
1.  出貨單號（例：SH-20260203-001）
2.  客戶姓名
3.  客戶電話
4.  客戶地址
5.  客戶 Email
6.  客戶身分證字號 ← 新增
7.  LINE 名稱 ← 修正（目前空白）
8.  商品名稱
9.  數量
10. 單價
11. 小計
12. 出貨日期 ← 調整順序
13. 到貨日期 ← 新增
14. 物流方式 ← 新增
15. 物流追蹤號碼
16. 狀態
17. 備註
```

**資料來源**：
- **LINE 名稱**：`wp_usermeta` 表的 `buygo_line_display_name`
  - 查詢方式：`get_user_meta($wp_user_id, 'buygo_line_display_name', true)`
- **身分證字號**：`wp_usermeta` 表的 `buygo_id_number`
- **到貨日期**：`wp_buygo_shipments.estimated_delivery`（新增欄位）
- **物流方式**：`wp_buygo_shipments.shipping_method`（新增欄位）

**相關檔案**：
- `/includes/services/class-export-service.php` - Excel 匯出邏輯
  - 目前 LINE 名稱取得邏輯在第 101-105 行
  - 需要檢查為何沒有內容

---

### Bug 4: 出貨通知訊息模板

**現況**：
- 出貨通知訊息缺少關鍵資訊

**需求的訊息格式**：
```
您的訂單已出貨囉！

商品清單：
{product_list}

物流方式：{shipping_method}
預計送達：{estimated_delivery}

感謝您的購買！如有問題請聯繫客服。
```

**佔位符說明**：
- `{product_list}` - 出貨單中的商品名稱列表（可能有多個商品）
- `{shipping_method}` - 賣家選擇的物流方式
- `{estimated_delivery}` - 賣家選擇的到貨日期

**資料來源**：
- 出貨單的商品：`wp_buygo_shipment_items` 表
- 物流方式：`wp_buygo_shipments.shipping_method`
- 到貨日期：`wp_buygo_shipments.estimated_delivery`

**時效要求**：
- 確認出貨後，買家應在 **1 分鐘內**收到 LINE 通知
- 目前延遲約 1.5 分鐘（第二次重試），屬於可接受範圍

**相關檔案**：
- `/includes/services/class-notification-templates.php` - 模板定義
  - 需要更新 `order_shipped` 模板
  - 模板是程式碼定義，不是資料庫表
- 可能需要修改通知發送邏輯以傳遞新參數

---

## 🔧 技術細節

### 資料庫表結構

**wp_buygo_shipments** (需要新增欄位)：
```sql
ALTER TABLE wp_buygo_shipments
ADD COLUMN shipping_method VARCHAR(50) DEFAULT NULL COMMENT '物流方式',
ADD COLUMN estimated_delivery DATE DEFAULT NULL COMMENT '預計到貨日期';
```

### 關鍵查詢邏輯

**取得 LINE 名稱**（目前有問題）：
```php
// 位置：class-export-service.php line 101-105
$line_display_name = '';
if (!empty($shipment['wp_user_id'])) {
    $line_display_name = get_user_meta($shipment['wp_user_id'], 'buygo_line_display_name', true);
}
```

**問題排查方向**：
1. 檢查 `$shipment['wp_user_id']` 是否有值
2. 檢查 `wp_usermeta` 表是否有 `buygo_line_display_name` 記錄
3. 可能需要從其他來源取得 LINE 名稱（例如：`wp_buygo_line_users` 表）

---

## 📝 執行順序

### Commit 0: 前置作業
- 檢查目前 git 狀態
- 如有未提交變更，先提交

### Commit 1: 出貨頁面新增身分證字號
**檔案**：
- `admin/partials/shipment-details.php`
- 可能：`includes/api/class-shipments-api.php`

**測試**：
- 開啟出貨頁面，確認身分證字號顯示

### Commit 2: 出貨頁面欄位調整
**檔案**：
- 資料庫遷移腳本（新增欄位）
- `admin/partials/shipment-details.php`
- `includes/api/class-shipments-api.php`
- `includes/services/class-shipment-service.php`

**測試**：
1. 確認資料庫欄位已新增
2. 確認出貨時間自動填入
3. 確認到貨時間可選擇
4. 確認物流方式下拉選單樣式正確
5. 確認資料可以儲存

### Commit 3: Excel 報表修正
**檔案**：
- `includes/services/class-export-service.php`

**測試**：
1. 匯出 Excel 報表
2. 確認 LINE 名稱有內容
3. 確認欄位順序正確
4. 確認新增欄位（身分證字號、到貨日期、物流方式）都有資料

### Commit 4: 出貨通知訊息模板
**檔案**：
- `includes/services/class-notification-templates.php`
- 可能：通知發送的相關檔案（需要傳遞新參數）

**測試**：
1. 建立測試訂單
2. 確認出貨
3. 確認買家在 1 分鐘內收到 LINE 通知
4. 確認訊息包含：商品清單、物流方式、預計送達時間

---

## 🔗 相關連結

- 出貨頁面：`https://test.buygo.me/buygo-portal/shipment-details/?view=shipment-mark&id=72`
- 訂單頁面（物流下拉參考）：`https://test.buygo.me/buygo-portal/orders/`
- 客戶詳情頁（身分證字號參考）：`https://test.buygo.me/buygo-portal/customers/?view=detail&id=1`

---

## 📸 重要截圖參考

1. **圖一**：出貨頁面設計稿（展示欄位順序）
2. **圖二**：出貨頁面當前狀態
3. **圖三**：客戶詳情頁（黃色標示：身分證字號）
4. **圖四**：Excel 報表範例（Numbers 表格）
5. **圖五**：LINE 訊息範例
6. **圖六**：訂單頁面（運送狀態下拉選單）
7. **圖七**：運送狀態下拉選單展開

---

## ⚠️ 注意事項

1. **資料庫遷移**：
   - 確保使用安全的 ALTER TABLE 語法
   - 考慮已有資料的相容性

2. **LINE 名稱問題**：
   - 目前取值為空白，需要深入調查原因
   - 可能需要從多個來源嘗試取得（usermeta、buygo_line_users 表等）

3. **下拉選單樣式**：
   - 確保與訂單頁面的運送狀態下拉一致
   - 可能需要複用相同的 CSS class 或 Vue 元件

4. **通知時效**：
   - 目前 1.5 分鐘延遲是第二次重試
   - 如果需要更快，可以調整重試時間（`RETRY_SCHEDULE`）
   - 但 1 分鐘內應該是可以達成的

---

## 🚀 開始執行

在新對話中，請按照以下步驟執行：

1. **讀取此文件**：`/Users/fishtv/Development/buygo-plus-one-dev/SHIPMENT-NOTIFICATION-BUGS.md`

2. **檢查 git 狀態**：
   ```bash
   cd /Users/fishtv/Development/buygo-plus-one-dev
   git status
   ```

3. **如有未提交變更，先提交**

4. **按照 Commit 1-4 的順序執行**

5. **每個 Bug 修復後都要：**
   - 測試功能
   - Git commit
   - 向使用者確認

---

## 📋 Commit 訊息範本

```
Commit 1:
feat(shipment): 出貨頁面新增客戶身分證字號顯示

Commit 2:
feat(shipment): 新增物流方式與到貨日期欄位
- 資料庫新增 shipping_method, estimated_delivery 欄位
- 出貨頁面調整欄位順序（出貨時間、到貨時間、物流方式）
- 物流方式下拉選單樣式統一

Commit 3:
fix(export): 修正 Excel 報表欄位與順序
- 修正 LINE 名稱取值邏輯
- 新增身分證字號、到貨日期、物流方式欄位
- 調整欄位順序

Commit 4:
feat(notification): 更新出貨通知訊息模板
- 新增商品清單、物流方式、預計送達時間
```

---

## ✅ 最後確認

- [x] 已完成前一個任務（訂單通知連結修復）並提交
- [x] 交接文件已建立
- [ ] 準備好在新對話中開始執行

**建議**：在新對話的第一句話中說：
```
請讀取 /Users/fishtv/Development/buygo-plus-one-dev/SHIPMENT-NOTIFICATION-BUGS.md
並開始執行出貨通知系統的 Bug 修復任務
```
