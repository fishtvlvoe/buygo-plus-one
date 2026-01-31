# GitHub Releases WordPress 自動更新研究報告

**專案:** BuyGo+1 WordPress 外掛
**研究日期:** 2026-01-31
**研究模式:** Feasibility（可行性評估）
**整體信心度:** HIGH

---

## 執行摘要

WordPress 外掛透過 GitHub Releases 實現自動更新是**完全可行**的。推薦使用 **YahnisElsts/plugin-update-checker** 函式庫，這是目前最成熟、維護最積極、使用最廣泛的解決方案（超過 120 萬次安裝）。

實現方式：
1. 透過 Composer 安裝 plugin-update-checker 函式庫
2. 在外掛主檔案加入約 15 行設定程式碼
3. 在 GitHub 建立 Release 並上傳 ZIP 檔案
4. 用戶即可在 WP 後台看到更新通知

---

## WordPress 更新機制原理

### 核心機制

WordPress 使用 `update_plugins` transient 來追蹤外掛更新狀態。每 12 小時檢查一次。

**關鍵 Hooks:**
| Hook | 用途 |
|------|------|
| `pre_set_site_transient_update_plugins` | 注入自訂更新資訊 |
| `plugins_api` | 提供外掛詳情（點擊「查看詳情」時） |
| `upgrader_process_complete` | 更新完成後的回調 |

### 更新流程

```
1. WP 定期檢查 update_plugins transient
2. 若發現新版本，在後台顯示更新通知
3. 用戶點擊「立即更新」
4. WP 下載 ZIP → 解壓縮 → 替換外掛檔案
5. 觸發 upgrader_process_complete hook
```

---

## 推薦方案：Plugin Update Checker

### 為什麼選擇這個方案

| 考量因素 | Plugin Update Checker | Git Updater | 自己實作 |
|---------|----------------------|-------------|---------|
| **安裝方式** | Composer 或手動 | 需安裝額外外掛 | N/A |
| **維護狀態** | 積極維護（2025-05 更新） | 積極維護 | 需自己維護 |
| **程式碼侵入性** | 低（15 行程式碼） | 無（純設定） | 高（約 200 行） |
| **私有 Repo 支援** | 有（Token 認證） | 有（Token 認證） | 需自己實作 |
| **適用場景** | 開發者發布外掛 | 站長管理多個外掛 | 特殊需求 |
| **推薦度** | **首選** | 適合站長 | 不推薦 |

**結論：Plugin Update Checker 是最佳選擇**
- 對外掛開發者最友善
- 程式碼嵌入外掛內，用戶無需額外安裝
- Composer 整合，版本管理方便

### 版本資訊

| 項目 | 值 |
|------|-----|
| 最新版本 | v5.6 |
| 發布日期 | 2025-05-20 |
| PHP 要求 | >= 5.6.20 |
| Packagist 安裝數 | 1,219,637+ |
| GitHub Stars | 2,376 |

---

## 實作指南

### Step 1: 安裝函式庫

```bash
cd /Users/fishtv/Development/buygo-plus-one-dev
composer require yahnis-elsts/plugin-update-checker
```

### Step 2: 在主外掛檔案加入設定

在 `buygo-plus-one.php` 的 `plugins_loaded` hook 之後加入：

```php
/**
 * GitHub 自動更新檢查器
 *
 * 透過 GitHub Releases 提供自動更新功能
 */
add_action('init', function() {
    // 只在後台執行
    if (!is_admin()) {
        return;
    }

    // 載入更新檢查器（使用 Composer autoload）
    if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
        $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/your-username/buygo-plus-one/',  // GitHub Repo URL
            BUYGO_PLUS_ONE_PLUGIN_FILE,                          // 主外掛檔案路徑
            'buygo-plus-one'                                      // 外掛 slug
        );

        // 設定要監控的分支（通常是 main 或 master）
        $updateChecker->setBranch('main');

        // 啟用 Release Assets（使用上傳的 ZIP 而非自動產生的）
        $updateChecker->getVcsApi()->enableReleaseAssets();

        // 如果是私有 Repo，設定 Token（從設定讀取）
        // $token = get_option('buygo_github_token');
        // if ($token) {
        //     $updateChecker->setAuthentication($token);
        // }
    }
});
```

### Step 3: 版本號格式

**重要：** 版本號必須在兩個地方保持一致：

1. **外掛主檔案 Header:**
```php
/**
 * Plugin Name: BuyGo+1
 * Version: 1.0.0
 * ...
 */
```

2. **GitHub Release Tag:**
```
Release Tag: 1.0.0 (或 v1.0.0)
Release Title: v1.0.0 - 功能更新
```

**支援的版本格式:**
- `1.0.0` (推薦)
- `v1.0.0`
- `1.0.0-beta1`
- `1.0.0_rc1`

