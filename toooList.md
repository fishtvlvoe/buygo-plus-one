# BuyGo Plus One 代碼優化 To-Do List

## 優先級說明

- 🔴 高優先級（安全性相關）
- 🟡 中優先級（代碼品質）
- 🟢 低優先級（可選優化）

---

## Phase 1：安全性優化（🔴 高優先級）

### 1.1 修正綁定碼生成器

檔案位置：`includes/services/class-line-service.php`

- [x] 找到 Line 464 的 `mt_rand(0, 999999)`
- [x] 改用 `random_int(0, 999999)` 替代
- [x] 測試綁定碼生成功能是否正常

理由：

- `mt_rand` 不是密碼學安全的隨機數生成器
- 綁定碼用於使用者身份驗證，應使用 `random_int`

預估時間：5 分鐘

---

### 1.2 Webhook 簽章驗證優化（可選）

檔案位置：`includes/api/class-line-webhook-api.php`

- [x] 檢查 Line 130 的簽章驗證邏輯
- [x] 決定是否調整「channel_secret 缺失時」的處理方式

目前行為：

```php
if (empty($channel_secret)) {
    // 跳過驗證（開發模式）
    return true;
}
```

建議選項（擇一執行）：

- 選項 A：維持現狀（適合開發階段）
- 選項 B：改為拒絕請求（生產環境更安全）

```php
if (empty($channel_secret)) {
    $logger->log('signature_verification_failed', [
        'reason' => 'Channel secret not configured'
    ]);
    return false; // Fail Closed
}
```

決策點：

- 如果外掛只在你自己的環境使用 → 選項 A（維持現狀）
- 如果外掛會發布給其他人使用 → 選項 B（改為 Fail Closed）

預估時間：10 分鐘

---

## Phase 2：WordPress 標準合規（🟡 中優先級）

### 2.1 清理備份檔案

- [x] 刪除 `includes/services/class-allocation-service.php.backup`
- [x] 刪除 `includes/services/class-shipment-service.php.backup`
- [x] 確認 Git 狀態（確保備份檔案不在版本控制中）
- [x] 提交 commit：「chore: 清理備份檔案」

預估時間：2 分鐘

---

### 2.2 加入國際化支援（i18n）

這是 WordPress.org 上架的必要條件，但工作量較大，可以分階段執行。

#### 2.2.1 準備階段

- [ ] 確認 `buygo-plus-one.php` 中有載入 text domain

  ```php
  load_plugin_textdomain('buygo-plus-one-dev', false, dirname(plugin_basename(__FILE__)) . '/languages');
  ```

- [ ] 建立 `/languages/` 目錄（如果不存在）

#### 2.2.2 執行階段（選擇一種方式）

方式 A：手動逐步替換（適合學習）

- [ ] 從 `class-settings-page.php` 開始
- [ ] 將硬編碼中文字串改為 `__('文字', 'buygo-plus-one-dev')`
- [ ] 範例：

  ```php
  // 修改前
  <h1>設定</h1>

  // 修改後
  <h1><?php _e('設定', 'buygo-plus-one-dev'); ?></h1>
  ```

方式 B：使用工具自動產生（推薦）

- [ ] 安裝 WP-CLI（如果沒有）
- [ ] 執行指令產生 .pot 檔案：
  ```bash
  wp i18n make-pot /path/to/buygo-plus-one-dev languages/buygo-plus-one-dev.pot
  ```
- [ ] 使用 Poedit 或其他工具手動標記需要翻譯的字串

決策點：

- 如果不打算上架 WordPress.org → 可以跳過
- 如果要上架 → 必須完成

預估時間：

- 方式 A：3-5 小時（全部檔案）
- 方式 B：1-2 小時（使用工具）

---

## Phase 3：代碼品質優化（🟢 低優先級）

### 3.1 移除 glob 載入方式（可選）

檔案位置：`includes/class-plugin.php`

目前使用 `glob` 動態載入 Services 和 API：

```php
foreach (glob(BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-*.php') as $service) {
    require_once $service;
}
```

優化選項：

- 選項 A：維持現狀（方便新增檔案）
- 選項 B：改用 Composer Autoloader（效能更好）
- 選項 C：改用明確的 require_once 清單（最清楚）

建議：

- 如果專案穩定、不常新增檔案 → 選項 C
- 如果追求效能 → 選項 B
- 如果方便性優先 → 選項 A（維持現狀）

預估時間：30 分鐘（如果選擇優化）

---

### 3.2 提取資料庫版本號為常數

檔案位置：`includes/class-plugin.php` Line 191

- [ ] 將 `$required_db_version = '1.2.0';` 改為類別常數
  ```php
  class Plugin {
      const DB_VERSION = '1.2.0';

      // ...

      private function maybe_upgrade_database(): void {
          $current_db_version = get_option('buygo_plus_one_db_version', '0');
          $required_db_version = self::DB_VERSION;
          // ...
      }
  }
  ```

預估時間：3 分鐘

---

## 執行建議順序

### 明天的工作計畫（建議順序）

1. Phase 1.1：修正綁定碼生成器（5 分鐘）✅ 必做 - 完成
2. Phase 2.1：清理備份檔案（2 分鐘）✅ 必做 - 完成
3. Phase 3.2：提取資料庫版本號為常數（3 分鐘）✅ 建議做
4. Phase 1.2：決定 Webhook 簽章驗證策略（10 分鐘）✅ 完成（選擇 A）
5. Phase 2.2：國際化支援（1-5 小時）❓ 看是否上架 WordPress.org
6. Phase 3.1：移除 glob 載入方式（30 分鐘）❓ 可選

總預估時間（必做項目）：10 分鐘

---

## 測試檢查清單

每個優化完成後，請執行以下測試：

### 安全性測試

- [ ] LINE 綁定功能測試（產生綁定碼 → 綁定 → 確認成功）
- [ ] Webhook 接收測試（發送測試 Webhook → 確認簽章驗證正常）

### 功能測試

- [ ] 後台設定頁面正常載入
- [ ] 模板設定功能正常
- [ ] 角色權限設定功能正常

### Git 提交

- [ ] 所有修改提交到 Git
- [ ] Commit message 清楚描述修改內容

---

## 完成標準

Phase 1 完成標準：

- ✅ 所有安全性問題已修復
- ✅ 功能測試通過
- ✅ 已提交 Git commit

Phase 2 完成標準：

- ✅ 備份檔案已清理
- ✅ （可選）國際化支援已加入

Phase 3 完成標準：

- ✅ （可選）代碼品質優化完成

---

## 注意事項

1. 每次修改前先備份（Git commit）
2. 一次只改一個檔案，測試後再改下一個
3. 如果遇到問題，可以隨時詢問我
4. 保持 Git 歷史乾淨（一個功能一個 commit）

---

生成時間：2026-01-24 04:50
Code Review 依據：WordPress Plugin 審查標準
