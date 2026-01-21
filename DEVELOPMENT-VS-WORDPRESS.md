# 開發環境 vs WordPress 環境 - 完整說明

**建立日期**: 2026-01-21

---

## 🎯 兩個環境的本質差異

### WordPress 環境（Local Sites）
**位置**: `/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one`

**本質**: 這是一個**完整的 WordPress 網站**
- ✅ 有資料庫（MySQL）
- ✅ 有 WordPress 核心
- ✅ 有 FluentCart 外掛
- ✅ 可以在瀏覽器看到介面
- ✅ 可以實際操作（新增訂單、商品等）
- ✅ 有完整的 PHP 執行環境

**用途**:
- 實際運行外掛
- 測試 UI 介面
- 手動測試功能
- 查看實際效果

---

### 開發環境（Development）
**位置**: `/Users/fishtv/Development/buygo-plus-one`

**本質**: 這只是一個**程式碼資料夾 + 測試工具**
- ❌ **沒有** WordPress
- ❌ **沒有** 資料庫
- ❌ **沒有** 瀏覽器介面
- ✅ 有 PHPUnit（單元測試工具）
- ✅ 有 Git（版本控制）
- ✅ 有 Composer（依賴管理）

**用途**:
- 編輯程式碼
- 執行**單元測試**（只測試商業邏輯，不需要 WordPress）
- Git 版本控制
- 程式碼組織和管理

---

## 🔗 符號連結的作用

因為我們已經設置了**符號連結**，所以：

```
開發環境                         WordPress 環境
↓ 修改程式碼                     ↓ 立即看到變更
/Development/buygo-plus-one  →  /Local Sites/.../buygo-plus-one
                                     ↓
                                 瀏覽器看到新功能
```

**意思是**:
1. 你在開發環境修改程式碼
2. WordPress 會**立即**使用這些程式碼
3. 重新整理瀏覽器就能看到效果

---

## 💡 簡單比喻

### WordPress 環境 = 餐廳廚房
- 有完整的設備（烤箱、爐子、冰箱）
- 可以真的煮出菜
- 客人可以看到成品
- 但很難做版本控制

### 開發環境 = 食譜筆記本
- 記錄所有食譜（程式碼）
- 可以快速測試某個步驟（單元測試）
- 有版本控制（Git）
- 但不能真的煮出菜來

### 符號連結 = 即時同步
- 你更新食譜
- 廚房立即使用新食譜
- 客人立即吃到新口味

---

## 🚀 實際開發流程（以你的任務為例）

### 任務：移除硬編碼 NT$ (Phase 3.4)

#### 步驟 1: 在開發環境修改程式碼
```bash
cd /Users/fishtv/Development/buygo-plus-one

# 用編輯器打開檔案
code includes/views/pages/shipment-details.php

# 或
vim includes/views/pages/shipment-details.php
```

找到第 408-416 行的硬編碼：
```php
// 原本（硬編碼）
echo 'NT$ ' . $price;

// 改成（動態）
echo window.buygoSettings.currencySymbol . ' ' . $price;
```

#### 步驟 2: 儲存檔案
因為有符號連結，WordPress 已經看到變更了！

#### 步驟 3: 在 WordPress 測試
```bash
# 1. 打開 Local by Flywheel
# 2. 啟動 buygo 站點
# 3. 瀏覽器打開: http://buygo.local/wp-admin
# 4. 進入「出貨管理」頁面
# 5. 檢查幣別顯示是否正確
```