### Step 4: 建立 GitHub Release

1. 在 GitHub Repository 點擊 "Releases" > "Create a new release"
2. 填寫 Tag version（如 `1.0.0`）
3. 填寫 Release title（如 `v1.0.0 - 初始版本`）
4. 撰寫 Release notes（這會顯示在 WP 的「查看詳情」中）
5. **重要：** 上傳 ZIP 檔案作為 Release Asset

### Step 5: 製作發布 ZIP

建立發布腳本 `release.sh`：

```bash
#!/bin/bash
VERSION=$(grep "Version:" buygo-plus-one.php | awk '{print $3}')
PLUGIN_SLUG="buygo-plus-one"

echo "Building release for ${PLUGIN_SLUG} v${VERSION}..."

# 建立暫存目錄
rm -rf /tmp/${PLUGIN_SLUG}
mkdir -p /tmp/${PLUGIN_SLUG}

# 複製檔案（排除開發用檔案）
rsync -av --exclude='.git' \
          --exclude='.github' \
          --exclude='node_modules' \
          --exclude='tests' \
          --exclude='*.log' \
          --exclude='.DS_Store' \
          --exclude='composer.lock' \
          --exclude='phpunit*.xml' \
          --exclude='.planning' \
          --exclude='docs' \
          --exclude='test-*.php' \
          --exclude='check-*.php' \
          --exclude='*.md' \
          ./ /tmp/${PLUGIN_SLUG}/

# 建立 ZIP
cd /tmp
zip -r ${PLUGIN_SLUG}-${VERSION}.zip ${PLUGIN_SLUG}

echo "Created: /tmp/${PLUGIN_SLUG}-${VERSION}.zip"
```

**ZIP 結構要求（必須遵守）:**
```
buygo-plus-one-1.0.0.zip
└── buygo-plus-one/           <-- 必須有子資料夾
    ├── buygo-plus-one.php
    ├── includes/
    ├── admin/
    ├── assets/
    └── vendor/
```

---

## 私有 Repository 設定

如果 BuyGo+1 是私有 Repository：

### 建立 GitHub Personal Access Token

1. GitHub > Settings > Developer settings > Personal access tokens > Tokens (classic)
2. 點擊 "Generate new token (classic)"
3. 選擇權限：`repo`（完整 repository 存取）
4. 設定過期時間（建議 90 天或更長）
5. 產生並儲存 Token

### 在外掛中設定 Token

**方式 A：寫死在程式碼（不推薦，但最簡單）**
```php
$updateChecker->setAuthentication('ghp_xxxxxxxxxxxx');
```

**方式 B：從 WordPress 設定讀取（推薦）**
```php
// 在設定頁面加入 Token 輸入欄位
$token = get_option('buygo_github_token');
if ($token) {
    $updateChecker->setAuthentication($token);
}
```

**方式 C：使用環境變數（適合多站部署）**
```php
$token = getenv('BUYGO_GITHUB_TOKEN');
if ($token) {
    $updateChecker->setAuthentication($token);
}
```

---

## 替代方案比較

### 方案 A：Git Updater（適合站長）

**使用情境：** 站長管理多個從 GitHub 安裝的外掛

**優點：**
- 無需修改外掛程式碼
- 支援多個 Git 平台（GitHub、GitLab、Bitbucket）
- 統一管理多個外掛的更新

**缺點：**
- 需要用戶額外安裝 Git Updater 外掛
- 設定較複雜
- 不適合「一鍵安裝即可用」的商業外掛

**設定方式：**
```php
/**
 * Plugin Name: BuyGo+1
 * GitHub Plugin URI: https://github.com/your-username/buygo-plus-one
 * GitHub Branch: main
 */
```

### 方案 B：自己實作（不推薦）

需要實作兩個 Filter Hooks：

```php
// 1. 注入更新資訊
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    // 呼叫 GitHub API 檢查最新版本
    // 比較版本號
    // 若有新版本，加入 $transient->response
    return $transient;
});

// 2. 提供外掛詳情
add_filter('plugins_api', function($result, $action, $args) {
    if ($args->slug !== 'buygo-plus-one') {
        return $result;
    }
    // 回傳外掛資訊（名稱、版本、changelog 等）
    return $plugin_info;
}, 10, 3);
```

**為什麼不推薦：**
- 需要處理 GitHub API Rate Limit
- 需要處理快取機制
- 需要處理認證 Token
- 需要處理錯誤情況
- Plugin Update Checker 已經做好這些

---

## 常見問題與注意事項

### 1. 更新後外掛資料夾名稱改變

**問題：** 更新後資料夾從 `buygo-plus-one` 變成 `buygo-plus-one-main`

