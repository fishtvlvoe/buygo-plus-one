#!/bin/bash

# 版本號自動更新腳本
# 用途：根據改動類型自動更新版本號並建立 tag

set -e

# 顏色輸出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 取得目前版本號
CURRENT_VERSION=$(grep "Version:" buygo-plus-one.php | sed 's/.*Version: *//' | tr -d ' ')

if [ -z "$CURRENT_VERSION" ]; then
    echo -e "${RED}錯誤：無法從 buygo-plus-one.php 取得版本號${NC}"
    exit 1
fi

echo -e "${BLUE}目前版本：v${CURRENT_VERSION}${NC}"
echo ""

# 解析版本號
IFS='.' read -r -a VERSION_PARTS <<< "$CURRENT_VERSION"
MAJOR=${VERSION_PARTS[0]}
MINOR=${VERSION_PARTS[1]}
PATCH=${VERSION_PARTS[2]}

# 顯示選單
echo -e "${YELLOW}請選擇更新類型：${NC}"
echo ""
echo "1) 修訂號 +1 (Patch) - 只修 bug、小修正"
echo "   ${CURRENT_VERSION} → $MAJOR.$MINOR.$((PATCH + 1))"
echo ""
echo "2) 次版本號 +1 (Minor) - 新增功能"
echo "   ${CURRENT_VERSION} → $MAJOR.$((MINOR + 1)).0"
echo ""
echo "3) 主版本號 +1 (Major) - 重大變更、不相容"
echo "   ${CURRENT_VERSION} → $((MAJOR + 1)).0.0"
echo ""
echo "4) 自訂版本號"
echo ""
read -p "請選擇 (1-4): " choice

case $choice in
    1)
        NEW_VERSION="$MAJOR.$MINOR.$((PATCH + 1))"
        UPDATE_TYPE="patch"
        ;;
    2)
        NEW_VERSION="$MAJOR.$((MINOR + 1)).0"
        UPDATE_TYPE="minor"
        ;;
    3)
        NEW_VERSION="$((MAJOR + 1)).0.0"
        UPDATE_TYPE="major"
        ;;
    4)
        read -p "請輸入新版本號 (例如: 0.2.1): " NEW_VERSION
        if [[ ! $NEW_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo -e "${RED}錯誤：版本號格式不正確，請使用 主版本號.次版本號.修訂號 格式${NC}"
            exit 1
        fi
        UPDATE_TYPE="custom"
        ;;
    *)
        echo -e "${RED}無效的選擇${NC}"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}準備更新版本號：${CURRENT_VERSION} → ${NEW_VERSION}${NC}"
read -p "確認繼續？(y/N): " confirm

if [[ ! $confirm =~ ^[Yy]$ ]]; then
    echo "已取消"
    exit 0
fi

# 更新版本號
echo ""
echo -e "${BLUE}更新版本號...${NC}"

# 更新 buygo-plus-one.php 中的版本號
sed -i '' "s/Version: ${CURRENT_VERSION}/Version: ${NEW_VERSION}/" buygo-plus-one.php
sed -i '' "s/BUYGO_PLUS_ONE_VERSION', '${CURRENT_VERSION}'/BUYGO_PLUS_ONE_VERSION', '${NEW_VERSION}'/" buygo-plus-one.php

echo -e "${GREEN}✓ 版本號已更新${NC}"

# 顯示變更
echo ""
echo -e "${YELLOW}變更內容：${NC}"
git diff buygo-plus-one.php | grep -A 2 -B 2 "Version" || true

# 詢問是否要 commit 和 push
echo ""
read -p "是否要 commit 並 push 到 GitHub？(y/N): " push_confirm

if [[ $push_confirm =~ ^[Yy]$ ]]; then
    # Commit
    echo ""
    echo -e "${BLUE}建立 commit...${NC}"
    git add buygo-plus-one.php
    git commit -m "Bump version to ${NEW_VERSION}"
    echo -e "${GREEN}✓ Commit 完成${NC}"

    # Push
    echo ""
    echo -e "${BLUE}推送到 GitHub...${NC}"
    git push
    echo -e "${GREEN}✓ Push 完成${NC}"

    # 建立 tag
    echo ""
    read -p "是否要建立 tag v${NEW_VERSION} 並推送到 GitHub？(y/N): " tag_confirm

    if [[ $tag_confirm =~ ^[Yy]$ ]]; then
        echo ""
        echo -e "${BLUE}建立 tag...${NC}"
        git tag "v${NEW_VERSION}"
        git push origin "v${NEW_VERSION}"
        echo -e "${GREEN}✓ Tag v${NEW_VERSION} 已建立並推送${NC}"
        echo ""
        echo -e "${YELLOW}下一步：${NC}"
        echo "1. 前往 https://github.com/fishtvlvoe/buygo-plus-one/releases"
        echo "2. 點擊「Create a new release」"
        echo "3. 選擇 tag: v${NEW_VERSION}"
        echo "4. 填寫 Release notes"
        echo "5. 點擊「Publish release」"
    fi
else
    echo ""
    echo -e "${YELLOW}版本號已更新，但尚未 commit${NC}"
    echo "你可以手動執行："
    echo "  git add buygo-plus-one.php"
    echo "  git commit -m \"Bump version to ${NEW_VERSION}\""
    echo "  git push"
    echo "  git tag v${NEW_VERSION}"
    echo "  git push origin v${NEW_VERSION}"
fi

echo ""
echo -e "${GREEN}完成！${NC}"
