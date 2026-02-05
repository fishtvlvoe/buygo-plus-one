#!/bin/bash

# 外掛打包腳本
# 用途：打包外掛供 GitHub Releases 或其他更新伺服器使用

set -e

# 顏色輸出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 取得版本號
VERSION=$(grep "Version:" buygo-plus-one.php | sed 's/.*Version: *//' | tr -d ' ')

if [ -z "$VERSION" ]; then
    echo -e "${RED}錯誤：無法從 buygo-plus-one.php 取得版本號${NC}"
    exit 1
fi

echo -e "${GREEN}開始打包外掛 v${VERSION}...${NC}"

# 建立暫存目錄
TEMP_DIR=$(mktemp -d)
PLUGIN_DIR="${TEMP_DIR}/buygo-plus-one"

# 複製檔案（排除不需要的）
echo "複製檔案..."
mkdir -p "${PLUGIN_DIR}"

rsync -av \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.gitattributes' \
    --exclude='.DS_Store' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='coverage' \
    --exclude='*.zip' \
    --exclude='tests' \
    --exclude='phpunit-unit.xml' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='release.sh' \
    --exclude='bump-version.sh' \
    --exclude='build-release.sh' \
    --exclude='.planning' \
    --exclude='BUILD-REPORT.md' \
    --exclude='RELEASE-GUIDE.md' \
    --exclude='GITHUB-SETUP.md' \
    ./ "${PLUGIN_DIR}/"

# 建立 zip 檔案
ZIP_NAME="buygo-plus-one.zip"
ZIP_PATH="$(pwd)/${ZIP_NAME}"

echo "建立 zip 檔案..."
cd "${TEMP_DIR}"
zip -r "${ZIP_PATH}" buygo-plus-one -q

# 清理
rm -rf "${TEMP_DIR}"

# 顯示結果
FILE_SIZE=$(du -h "${ZIP_PATH}" | cut -f1)
echo -e "${GREEN}✓ 打包完成！${NC}"
echo -e "檔案：${ZIP_NAME}"
echo -e "大小：${FILE_SIZE}"
echo -e "路徑：${ZIP_PATH}"
echo ""
echo -e "${YELLOW}下一步：${NC}"
echo "1. 在 GitHub 建立新的 Release（tag: v${VERSION}）"
echo "2. 上傳 ${ZIP_NAME} 作為 Release Asset"
echo "3. 外掛更新機制會自動使用這個 ZIP（資料夾名稱固定為 buygo-plus-one）"
