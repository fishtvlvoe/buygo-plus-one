# 📝 項目測試指南

## 快速開始

```bash
# 進入項目目錄
cd /Users/fishtv/Development/PROJECT_NAME

# 執行所有測試
composer test

# 執行特定測試
composer test -- --filter "testName"

# 生成覆蓋率報告
composer test:coverage
```

---

## 項目結構

```
PROJECT_NAME/
├─ includes/              ← 實際代碼
│  └─ services/
│     └─ class-product-service.php
│
├─ tests/                 ← 測試文件
│  ├─ bootstrap-unit.php  ← 測試啟動文件
│  └─ Unit/
│     └─ Services/
│        └─ ProductServiceBasicTest.php
│
├─ composer.json          ← 依賴和腳本配置
├─ phpunit-unit.xml       ← PHPUnit 配置
├─ .phpunit.config        ← 項目特定配置
└─ TESTING.md             ← 本文件
```

---

## 編寫測試

### 基本結構

```php
<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\ProductService;
use PHPUnit\Framework\TestCase;

class ProductServiceBasicTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        $this->service = new ProductService();
    }

    public function testSomething(): void
    {
        $result = $this->service->doSomething();
        $this->assertEquals(expectedValue, $result);
    }
}
```

### AAA 模式 (Arrange-Act-Assert)

```php
public function testCalculateDiscount(): void
{
    // Arrange: 準備數據
    $price = 100;
    $discount = 0.1;

    // Act: 執行操作
    $result = $this->service->calculateDiscount($price, $discount);

    // Assert: 驗證結果
    $this->assertEquals(90, $result);
}
```

### 常用斷言

```php
// 相等性
$this->assertEquals(expected, actual);
$this->assertNotEquals(unexpected, actual);

// 類型檢查
$this->assertIsArray($result);
$this->assertIsString($result);
$this->assertIsInt($result);

// 字符串檢查
$this->assertStringContainsString('needle', $haystack);
$this->assertStringNotContainsString('needle', $haystack);

// 真假檢查
$this->assertTrue($condition);
$this->assertFalse($condition);

// null 檢查
$this->assertNull($value);
$this->assertNotNull($value);

// 異常
$this->expectException(Exception::class);
$this->service->throwException();
```

---

## 最佳實踐

### 1. 一個測試一個功能

✅ **好的做法**:
```php
public function testCalculateDiscountWithValidPercentage(): void
{
    $result = $this->service->calculateDiscount(100, 0.1);
    $this->assertEquals(90, $result);
}

public function testCalculateDiscountWithInvalidPercentage(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->service->calculateDiscount(100, 1.5);
}
```

❌ **不好的做法**:
```php
public function testCalculateDiscount(): void
{
    $result = $this->service->calculateDiscount(100, 0.1);
    $this->assertEquals(90, $result);

    // 混合了多個測試
    $this->expectException(InvalidArgumentException::class);
    $this->service->calculateDiscount(100, 1.5);
}
```

### 2. 清晰的測試名稱

✅ **好的名稱**:
- `testCalculateDiscountWithValidPercentage`
- `testEmptyItemsReturnsZero`
- `testInvalidDiscountThrowsException`

❌ **不好的名稱**:
- `testDiscount`
- `test1`
- `testStuff`

### 3. 獨立的測試

每個測試應該：
- 不依賴其他測試的結果
- 可以任何順序執行
- 可以單獨執行

```php
// ✅ 好的做法: 每個測試都自己準備數據
public function testDiscount1(): void
{
    $result = $this->service->calculateDiscount(100, 0.1);
    $this->assertEquals(90, $result);
}

public function testDiscount2(): void
{
    $result = $this->service->calculateDiscount(50, 0.2);
    $this->assertEquals(40, $result);
}
```

### 4. 邊界值測試

```php
// 測試邊界情況
public function testMinimumValue(): void
{
    $result = $this->service->calculateDiscount(0, 0.1);
    $this->assertEquals(0, $result);
}

public function testMaximumDiscount(): void
{
    $result = $this->service->calculateDiscount(100, 1);
    $this->assertEquals(0, $result);
}

public function testZeroDiscount(): void
{
    $result = $this->service->calculateDiscount(100, 0);
    $this->assertEquals(100, $result);
}
```

### 5. 測試異常

```php
public function testNegativePriceThrowsException(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Price must be positive');

    $this->service->calculatePrice([-100]);
}
```

---

## 運行測試

### 執行所有測試
```bash
composer test
```

### 執行特定測試類
```bash
composer test -- --filter "ProductServiceBasicTest"
```

### 執行特定測試方法
```bash
composer test -- --filter "testCalculateDiscount"
```

### 詳細輸出
```bash
composer test:unit
```

### 生成覆蓋率報告
```bash
composer test:coverage

# 打開覆蓋率報告
open coverage/index.html
```

---

## 代碼覆蓋率

目標是：
- 所有公開方法都有測試
- 所有代碼路徑都被測試
- 至少 80% 的代碼覆蓋率

檢查覆蓋率報告：
```bash
composer test:coverage
open coverage/index.html
```

---

## 常見問題

### Q: 測試執行失敗，說「Class not found」

A:
1. 檢查 autoload 配置
2. 運行 `composer dump-autoload`
3. 檢查命名空間是否正確

### Q: 測試有時通過，有時失敗

A:
1. 檢查測試是否依賴順序
2. 檢查是否有時間相關的代碼
3. 檢查是否有全局狀態污染

### Q: 怎樣調試失敗的測試？

A:
```bash
# 1. 添加輸出
echo "Debugging: " . $result;

# 2. 單獨運行測試
composer test -- --filter "testName"

# 3. 查看詳細輸出
composer test:unit -- --filter "testName"
```

---

## 下一步

1. **寫更多測試**
   - 為所有公開方法編寫測試
   - 添加邊界值測試
   - 添加異常測試

2. **提高覆蓋率**
   - 目標: >= 80%
   - 使用覆蓋率報告找到未測試的代碼

3. **整合到 CI/CD**
   - 提交代碼前運行測試
   - 每次推送時自動運行測試

---

**記住**: 好的測試是代碼質量的保證。花時間編寫清晰、完整的測試是值得的。

有問題？直接問 AI。

