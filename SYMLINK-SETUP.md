# ✅ 符號連結設置完成

**完成日期**: 2026-01-21
**狀態**: ✅ 已啟用

---

## 🔗 什麼是符號連結？

符號連結（Symbolic Link）就像是一個「捷徑」或「分身」。

WordPress 中的外掛目錄現在**直接指向**開發目錄：

```
WordPress 外掛目錄 (捷徑)
↓
指向
↓
開發目錄 (實際檔案)
```

## 📁 設置詳情

### 實際檔案位置（開發環境）
```
/Users/fishtv/Development/buygo-plus-one/
```
這裡是你修改程式碼的地方 ✍️

### WordPress 看到的位置
```
/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one/
→ 這是符號連結，指向上面的開發目錄
```

### 備份位置
```
/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one.backup-20260121-213951/
```
原始 WordPress 外掛已備份 ✅

---

## ✨ 好處

### 1. 即時同步 ⚡
修改開發環境的檔案 → WordPress 立即看到變更

**範例**:
```bash
# 在開發環境修改程式碼
vim /Users/fishtv/Development/buygo-plus-one/includes/services/class-product-service.php

# 重新整理瀏覽器 → 立即看到變更！
```

### 2. 單一來源 📝
只有一份檔案，不會有「改了這邊忘了那邊」的問題

### 3. Git 版本控制 💾
所有修改都在開發目錄，方便 Git 追蹤

### 4. 測試方便 🧪
```bash
# 在開發環境執行測試
cd /Users/fishtv/Development/buygo-plus-one
composer test

# 在 WordPress 看結果
# 打開瀏覽器: http://buygo.local/wp-admin
```

---

## 🚀 開發流程

### 典型的開發步驟

#### 1. 修改程式碼
```bash
cd /Users/fishtv/Development/buygo-plus-one

# 用你喜歡的編輯器
code .  # VS Code
# 或
vim includes/services/class-product-service.php
```

#### 2. 執行測試
```bash
composer test
```

#### 3. 在 WordPress 查看效果
```bash
# 打開 Local by Flywheel
# 啟動 buygo 站點
# 瀏覽器: http://buygo.local/wp-admin
```

#### 4. 提交變更
```bash
git add .
git commit -m "更新產品服務邏輯"
```

---

## 📂 目錄結構

```
/Users/fishtv/Development/buygo-plus-one/    ← 實際檔案（開發環境）
├── includes/
│   ├── services/
│   │   ├── class-product-service.php       ← 修改這裡
│   │   └── ...
│   └── ...
├── tests/
├── composer.json
└── ...

/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one/
↑
這是符號連結，指向上面的開發目錄
WordPress 從這裡讀取外掛
```

---

## 🎯 實際使用範例

### 範例 1: 修改商品服務

```bash
# 1. 開啟開發環境
cd /Users/fishtv/Development/buygo-plus-one

# 2. 編輯 ProductService
vim includes/services/class-product-service.php

# 3. 執行測試
composer test

# 4. 打開瀏覽器查看 WordPress 後台
# http://buygo.local/wp-admin
# → 立即看到變更！

# 5. 確認無誤後提交
git add includes/services/class-product-service.php
git commit -m "修改產品計價邏輯"
```

### 範例 2: 新增測試

```bash
# 1. 建立新測試檔案
vim tests/Unit/Services/OrderServiceTest.php

# 2. 執行測試
composer test

# 3. 測試通過後提交
git add tests/Unit/Services/OrderServiceTest.php
git commit -m "新增訂單服務測試"
```

---

## ⚠️ 重要提醒

### 1. 只修改開發環境
✅ **正確**: 修改 `/Users/fishtv/Development/buygo-plus-one/`
❌ **錯誤**: 直接修改 WordPress 外掛目錄（因為它只是連結）

### 2. 備份已完成
你的原始外掛已備份到:
```
/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one.backup-20260121-213951/
```

### 3. 如果需要還原

```bash
# 移除符號連結
rm "/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one"

# 還原備份
mv "/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one.backup-20260121-213951" \
   "/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one"
```

### 4. Git 忽略的檔案

開發環境的 `.gitignore` 會忽略:
- `vendor/` (Composer 依賴)
- `node_modules/`
- `coverage/` (測試覆蓋率報告)
- `.phpunit.result.cache`

這些檔案不會進入版本控制 ✅

---

## 🔍 驗證設置

### 檢查符號連結
```bash
ls -la "/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/" | grep buygo-plus-one
```

**預期輸出**:
```
lrwxr-xr-x   1 fishtv  staff     40 Jan 21 21:40 buygo-plus-one -> /Users/fishtv/Development/buygo-plus-one
```

`lrwxr-xr-x` 開頭表示這是符號連結 ✅

### 測試同步
```bash
# 1. 在開發環境建立測試檔案
echo "test" > /Users/fishtv/Development/buygo-plus-one/test.txt

# 2. 在 WordPress 檢查是否存在
ls "/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one/test.txt"

# 3. 清理
rm /Users/fishtv/Development/buygo-plus-one/test.txt
```

---

## 📚 相關文件

- [遷移完成報告](MIGRATION-COMPLETE.md) - 外掛遷移詳情
- [框架說明](README.md) - 整體架構
- [快速開始](.plugin-testing/docs/00-開始使用.md) - 5 分鐘入門

---

## 🎉 現在開始開發

```bash
# 1. 進入開發目錄
cd /Users/fishtv/Development/buygo-plus-one

# 2. 執行測試
composer test

# 3. 開始編輯
code .  # 或使用你喜歡的編輯器

# 4. 在瀏覽器查看 WordPress
# http://buygo.local/wp-admin
```

**一次修改，兩邊同步！** 🚀

---

**設置版本**: 1.0
**設置日期**: 2026-01-21
**狀態**: 完全運作中 ✅