**解決：**
- 使用 `enableReleaseAssets()` 並上傳正確結構的 ZIP
- 或確保 ZIP 內的子資料夾名稱與外掛 slug 相同

### 2. 私有 Repo Token 過期

**問題：** Token 過期後更新失敗

**解決：**
- 設定 Token 時選擇較長的過期時間
- 在後台設定頁面提供 Token 更新功能
- 更新失敗時顯示友善的錯誤訊息

### 3. Pre-release 版本被當作正式版

**問題：** 用戶收到 alpha/beta 版本的更新通知

**解決：**
- Plugin Update Checker 會自動忽略標記為 "Pre-release" 的版本
- 確保在 GitHub 建立 Release 時勾選 "This is a pre-release"

### 4. 更新檢查間隔

**預設：** 每 12 小時檢查一次

**自訂：**
```php
// 每 6 小時檢查一次
$updateChecker->setCheckPeriod(6);

// 每次頁面載入都檢查（不建議）
$updateChecker->setCheckPeriod(0);
```

### 5. Debug 模式

開發時可以強制觸發更新檢查：

```php
// 在 wp-config.php 加入
define('PUC_DEBUG', true);
```

或使用 Debug Bar 外掛配合 Debug Bar Plugin Update Checker 外掛。

---

## 實作建議

### Phase 1：基本實作（建議先完成）

1. 安裝 plugin-update-checker via Composer
2. 加入基本設定程式碼
3. 建立 release.sh 腳本
4. 測試從 GitHub 更新

### Phase 2：進階功能（可選）

1. 設定頁面加入 GitHub Token 輸入
2. 更新通知的自訂樣式
3. Changelog 在後台顯示
4. 更新失敗的錯誤處理

### 建議的 GitHub Workflow

```yaml
# .github/workflows/release.yml
name: Create Release

on:
  push:
    tags:
      - '*'

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'

      - name: Install Dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Create ZIP
        run: |
          PLUGIN_SLUG="buygo-plus-one"
          mkdir -p /tmp/${PLUGIN_SLUG}
          rsync -av --exclude='.git*' --exclude='tests' --exclude='*.md' ./ /tmp/${PLUGIN_SLUG}/
          cd /tmp && zip -r ${PLUGIN_SLUG}.zip ${PLUGIN_SLUG}

      - name: Create Release
        uses: softprops/action-gh-release@v1
        with:
          files: /tmp/buygo-plus-one.zip
```

---

## 信心度評估

| 領域 | 信心度 | 原因 |
|------|--------|------|
| 可行性 | HIGH | 成熟的解決方案，大量生產使用案例 |
| 實作複雜度 | HIGH | 明確的實作步驟，豐富的文件 |
| 維護需求 | HIGH | 函式庫積極維護，相容 PHP 8.4 |
| 私有 Repo | MEDIUM | 需要管理 Token 過期問題 |

---

## 資料來源

### 主要來源（HIGH 信心度）

- [YahnisElsts/plugin-update-checker GitHub](https://github.com/YahnisElsts/plugin-update-checker) - 官方 Repository
- [Packagist - yahnis-elsts/plugin-update-checker](https://packagist.org/packages/yahnis-elsts/plugin-update-checker) - Composer 套件
- [Plugin Update Checker Releases](https://github.com/YahnisElsts/plugin-update-checker/releases) - 版本紀錄

### 參考來源（MEDIUM 信心度）

- [Rudra Styh - Self-Hosted Plugin Update](https://rudrastyh.com/wordpress/self-hosted-plugin-update.html) - WordPress Hooks 說明
- [Git Updater GitHub](https://github.com/afragen/git-updater) - 替代方案
- [WordPress Developer Reference - wp_update_plugins()](https://developer.wordpress.org/reference/functions/wp_update_plugins/) - 官方文件

### 社群討論（LOW 信心度）

- [BlogVault - WordPress Plugin Update From Github](https://blogvault.net/wordpress-plugin-update-from-github/)
- [Anchor Host - Using Github To Self-Host Updates](https://anchor.host/using-github-to-self-host-updates-for-wordpress-plugins/)

---

## 結論

**推薦方案：** 使用 `yahnis-elsts/plugin-update-checker` 函式庫

**理由：**
1. 最成熟穩定的解決方案（120 萬+ 安裝）
2. 積極維護，支援最新 PHP 版本
3. Composer 整合，程式碼乾淨
4. 約 15 行程式碼即可完成設定
5. 自動處理 GitHub API、快取、版本比較等複雜邏輯

**預估工時：** 2-4 小時（含測試）

**下一步：**
1. 在 `composer.json` 加入 `yahnis-elsts/plugin-update-checker`
2. 在 `buygo-plus-one.php` 加入更新檢查程式碼
3. 建立 `release.sh` 發布腳本
4. 建立第一個 GitHub Release 測試
