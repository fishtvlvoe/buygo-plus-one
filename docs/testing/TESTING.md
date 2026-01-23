# BuyGo Plus One - 測試指南

## 📋 快速開始

### 執行測試

```bash
# 執行所有測試 (簡潔輸出)
composer test

# 執行所有測試 (詳細輸出)
composer test:unit

# 執行並生成覆蓋率報告
composer test:coverage

# 設置測試資料庫
composer test:setup-db
```

---

## 📁 測試結構

```
tests/
├── Unit/                          # 單元測試（不依賴 WordPress）
│   ├── Services/
│   │   └── ProductServiceBasicTest.php
│   └── ...
├── Integration/                   # 整合測試（依賴 WordPress）
│   ├── Services/
│   │   └── ProductServiceTest.php
│   └── ...
├── bootstrap-unit.php             # 單元測試啟動檔
├── bootstrap.php                  # 整合測試啟動檔
└── Fixtures/
    └── sample-data.sql            # 測試資料
```

---

## 🧪 測試類型

### 1. 單元測試 (Unit Tests)
- **目的**: 測試純 PHP 邏輯，不涉及資料庫
- **配置**: `phpunit-unit.xml`
- **執行**: `composer test`
- **速度**: ⚡ 快（毫秒級）
- **依賴**: 只需 PHPUnit

**何時寫單元測試**:
- 計算邏輯（折扣、稅務計算）
- 資料驗證和格式化
- 字串操作
- 陣列處理

**範例**:
```php
public function test_calculate_discounted_price() {
    $price = 100;
    $discount = 10;
    $expected = 90;

    $result = $this->calculateDiscountedPrice($price, $discount);

    $this->assertEquals($expected, $result);
}
```

### 2. 整合測試 (Integration Tests)
- **目的**: 測試與 WordPress 和資料庫的互動
- **配置**: `phpunit.xml.dist`
- **執行**: `vendor/bin/phpunit`
- **速度**: 🐢 慢（秒級）
- **依賴**: WordPress 測試套件、測試資料庫

**何時寫整合測試**:
- 資料庫 CRUD 操作
- WordPress Hook 回呼
- FluentCart 整合
- API 端點

---

## 🔧 設置指南

### 前置條件

1. **Composer 依賴已安裝**
   ```bash
   composer install
   ```

2. **WordPress 測試套件已下載**
   ```bash
   bash bin/install-wp-tests.sh wordpress_test root root localhost latest true
   svn export --ignore-externals https://develop.svn.wordpress.org/tags/6.9/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes
   svn export --ignore-externals https://develop.svn.wordpress.org/tags/6.9/tests/phpunit/data/ /tmp/wordpress-tests-lib/data
   ```

3. **測試資料庫已建立**
   ```bash
   composer test:setup-db
   ```

### Local by Flywheel 設定

本外掛已配置為使用 Local by Flywheel 的 MySQL socket:

- **Socket 路徑**: `/Users/fishtv/Library/Application Support/Local/run/oFa4PFqBu/mysql/mysqld.sock`
- **測試資料庫**: `wordpress_test`
- **使用者**: `root`
- **密碼**: `root`

如果 Local 的 MySQL 路徑改變，請更新:
- `phpunit.xml.dist` 中的 `DB_HOST`
- `bin/setup-test-db.php` 中的 `$socket` 變數

---

## 📝 撰寫測試

### 單元測試範本

```php
<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase {

    /**
     * 測試描述性的名稱
     */
    public function test_should_do_something_when_condition_is_met() {
        // Arrange - 準備
        $input = [/* 測試資料 */];
        $expected = [/* 預期結果 */];

        // Act - 執行
        $result = $this->myFunction($input);

        // Assert - 驗證
        $this->assertEquals($expected, $result);
    }

    /**
     * 邊界情況測試
     */
    public function test_handles_empty_input() {
        $this->assertEquals(0, $this->myFunction([]));
    }
}
```

### 命名規則

