#!/bin/bash

# ============================================
# BuyGo+1 生產版本打包腳本
# ============================================
#
# 用途：建立可上架到雲端主機的外掛壓縮檔
#
# 功能：
# 1. 排除開發檔案（docs, tests, scripts 等）
# 2. 排除版本控制檔案（.git, .gitignore 等）
# 3. 排除開發依賴（composer.json, phpunit 等）
# 4. 保留所有必要的功能檔案
#

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 專案根目錄
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$PROJECT_DIR/build"
PLUGIN_NAME="buygo-plus-one"
VERSION="0.03"
OUTPUT_FILE="$PROJECT_DIR/${PLUGIN_NAME}-${VERSION}.zip"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}BuyGo+1 生產版本打包${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "版本：$VERSION"
echo "來源：$PROJECT_DIR"
echo "輸出：$OUTPUT_FILE"
echo ""

# 清理舊的建置
if [ -d "$BUILD_DIR" ]; then
    echo -e "${YELLOW}清理舊的建置目錄...${NC}"
    rm -rf "$BUILD_DIR"
fi

# 建立建置目錄
mkdir -p "$BUILD_DIR/$PLUGIN_NAME"

echo -e "${YELLOW}複製檔案到建置目錄...${NC}"

# 使用 rsync 複製檔案，排除不需要的檔案和目錄
rsync -av \
    --exclude='.git/' \
    --exclude='.gitignore' \
    --exclude='.gitattributes' \
    --exclude='.phpunit.result.cache' \
    --exclude='.claude/' \
    --exclude='.vscode/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='build/' \
    --exclude='tests/' \
    --exclude='bin/' \
    --exclude='docs/' \
    --exclude='scripts/' \
    --exclude='templates/' \
    --exclude='*.log' \
    --exclude='.DS_Store' \
    --exclude='phpunit.xml.dist' \
    --exclude='phpunit-unit.xml' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='CLAUDE.md' \
    --exclude='CHANGELOG.md' \
    --exclude='CUMULATIVE_BUG_FIX.md' \
    --exclude='HANDOFF-NOTES.md' \
    --exclude='test-*.php' \
    --exclude='*.zip' \
    "$PROJECT_DIR/" "$BUILD_DIR/$PLUGIN_NAME/"

echo ""
echo -e "${YELLOW}建立壓縮檔...${NC}"

# 切換到建置目錄並建立 zip
cd "$BUILD_DIR"
zip -r "$OUTPUT_FILE" "$PLUGIN_NAME" -q

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}打包完成！${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${GREEN}輸出檔案：${NC}"
echo "  $OUTPUT_FILE"
echo ""

# 顯示檔案大小
FILE_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
echo -e "${GREEN}檔案大小：${NC}$FILE_SIZE"
echo ""

# 清理建置目錄
echo -e "${YELLOW}清理建置目錄...${NC}"
rm -rf "$BUILD_DIR"

echo ""
echo -e "${GREEN}✓ 完成！${NC}"
echo ""
echo "下一步："
echo "1. 上傳 $OUTPUT_FILE 到雲端主機"
echo "2. 在 WordPress 外掛管理中停用舊版外掛"
echo "3. 上傳並啟用新版外掛"
echo ""
