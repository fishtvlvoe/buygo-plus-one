#!/bin/bash

# ============================================
# BuyGo+1 功能建立腳本
# ============================================
#
# 使用方式：
#   ./scripts/create-feature.sh <EntityName> <中文名稱>
#
# 範例：
#   ./scripts/create-feature.sh Report 報表
#   ./scripts/create-feature.sh Category 分類
#
# 這會建立：
#   - includes/services/class-{entity}-service.php
#   - includes/api/class-{entities}-api.php
#   - admin/partials/{entities}.php
#

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 檢查參數
if [ $# -lt 2 ]; then
    echo -e "${RED}錯誤：需要提供實體名稱和中文名稱${NC}"
    echo ""
    echo "使用方式："
    echo "  ./scripts/create-feature.sh <EntityName> <中文名稱>"
    echo ""
    echo "範例："
    echo "  ./scripts/create-feature.sh Report 報表"
    exit 1
fi

ENTITY=$1
CHINESE_NAME=$2

# 轉換名稱格式
ENTITY_LOWER=$(echo "$ENTITY" | tr '[:upper:]' '[:lower:]')
ENTITIES_LOWER="${ENTITY_LOWER}s"
ENTITIES_UPPER="${ENTITY}s"

# 專案根目錄
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TEMPLATES_DIR="$PROJECT_DIR/templates"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}BuyGo+1 功能建立腳本${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "實體名稱：$ENTITY ($CHINESE_NAME)"
echo "小寫單數：$ENTITY_LOWER"
echo "小寫複數：$ENTITIES_LOWER"
echo ""

# 確認
read -p "確定要建立這些檔案嗎？(y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}已取消${NC}"
    exit 0
fi

# 建立服務層
echo -e "${YELLOW}建立服務層...${NC}"
SERVICE_FILE="$PROJECT_DIR/includes/services/class-${ENTITY_LOWER}-service.php"
if [ -f "$SERVICE_FILE" ]; then
    echo -e "${RED}警告：$SERVICE_FILE 已存在，跳過${NC}"
else
    cp "$TEMPLATES_DIR/service-template.php" "$SERVICE_FILE"
    sed -i '' "s/{Entity}/$ENTITY/g" "$SERVICE_FILE"
    sed -i '' "s/{entity}/$ENTITY_LOWER/g" "$SERVICE_FILE"
    sed -i '' "s/{Entities}/$ENTITIES_UPPER/g" "$SERVICE_FILE"
    sed -i '' "s/{entities}/$ENTITIES_LOWER/g" "$SERVICE_FILE"
    sed -i '' "s/{實體描述}/$CHINESE_NAME/g" "$SERVICE_FILE"
    echo -e "${GREEN}✓ 已建立：$SERVICE_FILE${NC}"
fi

# 建立 API
echo -e "${YELLOW}建立 API...${NC}"
API_FILE="$PROJECT_DIR/includes/api/class-${ENTITIES_LOWER}-api.php"
if [ -f "$API_FILE" ]; then
    echo -e "${RED}警告：$API_FILE 已存在，跳過${NC}"
else
    cp "$TEMPLATES_DIR/api-template.php" "$API_FILE"
    sed -i '' "s/{Entity}/$ENTITY/g" "$API_FILE"
    sed -i '' "s/{entity}/$ENTITY_LOWER/g" "$API_FILE"
    sed -i '' "s/{Entities}/$ENTITIES_UPPER/g" "$API_FILE"
    sed -i '' "s/{entities}/$ENTITIES_LOWER/g" "$API_FILE"
    sed -i '' "s/{實體描述}/$CHINESE_NAME/g" "$API_FILE"
    echo -e "${GREEN}✓ 已建立：$API_FILE${NC}"
fi

# 建立頁面
echo -e "${YELLOW}建立管理員頁面...${NC}"
PAGE_FILE="$PROJECT_DIR/admin/partials/${ENTITIES_LOWER}.php"
if [ -f "$PAGE_FILE" ]; then
    echo -e "${RED}警告：$PAGE_FILE 已存在，跳過${NC}"
else
    cp "$TEMPLATES_DIR/admin-page-template.php" "$PAGE_FILE"
    sed -i '' "s/{PageName}/$ENTITIES_UPPER/g" "$PAGE_FILE"
    sed -i '' "s/{page-name}/$ENTITIES_LOWER/g" "$PAGE_FILE"
    sed -i '' "s/{page_name}/$ENTITIES_LOWER/g" "$PAGE_FILE"
    sed -i '' "s/{頁面標題}/$CHINESE_NAME/g" "$PAGE_FILE"
    echo -e "${GREEN}✓ 已建立：$PAGE_FILE${NC}"
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}完成！${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${YELLOW}接下來需要手動完成：${NC}"
echo "1. 在 class-api.php 中註冊新的 API"
echo "2. 在路由中添加新頁面"
echo "3. 建立資料表（如需要）"
echo ""
echo "詳細說明請參考："
echo "  docs/development/REFACTORING-GUIDE.md"
