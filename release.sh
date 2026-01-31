#!/bin/bash

# BuyGo+1 發布腳本
# 此腳本會建立 git tag 並推送到 GitHub，觸發自動發布流程

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== BuyGo+1 發布腳本 ===${NC}"

# 檢查是否有未提交的變更
if [[ -n $(git status -s) ]]; then
    echo -e "${RED}錯誤：有未提交的變更，請先提交所有變更${NC}"
    git status -s
    exit 1
fi

# 獲取當前分支
CURRENT_BRANCH=$(git branch --show-current)
if [[ "$CURRENT_BRANCH" != "main" ]]; then
    echo -e "${YELLOW}警告：當前不在 main 分支，而是在 $CURRENT_BRANCH${NC}"
    read -p "是否繼續？(y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# 從外掛主檔案讀取版本號
VERSION=$(grep "Version:" buygo-plus-one.php | awk '{print $3}')

if [[ -z "$VERSION" ]]; then
    echo -e "${RED}錯誤：無法從 buygo-plus-one.php 讀取版本號${NC}"
    exit 1
fi

echo -e "${GREEN}準備發布版本: v${VERSION}${NC}"

# 確認是否繼續
read -p "確定要發布 v${VERSION} 嗎？(y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}已取消發布${NC}"
    exit 0
fi

# 檢查 tag 是否已存在
if git rev-parse "v${VERSION}" >/dev/null 2>&1; then
    echo -e "${RED}錯誤：Tag v${VERSION} 已存在${NC}"
    read -p "是否刪除舊 tag 並重新建立？(y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git tag -d "v${VERSION}"
        git push origin ":refs/tags/v${VERSION}" 2>/dev/null || true
    else
        exit 1
    fi
fi

# 建立 tag
echo -e "${GREEN}建立 tag v${VERSION}...${NC}"
git tag -a "v${VERSION}" -m "Release v${VERSION}"

# 推送 tag
echo -e "${GREEN}推送 tag 到 GitHub...${NC}"
git push origin "v${VERSION}"

echo -e "${GREEN}=== 發布完成 ===${NC}"
echo -e "Tag v${VERSION} 已推送到 GitHub"
echo -e "GitHub Actions 將自動建立 Release 和上傳 ZIP 檔案"
echo -e "請訪問 https://github.com/fishtvlvoe/buygo-plus-one/releases 查看進度"
