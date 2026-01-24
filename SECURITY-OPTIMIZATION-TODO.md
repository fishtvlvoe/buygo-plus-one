# BuyGo Plus One - 安全性優化 To-Do List

**建立日期：** 2026-01-24
**目的：** 修正安全性問題並優化代碼品質
**預估總時間：** 20-25 分鐘
**風險等級：** 🟢 低風險（不會破壞現有功能）

---

## 📋 執行前檢查清單

### 環境確認

- [ ] 確認目前在開發環境 (`buygo-plus-one-dev`)
- [ ] 確認 `WP_DEBUG = true`（開發模式）
- [ ] 確認 WordPress 網站可正常訪問
- [ ] 確認有最近的資料庫備份（建議先備份）

### Git 狀態確認

- [ ] 當前分支：`main`
- [ ] 領先 origin/main：28 commits
- [ ] 有未提交的變更：`buygo-plus-one.php`, `scripts/*.sh`

---

## 🔴 Phase 1：Git 準備工作（5 分鐘）

### Task 1.1：處理現有的未提交變更

**檔案變更：**
- `buygo-plus-one.php`（版本號 0.0.1-dev → 0.03）
- `scripts/create-feature.sh`（腳本改進）
- `scripts/validate-structure.sh`（腳本改進）

**執行步驟：**

- [ ] 檢查變更內容
  ```bash
  cd /Users/fishtv/Development/buygo-plus-one-dev
  git diff buygo-plus-one.php
  git diff scripts/create-feature.sh
  git diff scripts/validate-structure.sh
  ```

- [ ] 決定處理方式（二選一）：
  - [ ] 選項 A：提交這些變更（推薦）
    ```bash
    git add buygo-plus-one.php scripts/create-feature.sh scripts/validate-structure.sh
    git commit -m "chore: 更新版本號為 0.03 並改進腳本"
    ```
  - [ ] 選項 B：暫存起來
    ```bash
    git stash push -m "暫存版本號和腳本變更"
    ```

---

### Task 1.2：處理未追蹤的檔案

**未追蹤的檔案：**
- `BUILD-REPORT.md`（自動生成的測試報告）
- `docs/development/AUTOMATION-TEST-REPORT.md`（自動生成的測試報告）
- `scripts/build-production.sh`（部署腳本）

**執行步驟：**

- [ ] 決定處理方式（二選一）：
  - [ ] 選項 A：加入 `.gitignore`（推薦）
    ```bash
    echo "" >> .gitignore
    echo "# 測試報告" >> .gitignore
    echo "BUILD-REPORT.md" >> .gitignore
    echo "AUTOMATION-TEST-REPORT.md" >> .gitignore
    echo "*-TEST-REPORT.md" >> .gitignore
    ```
    然後提交 `build-production.sh`：
    ```bash
    git add scripts/build-production.sh .gitignore
    git commit -m "chore: 加入部署腳本並更新 .gitignore"
    ```

  - [ ] 選項 B：全部提交
    ```bash
    git add BUILD-REPORT.md docs/development/AUTOMATION-TEST-REPORT.md scripts/build-production.sh
    git commit -m "docs: 加入測試報告和部署腳本"
    ```

---

### Task 1.3：建立功能分支

- [ ] 建立新分支 `feature/security-optimization`
  ```bash
  git checkout -b feature/security-optimization
  ```

- [ ] 確認分支建立成功
  ```bash
  git branch
  # 應該看到 * feature/security-optimization
  ```

**✅ Phase 1 完成檢查點：**
- [ ] Git 狀態乾淨（無未提交變更）
- [ ] 已在 `feature/security-optimization` 分支

---

## 🔴 Phase 2：安全性修正（10 分鐘）

### Task 2.1：修正綁定碼生成器（mt_rand → random_int）

**檔案位置：** `includes/services/class-line-service.php:464`

**風險評估：** 🟢 安全（只改一行）

**執行步驟：**

- [ ] 讀取目前的檔案內容
  ```bash
  grep -n "mt_rand" includes/services/class-line-service.php
  # 應該看到 Line 464
  ```

- [ ] 執行修改
  **修改前：**
  ```php
  $code = str_pad( mt_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
  ```

  **修改後：**
  ```php
  $code = str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
  ```