#### 步驟 4: 提交變更
```bash
cd /Users/fishtv/Development/buygo-plus-one

git add includes/views/pages/shipment-details.php
git commit -m "fix: 移除 shipment-details.php 硬編碼 NT$，改用動態幣別

- 將硬編碼的 NT$ 改為讀取 window.buygoSettings.currencySymbol
- 支援多幣別顯示
- 解決 Phase 3.4 任務

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

---

## ❓ 開發環境能自動化嗎？

### 可以自動化的部分 ✅
1. **單元測試** - 自動測試商業邏輯
   ```bash
   composer test
   ```

2. **程式碼格式檢查** - 自動檢查語法
   ```bash
   composer phpcs  # 如果有設置
   ```

3. **Git 操作** - 版本控制
   ```bash
   git add . && git commit -m "message"
   ```

### **不能**自動化的部分 ❌
1. **UI 測試** - 需要人工在瀏覽器檢查
2. **功能測試** - 需要實際點擊按鈕、輸入資料
3. **視覺檢查** - 需要人眼確認排版、顏色等
4. **整合測試** - 需要完整的 WordPress 環境

**為什麼？**
因為開發環境沒有：
- WordPress
- 資料庫
- 瀏覽器介面
- FluentCart 外掛

---

## 📋 完成你的任務的正確流程

### Phase 3.4：移除硬編碼 NT$

#### 1. 在開發環境修改
```bash
cd /Users/fishtv/Development/buygo-plus-one
code .  # 打開 VS Code
```

修改這些檔案：
- `includes/views/pages/shipment-details.php` (行 408-416)
- 搜尋所有 `NT$` 硬編碼並替換

#### 2. 在 WordPress 測試
```bash
# Local by Flywheel → 啟動站點
# 瀏覽器: http://buygo.local/wp-admin
```

測試項目：
- [ ] 出貨管理頁面幣別顯示正確
- [ ] 商品頁面幣別顯示正確
- [ ] 訂單頁面幣別顯示正確
- [ ] Console 沒有錯誤

#### 3. 提交變更
```bash
git add .
git commit -m "fix: Phase 3.4 - 移除硬編碼 NT$"
git push  # 如果有遠端倉庫
```

---

## 🎯 開發環境的真正價值

雖然開發環境**不能取代** WordPress 環境，但它提供：

### 1. 版本控制 📚
```bash
# 隨時可以回到之前的版本
git log
git reset --hard abc123
```

### 2. 單元測試 🧪
```bash
# 快速測試商業邏輯（不需要啟動 WordPress）
composer test
```

### 3. 程式碼組織 📁
- 清晰的目錄結構
- 統一的編碼風格
- 完整的文檔

### 4. 協作開發 👥
- 多人可以同時開發
- 透過 Git 合併變更
- 程式碼審查

---

## 🔄 實際工作流程圖

```
┌─────────────────────────────────────────────────────────┐
│                     你的工作流程                          │
└─────────────────────────────────────────────────────────┘

1. 修改程式碼
   📝 /Users/fishtv/Development/buygo-plus-one/

   ↓ (符號連結即時同步)

2. WordPress 自動更新
   🔗 /Local Sites/.../buygo-plus-one/

   ↓

3. 瀏覽器測試
   🌐 http://buygo.local/wp-admin

   ↓

4. 確認無誤

   ↓

5. Git 提交
   💾 git commit -m "完成 Phase 3.4"
```

---

## ✅ 總結

| 問題 | 答案 |
|------|------|
| 開發環境可以看到介面嗎？ | ❌ 不行，需要在 WordPress 環境看 |
| 開發環境可以自動完成任務嗎？ | ❌ 不行，但可以執行單元測試 |
| 修改程式碼後需要複製嗎？ | ❌ 不用，符號連結自動同步 |
| 要在哪裡編輯程式碼？ | ✅ 開發環境 (Development) |
| 要在哪裡測試功能？ | ✅ WordPress 環境 (Local Sites) |
| 要在哪裡執行 git commit？ | ✅ 開發環境 (Development) |

---

## 🎯 下一步行動

### 立即開始你的 Phase 3.4 任務

```bash
# 1. 進入開發環境
cd /Users/fishtv/Development/buygo-plus-one

# 2. 打開編輯器
code .

# 3. 搜尋所有硬編碼的 NT$
# 在 VS Code 中: Cmd+Shift+F 搜尋 "NT$"

# 4. 逐一修改

# 5. 在 WordPress 測試
# Local by Flywheel → 啟動站點
# 瀏覽器測試各頁面

# 6. 提交變更
git add .
git commit -m "fix: Phase 3.4 完成"
```

---

**記住**:
- 💻 **開發環境** = 寫程式碼的地方
- 🌐 **WordPress 環境** = 看效果的地方
- 🔗 **符號連結** = 讓兩邊自動同步

**你只需要**:
1. 在開發環境寫程式碼
2. 在 WordPress 看效果
3. 重複 1-2 直到完成
4. Git 提交

就這麼簡單！🎉

---

**文件版本**: 1.0
**最後更新**: 2026-01-21
