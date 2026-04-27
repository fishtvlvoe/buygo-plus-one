<!-- SPECTRA:START v1.0.2 -->

# Spectra Instructions

This project uses Spectra for Spec-Driven Development(SDD). Specs live in `openspec/specs/`, change proposals in `openspec/changes/`.

## Use `/spectra-*` skills when:

- A discussion needs structure before coding → `/spectra-discuss`
- User wants to plan, propose, or design a change → `/spectra-propose`
- Tasks are ready to implement → `/spectra-apply`
- There's an in-progress change to continue → `/spectra-ingest`
- User asks about specs or how something works → `/spectra-ask`
- Implementation is done → `/spectra-archive`
- Commit only files related to a specific change → `/spectra-commit`

## Workflow

discuss? → propose → apply ⇄ ingest → archive

- `discuss` is optional — skip if requirements are clear
- Requirements change mid-work? Plan mode → `ingest` → resume `apply`

## Parked Changes

Changes can be parked（暫存）— temporarily moved out of `openspec/changes/`. Parked changes won't appear in `spectra list` but can be found with `spectra list --parked`. To restore: `spectra unpark <name>`. The `/spectra-apply` and `/spectra-ingest` skills handle parked changes automatically.

<!-- SPECTRA:END -->

# CLAUDE.md — BuyGo Plus One

<gates>
trigger: 任何程式碼修改前
action: 確認只修改 `wp-plugins/buygo-plus-one/` 目錄內的檔案

trigger: 新增商業邏輯
action: 邏輯放在 `includes/services/`，API 層只做驗證和路由
violation: 在 `includes/api/` 中寫商業邏輯

trigger: 修改資料表結構
action: 一律用 WordPress 遷移 hook，禁止直接修改表結構

trigger: 需要呼叫其他外掛（如 LineHub）的功能
action: 一律透過 WordPress hooks，禁止直接 `new` 或 `require` 其他外掛的 class
</gates>

<rules>
## 熵減原則
policy: 主入口（buygo-plus-one.php）< 50 行
policy: 外掛載入器（class-plugin.php）< 150 行
policy: 單一 Service class < 300 行

## 架構規範
policy: 商業邏輯只放在 `includes/services/`
policy: `includes/api/` 只做輸入驗證和路由
policy: `includes/core/` 放通用基礎設施
policy: `includes/integrations/` 放第三方整合（FluentCart 等）
banned: 在 API 層寫商業邏輯
banned: 直接查詢資料庫（必須透過 service）
banned: 修改表結構而不用遷移腳本

## 測試規範（TDD）
policy: 先寫測試，再寫實作
policy: 測試只測純 PHP 邏輯，不依賴 WordPress 運行環境
banned: 測試中直接呼叫 `wp_insert_post()`、`wpdb` 等 WordPress 全域函數

## Git 規範
policy: 功能用 `feature/xxx` 分支，修 bug 用 `fix/xxx` 分支
banned: 直接 push main 分支，必須建 PR
</rules>

<conn>
## 常用指令
```bash
cd /Users/fishtv/Development/wp-plugins/buygo-plus-one
composer test                                        # 所有測試
composer test -- --filter "Service"                  # 只測 services
composer test:unit                                   # 詳細輸出
composer test:coverage                               # 生成覆蓋率報告
```

## 初始化流程
```
buygo-plus-one.php（定義常數、註冊 activate hook）
  → plugins_loaded（優先級 20）
  → Plugin::instance()->init()
  → load_dependencies()（載入 service、api、admin）
  → register_hooks()（初始化路由、API、後台）
```

## 重要服務一覽
| 服務 | 位置 | 說明 |
|------|------|------|
| AllocationService | services/ | 庫存分配與釋放 |
| OrderService | services/ | 訂單建立與狀態更新 |
| ShipmentService | services/ | 出貨建立與追蹤 |
| IdentityService | services/ | 用戶身份驗證 |
| SettingsService | services/ | 外掛設定讀寫 |
| DashboardService | services/ | 儀表板資料快取 |
| ExportService | services/ | 資料匯出 |
| EncryptionService | services/ | 敏感資料加密 |

## 本機錯誤日誌
```bash
tail -f /Users/fishtv/Local\ Sites/buygo/app/logs/php-error.log
```

## 部署
```bash
# 用 /deploy skill，不要自己寫 rsync
```
</conn>

<ref>
## 目錄結構
```
buygo-plus-one/
├── buygo-plus-one.php           # 主入口（< 50 行）
├── includes/
│   ├── class-plugin.php         # 外掛載入器（< 150 行）
│   ├── class-routes.php         # 前端路由
│   ├── class-database.php       # 資料庫初始化
│   ├── class-database-checker.php
│   ├── autoload.php
│   ├── functions.php
│   ├── core/                    # 通用基礎設施（class-buygo-plus-core.php）
│   ├── services/                # ⭐ 商業邏輯層（20+ classes）
│   ├── api/                     # REST API 端點（15+ classes）
│   ├── admin/                   # WordPress 後台頁面
│   ├── integrations/            # 第三方整合（FluentCart）
│   ├── cli/                     # WP-CLI 指令
│   ├── monitoring/              # 監控與診斷
│   └── diagnostics/             # 系統診斷
├── tests/                       # PHPUnit 測試
├── admin/                       # 後台 PHP/CSS/JS
├── assets/                      # 靜態資源
├── templates/                   # PHP 模板
├── liff/                        # LIFF 頁面
└── composer.json
```

## 相關文檔
- 系統架構：`../docs/architecture.md`
- 根目錄指引：`../../CLAUDE.md`
- 技術決策：`../docs/decisions/`
</ref>

<!-- code-review-graph MCP tools -->
## MCP Tools: code-review-graph

**IMPORTANT: This project has a knowledge graph. ALWAYS use the
code-review-graph MCP tools BEFORE using Grep/Glob/Read to explore
the codebase.** The graph is faster, cheaper (fewer tokens), and gives
you structural context (callers, dependents, test coverage) that file
scanning cannot.

### When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes` or `query_graph` instead of Grep
- **Understanding impact**: `get_impact_radius` instead of manually tracing imports
- **Code review**: `detect_changes` + `get_review_context` instead of reading entire files
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

Fall back to Grep/Glob/Read **only** when the graph doesn't cover what you need.

### Key Tools

| Tool | Use when |
|------|----------|
| `detect_changes` | Reviewing code changes — gives risk-scored analysis |
| `get_review_context` | Need source snippets for review — token-efficient |
| `get_impact_radius` | Understanding blast radius of a change |
| `get_affected_flows` | Finding which execution paths are impacted |
| `query_graph` | Tracing callers, callees, imports, tests, dependencies |
| `semantic_search_nodes` | Finding functions/classes by name or keyword |
| `get_architecture_overview` | Understanding high-level codebase structure |
| `refactor_tool` | Planning renames, finding dead code |

### Workflow

1. The graph auto-updates on file changes (via hooks).
2. Use `detect_changes` for code review.
3. Use `get_affected_flows` to understand impact.
4. Use `query_graph` pattern="tests_for" to check coverage.
