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
# - 頁首/內容結構
# - 檢視切換邏輯
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

# 定義每個頁面的前綴的函數
get_page_prefix() {
    case "$1" in
        products.php) echo "products-" ;;
        orders.php) echo "orders-" ;;
        customers.php) echo "customers-" ;;
        shipment-details.php) echo "shipment-" ;;
        shipment-products.php) echo "shipment-" ;;
        settings.php) echo "settings-" ;;
        *) echo "" ;;
    esac
}

for file in "$ADMIN_DIR"/*.php; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")
        prefix=$(get_page_prefix "$filename")

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
        fetch_count=$(grep -o "await fetch" "$file" 2>/dev/null | wc -l | tr -d ' \n')
        nonce_count=$(grep -o "X-WP-Nonce" "$file" 2>/dev/null | wc -l | tr -d ' \n')
        : ${fetch_count:=0}
        : ${nonce_count:=0}

        if [ "$fetch_count" -gt 0 ] 2>/dev/null; then
            if [ "$nonce_count" -ge "$fetch_count" ] 2>/dev/null; then
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
# 檢查頁首/內容結構
# ============================================
echo -e "${YELLOW}檢查頁首/內容結構...${NC}"

for file in "$ADMIN_DIR"/*.php; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")

        # 跳過 template.php 和其他非頁面檔案
        if [[ "$filename" == "template.php" ]] || [[ "$filename" == "index.php" ]]; then
            continue
        fi

        # 檢查是否有正確的結構註解
        has_header_comment=$(grep -o "<!-- 頁首部分" "$file" 2>/dev/null | wc -l | tr -d ' \n')
        has_content_comment=$(grep -o "<!-- 內容區域" "$file" 2>/dev/null | wc -l | tr -d ' \n')
        : ${has_header_comment:=0}
        : ${has_content_comment:=0}

        if [ "$has_header_comment" -eq 0 ] 2>/dev/null || [ "$has_content_comment" -eq 0 ] 2>/dev/null; then
            echo -e "${YELLOW}! $filename: 缺少結構註解（<!-- 頁首部分 --> 或 <!-- 內容區域 -->）${NC}"
            ((WARNINGS++))
        else
            echo -e "${GREEN}✓ $filename: 結構註解存在${NC}"
        fi

        # 檢查 header 標籤是否正確（不應該在 v-show="currentView === 'list'" 內）
        # 警告：這是簡單的檢查，可能有誤報
        if grep -q "<header" "$file"; then
            # 檢查 header 是否在 main 標籤內但在 v-show 列表檢視外
            if grep -B 5 "<header" "$file" | grep -q "v-show=\"currentView === 'list'\""; then
                echo -e "${RED}✗ $filename: header 可能嵌套在 v-show='list' 內（會導致檢視切換失敗）${NC}"
                ((ERRORS++))
            fi
        fi
    fi
done

echo ""

# ============================================
# 檢查檢視切換邏輯
# ============================================
echo -e "${YELLOW}檢查檢視切換邏輯...${NC}"

for file in "$ADMIN_DIR"/*.php; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")

        # 跳過 template.php 和其他非頁面檔案
        if [[ "$filename" == "template.php" ]] || [[ "$filename" == "index.php" ]]; then
            continue
        fi

        # 檢查是否有 v-show="currentView" 的使用
        if grep -q "v-show=\"currentView" "$file"; then
            # 檢查列表和詳情檢視是否為平級（兄弟元素）
            list_view_count=$(grep -c "v-show=\"currentView === 'list'\"" "$file" 2>/dev/null || echo "0")

            if [ "$list_view_count" -gt 0 ]; then
                # 檢查是否有其他檢視（edit, detail, allocation 等）
                other_views=$(grep -o "v-show=\"currentView === '[^']*'\"" "$file" | grep -v "list" | wc -l | tr -d ' \n')

                if [ "$other_views" -gt 0 ] 2>/dev/null; then
                    echo -e "${GREEN}✓ $filename: 檢視切換邏輯存在（列表 + ${other_views} 個其他檢視）${NC}"
                else
                    echo -e "${YELLOW}! $filename: 只有列表檢視，沒有其他檢視${NC}"
                    ((WARNINGS++))
                fi
            fi
        else
            # 某些頁面可能不需要檢視切換（如 settings.php）
            echo -e "${YELLOW}! $filename: 未使用檢視切換（如果是設定頁面則正常）${NC}"
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