- [ ] 確認修改成功
  ```bash
  grep -n "random_int" includes/services/class-line-service.php
  # 應該看到 Line 464
  ```

- [ ] 提交變更
  ```bash
  git add includes/services/class-line-service.php
  git commit -m "security: 修正綁定碼生成器使用密碼學安全的隨機數

- 將 mt_rand() 改為 random_int()
- 防止綁定碼被預測的安全風險

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
  ```

---

### Task 2.2：優化 Webhook 簽章驗證邏輯

**檔案位置：** `includes/api/class-line-webhook-api.php`

**風險評估：** 🟡 中等（邏輯變更，需測試）

**執行步驟：**

- [ ] 備份原始檔案（預防措施）
  ```bash
  cp includes/api/class-line-webhook-api.php includes/api/class-line-webhook-api.php.before-optimization
  ```

- [ ] 修改 `verify_signature()` 方法（Line 129-135）

  **修改前：**
  ```php
  // 如果沒有設定 channel secret，跳過驗證（開發模式）
  if ( empty( $channel_secret ) ) {
      $logger->log( 'signature_verification_skipped', array(
          'reason' => 'Channel secret not configured, skipping verification (development mode)',
      ) );
      return true;
  }
  ```

  **修改後：**
  ```php
  // 如果沒有設定 channel secret，根據環境決定是否跳過驗證
  if ( empty( $channel_secret ) ) {
      $is_dev = $this->is_development_mode();

      if ( $is_dev ) {
          // 開發環境：允許跳過驗證
          $logger->log( 'signature_verification_skipped', array(
              'reason' => 'Development mode: Channel secret not configured',
              'mode' => 'development',
          ) );
          return true;
      } else {
          // 正式環境：拒絕請求
          $logger->log( 'signature_verification_failed', array(
              'reason' => 'Production mode: Channel secret not configured',
              'mode' => 'production',
              'instruction' => 'Please configure LINE Channel Secret in plugin settings',
          ) );
          return false;
      }
  }
  ```

- [ ] 在檔案末尾新增 `is_development_mode()` 方法（在 `verify_signature()` 之後）

  ```php
  /**
   * 檢查是否為開發模式
   *
   * @return bool
   */
  private function is_development_mode() {
      // 方法1: 檢查 WP_DEBUG（最常用）
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
          return true;
      }

      // 方法2: 檢查環境類型（WordPress 5.5+）
      if ( function_exists( 'wp_get_environment_type' ) ) {
          $env_type = wp_get_environment_type();
          if ( in_array( $env_type, array( 'development', 'local' ), true ) ) {
              return true;
          }
      }

      // 方法3: 檢查伺服器名稱（補充判斷）
      if ( isset( $_SERVER['SERVER_NAME'] ) ) {
          $server_name = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
          if ( in_array( $server_name, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
              return true;
          }
      }

      // 預設為正式環境（安全優先）
      return false;
  }
  ```

- [ ] 確認修改正確
  ```bash
  grep -n "is_development_mode" includes/api/class-line-webhook-api.php
  # 應該看到兩處：呼叫處 + 定義處
  ```

- [ ] 提交變更
  ```bash
  git add includes/api/class-line-webhook-api.php
  git commit -m "security: 優化 Webhook 簽章驗證邏輯

- 新增環境變數控制（開發/正式環境）
- 開發環境（WP_DEBUG=true）：允許跳過驗證
- 正式環境（WP_DEBUG=false）：強制驗證
- 新增 is_development_mode() 方法判斷環境

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
  ```

**✅ Phase 2 完成檢查點：**
- [ ] `class-line-service.php` 已使用 `random_int()`
- [ ] `class-line-webhook-api.php` 已加入環境控制
- [ ] 已提交 2 個 commits

---

## 🟡 Phase 3：代碼清理（3 分鐘）

### Task 3.1：刪除備份檔案

**檔案清單：**
- `includes/views/pages/orders.php.backup`
- `includes/services/class-shipment-service.php.backup`
- `includes/services/class-allocation-service.php.backup`
- `components/order/order-detail-modal.php.bak`

