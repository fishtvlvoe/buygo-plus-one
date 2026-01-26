#!/bin/bash

# 完整自動化發布腳本（使用 GitHub API，不需要 GitHub CLI）
# 用途：自動更新版本號、push、建立 tag、建立 GitHub Release

set -e

# 顏色輸出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 檢查是否有 GitHub token
GITHUB_TOKEN="${GITHUB_TOKEN:-}"
if [ -z "$GITHUB_TOKEN" ]; then
    echo -e "${YELLOW}未設定 GITHUB_TOKEN${NC}"
    echo ""
    echo "為了自動建立 Release，需要 GitHub Personal Access Token"
    echo ""
    echo "建立方式："
    echo "1. 前往：https://github.com/settings/tokens"
    echo "2. 點擊「Generate new token (classic)」"
    echo "3. 勾選「repo」權限"
    echo "4. 複製 token"
    echo ""
    echo "然後執行："
    echo "  export GITHUB_TOKEN='你的token'"
    echo "  或加入 ~/.zshrc: echo \"export GITHUB_TOKEN='你的token'\" >> ~/.zshrc"
    echo ""
    read -p "是否要繼續（只 push，不建立 Release）？(y/N): " continue_without_release
    
    if [[ ! $continue_without_release =~ ^[Yy]$ ]]; then
        echo "已取消"
        exit 0
    fi
    
    SKIP_RELEASE=true
else
    SKIP_RELEASE=false
fi

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
echo -e "${GREEN}準備發布版本：v${NEW_VERSION}${NC}"
echo ""

# 詢問 Release notes
echo -e "${YELLOW}請輸入 Release notes（可選，按 Enter 使用預設）：${NC}"
read -r RELEASE_NOTES

if [ -z "$RELEASE_NOTES" ]; then
    RELEASE_NOTES="Release v${NEW_VERSION}"
fi

# 確認
read -p "確認發布？(y/N): " confirm

if [[ ! $confirm =~ ^[Yy]$ ]]; then
    echo "已取消"
    exit 0
fi

# 更新版本號
echo ""
echo -e "${BLUE}更新版本號...${NC}"
sed -i '' "s/Version: ${CURRENT_VERSION}/Version: ${NEW_VERSION}/" buygo-plus-one.php
sed -i '' "s/BUYGO_PLUS_ONE_VERSION', '${CURRENT_VERSION}'/BUYGO_PLUS_ONE_VERSION', '${NEW_VERSION}'/" buygo-plus-one.php
echo -e "${GREEN}✓ 版本號已更新${NC}"

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
echo -e "${BLUE}建立 tag...${NC}"
git tag "v${NEW_VERSION}"
git push origin "v${NEW_VERSION}"
echo -e "${GREEN}✓ Tag v${NEW_VERSION} 已建立並推送${NC}"

# 建立 Release
if [ "$SKIP_RELEASE" = false ]; then
    echo ""
    echo -e "${BLUE}建立 GitHub Release...${NC}"
    
    # 準備 JSON payload
    RELEASE_JSON=$(cat <<EOF
{
  "tag_name": "v${NEW_VERSION}",
  "name": "v${NEW_VERSION}",
  "body": "${RELEASE_NOTES//$'\n'/\\n}",
  "draft": false,
  "prerelease": false
}
EOF
)
    
    # 使用 curl 建立 Release
    if command -v curl &> /dev/null; then
        RESPONSE=$(curl -s -w "\n%{http_code}" \
            -X POST \
            -H "Accept: application/vnd.github.v3+json" \
            -H "Authorization: token ${GITHUB_TOKEN}" \
            -H "Content-Type: application/json" \
            -d "$RELEASE_JSON" \
            "https://api.github.com/repos/fishtvlvoe/buygo-plus-one/releases")
        
        HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
        BODY=$(echo "$RESPONSE" | sed '$d')
        
        if [ "$HTTP_CODE" = "201" ]; then
            echo -e "${GREEN}✓ Release 已建立${NC}"
            echo ""
            echo -e "${GREEN}發布完成！${NC}"
            echo ""
            RELEASE_URL=$(echo "$BODY" | grep -o '"html_url": "[^"]*"' | cut -d'"' -f4)
            if [ -n "$RELEASE_URL" ]; then
                echo "Release 連結："
                echo "$RELEASE_URL"
            fi
        else
            echo -e "${RED}✗ Release 建立失敗 (HTTP $HTTP_CODE)${NC}"
            echo "回應：$BODY"
            echo ""
            echo "請手動前往 GitHub 建立 Release："
            echo "https://github.com/fishtvlvoe/buygo-plus-one/releases/new"
        fi
    else
        echo -e "${RED}錯誤：需要 curl 才能自動建立 Release${NC}"
        echo "請手動前往 GitHub 建立 Release："
        echo "https://github.com/fishtvlvoe/buygo-plus-one/releases/new"
    fi
else
    echo ""
    echo -e "${YELLOW}跳過 Release 建立（未設定 GITHUB_TOKEN）${NC}"
    echo ""
    echo "請手動前往 GitHub 建立 Release："
    echo "https://github.com/fishtvlvoe/buygo-plus-one/releases/new"
    echo "選擇 tag: v${NEW_VERSION}"
fi

echo ""
echo -e "${GREEN}完成！${NC}"
