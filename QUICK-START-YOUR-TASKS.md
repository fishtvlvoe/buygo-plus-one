# 快速開始完成你的任務

**針對**: Phase 3.4-3.9 的 UI 優化任務

---

## 🚀 5 分鐘快速流程

### 1. 打開兩個視窗

**視窗 A - 編輯器（開發環境）**
```bash
cd /Users/fishtv/Development/buygo-plus-one
code .  # 或用你喜歡的編輯器
```

**視窗 B - 瀏覽器（WordPress）**
```
1. 打開 Local by Flywheel
2. 啟動 buygo 站點
3. 瀏覽器: http://buygo.local/wp-admin
```

### 2. 開始第一個任務（Phase 3.4）

#### 任務：移除硬編碼 NT$

**在編輯器（視窗 A）**:
```bash
# 搜尋所有 NT$ (Cmd+Shift+F 或 Ctrl+Shift+F)
# 找到這些檔案：
- includes/views/pages/shipment-details.php (行 408-416)
- includes/services/class-product-service.php (如果有)
```

**修改**:
```php
// 之前
echo 'NT$ ' . $price;

// 之後
echo '<span class="currency-symbol"></span> ' . $price;
```

**在瀏覽器（視窗 B）**:
```
1. 重新整理頁面 (F5 或 Cmd+R)
2. 檢查幣別顯示
3. 打開 Console (F12) 檢查錯誤
```

### 3. 確認無誤後提交

**在終端機**:
```bash
cd /Users/fishtv/Development/buygo-plus-one

git add .
git commit -m "fix: Phase 3.4 - 移除硬編碼 NT$，改用動態幣別

- 修改 shipment-details.php 移除硬編碼
- 改為讀取 window.buygoSettings.currencySymbol
- 測試通過：出貨管理頁面幣別顯示正確

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

---

## 📋 完整任務清單

### Phase 3.4：移除硬編碼 NT$ ⏳

**檔案**:
- `includes/views/pages/shipment-details.php` (行 408-416)

**搜尋**: `NT$` 或 `'NT$ '`

**替換為**: 動態讀取 `window.buygoSettings.currencySymbol`

**測試**:
- [ ] 出貨管理頁面
- [ ] Console 沒有錯誤

**時間**: 30 分鐘

---

### Phase 3.7：訂單頁面商品顯示優化 ⏳

**檔案**:
- `includes/views/pages/orders.php`

**問題**: 商品數量欄位顯示不完整

**修改**: 確保顯示「商品名稱 x 數量」格式

**測試**:
- [ ] 訂單列表頁面
- [ ] 訂單詳情頁面

**時間**: 30-60 分鐘

---

### Phase 3.8：出貨與備貨頁面完整性檢查 ⏳

**檔案**:
- `includes/views/pages/shipment-details.php`
- `includes/views/pages/shipment-products.php`

**檢查項目**:
- [ ] UI 一致性
- [ ] 幣別切換功能
- [ ] Console 警告
- [ ] 功能完整性

**時間**: 1-2 小時

---

### Phase 3.9：全站驗收與文檔 ⏳

**驗收清單**:
- [ ] 所有頁面正常運作
- [ ] 無 Console 錯誤
- [ ] 無 404 請求
- [ ] 幣別顯示統一
- [ ] padding/margin 協調
- [ ] 響應式設計完整

**時間**: 1-2 小時

---

## 🔍 如何找到要修改的檔案

### 方法 1: VS Code 全域搜尋
```
1. 打開 VS Code
2. Cmd+Shift+F (Mac) 或 Ctrl+Shift+F (Windows)
3. 搜尋 "NT$" 或 "hardcoded"
4. 查看所有結果
```

### 方法 2: 用 Grep 搜尋
```bash
cd /Users/fishtv/Development/buygo-plus-one
grep -r "NT\$" includes/
```

### 方法 3: 查看你的任務文件
你的任務文件已經寫明檔案位置：
- Phase 3.4: `shipment-details.php` 行 408-416

---

## 💡 實用技巧

### 1. 保持兩個視窗開啟
```
左邊：編輯器（修改程式碼）
右邊：瀏覽器（看效果）
```

### 2. 每完成一個任務就 commit
```bash
git add .
git commit -m "完成 Phase 3.4"
```

### 3. 隨時檢查 Console
```
F12 → Console
確保沒有紅色錯誤
```

### 4. 用 Git 查看變更
```bash
git diff  # 查看修改了什麼
git status  # 查看哪些檔案被修改
```

---

## ⚠️ 常見問題

### Q: 修改程式碼後看不到變更？
A:
1. 檢查符號連結是否正常
2. 清除瀏覽器快取 (Cmd+Shift+R)
3. 檢查 PHP 錯誤 (在 WordPress debug.log)

### Q: Console 有錯誤怎麼辦？
A:
1. 讀取錯誤訊息
2. 檢查 JavaScript 語法
3. 確認變數名稱正確

### Q: 不知道要改哪些檔案？
A:
1. 參考你的任務文件（已寫明位置）
2. 用 VS Code 搜尋關鍵字
3. 問 AI 助手

---

## 🎯 立即開始

```bash
# 1. 進入開發環境
cd /Users/fishtv/Development/buygo-plus-one

# 2. 打開編輯器
code .

# 3. 打開 Local by Flywheel 和瀏覽器

# 4. 開始第一個任務（Phase 3.4）
```

加油！💪

---

**提示**: 參考 [DEVELOPMENT-VS-WORDPRESS.md](DEVELOPMENT-VS-WORDPRESS.md) 了解更多細節