**風險評估：** 🟢 安全（這些是重複的備份檔案）

**執行步驟：**

- [ ] 確認備份檔案存在
  ```bash
  find . -name "*.backup" -o -name "*.bak"
  ```

- [ ] 刪除備份檔案
  ```bash
  rm includes/views/pages/orders.php.backup
  rm includes/services/class-shipment-service.php.backup
  rm includes/services/class-allocation-service.php.backup
  rm components/order/order-detail-modal.php.bak
  ```

- [ ] 確認刪除成功
  ```bash
  find . -name "*.backup" -o -name "*.bak"
  # 應該沒有輸出
  ```

- [ ] 更新 `.gitignore`（防止未來產生備份檔案）
  ```bash
  echo "" >> .gitignore
  echo "# 備份檔案" >> .gitignore
  echo "*.backup" >> .gitignore
  echo "*.bak" >> .gitignore
  echo "*.tmp" >> .gitignore
  ```

- [ ] 提交變更
  ```bash
  git add .gitignore
  git rm includes/views/pages/orders.php.backup
  git rm includes/services/class-shipment-service.php.backup
  git rm includes/services/class-allocation-service.php.backup
  git rm components/order/order-detail-modal.php.bak
  git commit -m "chore: 清理備份檔案並更新 .gitignore

- 刪除 4 個備份檔案
- 更新 .gitignore 防止未來產生備份檔案

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
  ```

---

### Task 3.2：提取資料庫版本號為常數

**檔案位置：** `includes/class-plugin.php`

**風險評估：** 🟢 安全（重構，不改變邏輯）

**執行步驟：**

- [ ] 找到資料庫版本號的位置
  ```bash
  grep -n "required_db_version = '1.2.0'" includes/class-plugin.php
  ```

- [ ] 在 `class Plugin` 開頭新增常數

  **修改位置：** 類別定義的開頭
  ```php
  class Plugin {
      /**
       * Database version
       */
      const DB_VERSION = '1.2.0';

      // ... 其他程式碼
  ```

- [ ] 修改 `maybe_upgrade_database()` 方法

  **修改前：**
  ```php
  $required_db_version = '1.2.0';
  ```

  **修改後：**
  ```php
  $required_db_version = self::DB_VERSION;
  ```

- [ ] 確認修改正確
  ```bash
  grep -n "DB_VERSION" includes/class-plugin.php
  # 應該看到兩處：定義處 + 使用處
  ```

- [ ] 提交變更
  ```bash
  git add includes/class-plugin.php
  git commit -m "refactor: 提取資料庫版本號為類別常數

- 將硬編碼的版本號改為 Plugin::DB_VERSION
- 提升代碼可維護性

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
  ```

**✅ Phase 3 完成檢查點：**
- [ ] 備份檔案已刪除
- [ ] `.gitignore` 已更新
- [ ] 資料庫版本號已提取為常數
- [ ] 已提交 2 個 commits

---

## ✅ Phase 4：功能測試（5 分鐘）

### Task 4.1：測試 LINE 綁定碼生成

- [ ] 登入 WordPress 後台
- [ ] 進入「BuyGo+1」→「LINE 設定」
- [ ] 點擊「產生新綁定碼」
- [ ] 確認：
  - [ ] 綁定碼正常產生（6 位數字）
  - [ ] 沒有 PHP 錯誤訊息
  - [ ] 綁定碼可以正常使用

---

### Task 4.2：測試 Webhook 簽章驗證（開發環境）

**前置條件：** 確保 `WP_DEBUG = true`

- [ ] 發送測試 Webhook 到 `/wp-json/buygo-plus-one/v1/line/webhook`
  ```bash
  curl -X POST "http://你的WordPress網址/wp-json/buygo-plus-one/v1/line/webhook" \
    -H "Content-Type: application/json" \
    -d '{"events":[{"type":"message","message":{"type":"text","text":"test"}}]}'
  ```

- [ ] 檢查 Webhook 日誌
  - [ ] 應該看到 `signature_verification_skipped`
  - [ ] 原因：`Development mode: Channel secret not configured`
  - [ ] 模式：`development`

---

### Task 4.3：測試基本功能

