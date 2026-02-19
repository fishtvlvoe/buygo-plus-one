#!/bin/bash

# BuyGo Plus One - 正式版打包腳本
# 排除開發文件和測試文件

set -e

PLUGIN_NAME="buygo-plus-one"
VERSION="0.2.9"
BUILD_DIR="/tmp/${PLUGIN_NAME}-build"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"
DESKTOP="$HOME/Desktop"

echo "🚀 開始打包 ${PLUGIN_NAME} v${VERSION}..."

# 清理舊的建置目錄
if [ -d "$BUILD_DIR" ]; then
    rm -rf "$BUILD_DIR"
fi

# 建立建置目錄
mkdir -p "$BUILD_DIR/$PLUGIN_NAME"

echo "📦 複製檔案（排除開發文件）..."

# 使用 rsync 複製檔案，排除不需要的文件
rsync -av \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.planning' \
    --exclude='tests' \
    --exclude='phpunit*.xml*' \
    --exclude='.phpunit.result.cache' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='vendor' \
    --exclude='.vscode' \
    --exclude='.claude' \
    --exclude='node_modules' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='*.md' \
    --exclude='build-release.sh' \
    --exclude='release.sh' \
    --exclude='.DS_Store' \
    --exclude='*.log' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='bin' \
    --exclude='/check-*.php' \
    --exclude='/debug-*.php' \
    --exclude='/test-*.php' \
    --exclude='/verify-*.php' \
    --exclude='/simple-debug.php' \
    --exclude='.zipignore' \
    --exclude='.github' \
    ./ "$BUILD_DIR/$PLUGIN_NAME/"

echo "🗜️  壓縮成 ZIP 檔案..."

# 切換到建置目錄並壓縮
cd "$BUILD_DIR"
zip -rq "$ZIP_NAME" "$PLUGIN_NAME"

echo "📂 移動到桌面..."

# 移動到桌面
mv "$ZIP_NAME" "$DESKTOP/"

# 清理建置目錄
rm -rf "$BUILD_DIR"

echo "✅ 完成！"
echo "📍 檔案位置: $DESKTOP/$ZIP_NAME"
echo "📊 檔案大小: $(du -h "$DESKTOP/$ZIP_NAME" | cut -f1)"
