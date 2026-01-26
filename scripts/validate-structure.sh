#!/bin/bash

# ============================================
# BuyGo+1 結構驗證腳本
# ============================================
#
# 檢查常見的編碼問題，包括：
# - wpNonce 是否在 return 中導出
# - permission_callback 設定
# - CSS 類名是否使用前綴
# - fetch 是否帶有 X-WP-Nonce header
#

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 專案根目錄
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ADMIN_DIR="$PROJECT_DIR/admin/partials"
API_DIR="$PROJECT_DIR/includes/api"

ERRORS=0
WARNINGS=0

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}BuyGo+1 結構驗證${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# ============================================
# 檢查 wpNonce 導出
# ============================================
echo -e "${YELLOW}檢查 wpNonce 導出...${NC}"

for file in "$ADMIN_DIR"/*.php; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")

        # 檢查是否定義了 wpNonce
        if grep -q "const wpNonce = " "$file"; then
            # 檢查是否在 return 中導出
            if ! grep -q "return {" "$file" || ! grep -A 50 "return {" "$file" | grep -q "wpNonce"; then
                echo -e "${RED}✗ $filename: wpNonce 已定義但可能未在 return 中導出${NC}"
                ((ERRORS++))
            else
                echo -e "${GREEN}✓ $filename: wpNonce 正確導出${NC}"
            fi
        fi
    fi
done

echo ""

# ============================================
# 檢查 permission_callback
# ============================================
echo -e "${YELLOW}檢查 permission_callback...${NC}"

for file in "$API_DIR"/*.php; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")

        # 跳過 LINE webhook API（使用 __return_true）
        if [[ "$filename" == *"line"* ]]; then
            continue
        fi

        # 檢查是否使用正確的權限檢查
        if grep -q "permission_callback" "$file"; then
            if grep -q "API::class, 'check_permission'" "$file"; then
                echo -e "${GREEN}✓ $filename: 使用統一權限檢查${NC}"
            elif grep -q "__return_true" "$file"; then
                echo -e "${YELLOW}! $filename: 使用 __return_true（確認是否正確）${NC}"
                ((WARNINGS++))
            else
                echo -e "${RED}✗ $filename: permission_callback 可能設定不正確${NC}"
                ((ERRORS++))
            fi
        fi
    fi
done

echo ""

# ============================================
# 檢查 CSS 類名前綴
# ============================================
echo -e "${YELLOW}檢查 CSS 類名前綴...${NC}"

# 定義每個頁面的前綴
declare -A PAGE_PREFIXES
PAGE_PREFIXES["products.php"]="products-"
PAGE_PREFIXES["orders.php"]="orders-"
PAGE_PREFIXES["customers.php"]="customers-"
PAGE_PREFIXES["shipment-details.php"]="shipment-"
PAGE_PREFIXES["shipment-products.php"]="shipment-"
PAGE_PREFIXES["settings.php"]="settings-"

for file in "$ADMIN_DIR"/*.php; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")
        prefix="${PAGE_PREFIXES[$filename]}"

        if [ -n "$prefix" ]; then
            # 檢查是否有不帶前綴的通用類名（在 <style> 區塊內）
            if grep -q "<style>" "$file"; then
                # 簡單檢查：是否有以 . 開頭但不帶前綴的類名
                generic_classes=$(grep -o '\.[a-z][a-z-]*\s*{' "$file" | grep -v "^\.$prefix" | head -5)
                if [ -n "$generic_classes" ]; then
                    echo -e "${YELLOW}! $filename: 可能有未使用前綴的 CSS 類名${NC}"
                    ((WARNINGS++))
                else
                    echo -e "${GREEN}✓ $filename: CSS 類名檢查通過${NC}"
                fi
            fi
        fi
    fi
done

echo ""

# ============================================
# 檢查 fetch X-WP-Nonce header
# ============================================
echo -e "${YELLOW}檢查 fetch X-WP-Nonce header...${NC}"

for file in "$ADMIN_DIR"/*.php; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")

        # 檢查是否有 fetch 呼叫
        fetch_count=$(grep -c "await fetch" "$file" 2>/dev/null || echo "0")
        nonce_count=$(grep -c "X-WP-Nonce" "$file" 2>/dev/null || echo "0")

        if [ "$fetch_count" -gt 0 ]; then
            if [ "$nonce_count" -ge "$fetch_count" ]; then
                echo -e "${GREEN}✓ $filename: fetch 請求帶有 X-WP-Nonce${NC}"
            else
                echo -e "${YELLOW}! $filename: 有 $fetch_count 個 fetch，但只有 $nonce_count 個 X-WP-Nonce${NC}"
                ((WARNINGS++))
            fi
        fi
    fi
done

echo ""

# ============================================
# 總結
# ============================================
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}驗證完成${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}錯誤：$ERRORS${NC}"
fi

if [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}警告：$WARNINGS${NC}"
fi

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}全部通過！${NC}"
fi

exit $ERRORS