- [ ] 後台設定頁面正常載入
- [ ] 訂單列表正常顯示
- [ ] 產品列表正常顯示
- [ ] 出貨管理正常運作

**✅ Phase 4 完成檢查點：**
- [ ] LINE 綁定功能正常
- [ ] Webhook 接收正常（開發模式）
- [ ] 基本功能無異常

---

## 💾 Phase 5：Git 合併與完成（3 分鐘）

### Task 5.1：檢查所有修改

- [ ] 查看 feature 分支的所有 commits
  ```bash
  git log --oneline main..feature/security-optimization
  # 應該看到 4 個新 commits
  ```

- [ ] 查看所有變更的檔案
  ```bash
  git diff main --name-only
  ```

---

### Task 5.2：合併回 main 分支

- [ ] 切回 main 分支
  ```bash
  git checkout main
  ```

- [ ] 合併 feature 分支
  ```bash
  git merge feature/security-optimization --no-ff -m "feat: 完成安全性優化與代碼清理

包含以下改進：
- 修正綁定碼生成器使用密碼學安全的隨機數
- 優化 Webhook 簽章驗證邏輯（環境變數控制）
- 清理備份檔案並更新 .gitignore
- 提取資料庫版本號為類別常數

所有功能已測試正常。

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
  ```

- [ ] 確認合併成功
  ```bash
  git log --oneline -5
  ```

---

### Task 5.3：清理與收尾

- [ ] （可選）刪除 feature 分支
  ```bash
  git branch -d feature/security-optimization
  ```

- [ ] （可選）刪除臨時備份檔案
  ```bash
  rm includes/api/class-line-webhook-api.php.before-optimization
  ```

- [ ] 查看最終 Git 狀態
  ```bash
  git status
  # 應該是乾淨的狀態
  ```

**✅ Phase 5 完成檢查點：**
- [ ] 已合併回 main 分支
- [ ] Git 狀態乾淨
- [ ] 所有測試通過

---

## 📊 完成總結

### 修改的檔案清單

- [x] `includes/services/class-line-service.php`（安全性修正）
- [x] `includes/api/class-line-webhook-api.php`（安全性優化）
- [x] `includes/class-plugin.php`（代碼重構）
- [x] `.gitignore`（清理規則）
- [x] 刪除 4 個備份檔案

### Git Commits 數量

- [x] Phase 1：1-2 commits（Git 準備）
- [x] Phase 2：2 commits（安全性修正）
- [x] Phase 3：2 commits（代碼清理）
- [x] Phase 5：1 commit（合併）

**總計：** 6-7 commits

### 測試結果

- [ ] LINE 綁定功能：✅ 通過
- [ ] Webhook 接收：✅ 通過
- [ ] 基本功能：✅ 通過

---

## ⚠️ 回滾方案（如果出現問題）

### 如果測試失敗，可以立即回滾：

```bash
# 1. 切回 main 分支
git checkout main

# 2. 重置到合併前的狀態
git reset --hard HEAD~1

# 3. 刪除 feature 分支
git branch -D feature/security-optimization
```

### 如果需要恢復備份檔案：

```bash
# 從 Git 歷史中恢復
git checkout HEAD~1 -- includes/api/class-line-webhook-api.php.before-optimization
```

---

## 📝 注意事項

1. **不會破壞現有功能**
   - ✅ 所有修改都是安全的改進
   - ✅ 使用 feature 分支，不直接修改 main
   - ✅ 每個步驟都有測試和確認

2. **開發環境不受影響**
   - ✅ `WP_DEBUG = true` 時，Webhook 行為不變
   - ✅ LINE 綁定功能更安全
   - ✅ 所有現有功能保持正常

3. **正式環境更安全**
   - ✅ 綁定碼無法被預測
   - ✅ Webhook 強制驗證
   - ✅ 符合 WordPress 安全標準

---

## ❓ 執行前確認

**請在開始執行前確認以下事項：**

- [ ] 我已閱讀並理解所有步驟
- [ ] 我確認目前在開發環境（不是正式環境）
- [ ] 我已備份資料庫（或確認可以回滾）
- [ ] 我同意開始執行這些修改

---

**準備好了嗎？請告訴我，我們就可以開始執行！** 🚀