- **測試類別**: `{Name}Test` (e.g., `ProductServiceTest`)
- **測試方法**: `test_{what_should_happen}_{when_condition}`
  - ✅ `test_calculates_total_price_with_discount`
  - ✅ `test_validates_quantity_exceeds_stock`
  - ❌ `test1`, `testCalc`, `testPrice`

### 斷言 (Assertions)

常用斷言:
```php
$this->assertEquals($expected, $actual);           // 相等
$this->assertNotEquals($expected, $actual);       // 不相等
$this->assertTrue($condition);                     // 為真
$this->assertFalse($condition);                    // 為假
$this->assertNull($value);                         // 為 null
$this->assertEmpty($array);                        // 為空
$this->assertCount(3, $array);                     // 陣列長度
$this->assertContains($needle, $haystack);         // 包含
$this->assertStringContains($substring, $string);  // 字串包含
```

---

## 🎯 測試最佳實踐

### 1. 一個測試方法只測試一件事
✅ **好**
```php
public function test_calculates_discount() { }
public function test_validates_stock_quantity() { }
```

❌ **不好**
```php
public function test_everything() {
    // 測試折扣、庫存、格式等等...
}
```

### 2. 使用描述性的測試名稱
✅ **好**: `test_returns_zero_when_no_items_in_cart`
❌ **不好**: `test_cart`

### 3. 遵循 AAA 模式 (Arrange-Act-Assert)
```php
public function test_something() {
    // Arrange - 準備測試資料
    $product = ['name' => 'Item', 'price' => 100];

    // Act - 執行要測試的操作
    $total = $this->calculateTotal($product);

    // Assert - 驗證結果
    $this->assertEquals(100, $total);
}
```

### 4. 測試應該是獨立的
- 每個測試不應依賴其他測試的結果
- 測試的執行順序不應影響結果
- 使用 `setUp()` 準備通用資料

```php
public function setUp(): void {
    parent::setUp();
    $this->testData = [/* ... */];
}
```

### 5. 測試應該快速
- 單元測試應在毫秒內完成
- 避免在測試中進行重型操作
- 使用 Mock 物件替代外部依賴

---

## 🚀 持續整合

### GitHub Actions 工作流程

計畫建立 `.github/workflows/test.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - run: composer install
      - run: composer test
```

---

## 📊 測試覆蓋率

執行覆蓋率分析:

```bash
composer test:coverage
```

報告將生成在 `coverage/` 目錄。開啟 `coverage/index.html` 查看。

**目標**:
- Services 層: 80%+
- API 層: 70%+
- 工具函數: 90%+

---

## 🐛 除錯測試

### 使用 var_dump

```php
public function test_something() {
    $result = $this->myFunction();
    var_dump($result);  // 將被 PHPUnit 捕捉和顯示
    $this->assertTrue(true);
}
```

### 執行單一測試

```bash
vendor/bin/phpunit -c phpunit-unit.xml --filter test_my_specific_test
```

### 詳細模式

```bash
composer test:unit
```

---

## 📚 參考資源

- [PHPUnit 官方文檔](https://phpunit.de/documentation.html)
- [WordPress 測試套件](https://develop.wordpress.org/handbook/coding-standards/php/)
- [Oberon Lai 的 WordPress 測試教學](https://oberonlai.blog/wordpress-unit-test/)

---

## ❓ 常見問題

### Q: 為什麼我的測試沒有執行?
**A**: 確認:
1. 測試類別名稱以 `Test` 結尾
2. 測試方法以 `test_` 開頭
3. 測試檔案在 `tests/Unit/` 或 `tests/Integration/` 中

### Q: 測試資料庫連接失敗?
**A**: 執行:
```bash
composer test:setup-db
```

確認 Local 的 MySQL 已啟動，且 socket 路徑正確。

### Q: 如何在 CI/CD 中執行測試?
**A**: 將 `composer test` 添加到你的 GitHub Actions 或其他 CI/CD 工作流程。

---

## 📞 支持

如有問題，請參考:
- [BuyGo Plus One 文檔](./README.md)
- [GitHub Issues](https://github.com/yourusername/buygo-plus-one/issues)
