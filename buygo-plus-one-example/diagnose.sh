#!/bin/bash

echo "=== WordPress Plugin Testing 診斷 ==="
echo ""

echo "1. 當前目錄："
pwd
echo ""

echo "2. PHP 版本："
php --version | head -1
echo ""

echo "3. Composer 版本："
composer --version
echo ""

echo "4. vendor 目錄存在？"
if [ -d "vendor" ]; then
    echo "✓ vendor 目錄存在"
else
    echo "✗ vendor 目錄不存在，請執行: composer install"
fi
echo ""

echo "5. PHPUnit 可執行？"
if [ -f "vendor/bin/phpunit" ]; then
    echo "✓ PHPUnit 已安裝"
    ./vendor/bin/phpunit --version
else
    echo "✗ PHPUnit 未安裝"
fi
echo ""

echo "6. 測試檔案存在？"
if [ -f "tests/Unit/Services/ServiceBasicTest.php" ]; then
    echo "✓ 測試檔案存在"
else
    echo "✗ 測試檔案不存在"
fi
echo ""

echo "7. ProductService 類別存在？"
if [ -f "includes/services/class-product-service.php" ]; then
    echo "✓ ProductService 類別存在"
else
    echo "✗ ProductService 類別不存在"
fi
echo ""

echo "8. 執行測試："
composer test
echo ""

echo "=== 診斷完成 ==="
