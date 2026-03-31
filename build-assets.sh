#!/usr/bin/env bash
# build-assets.sh — 合併 CSS / JS 靜態資源，讓瀏覽器可以快取
# 執行：bash build-assets.sh（在專案根目錄）

set -e

# 取得腳本所在目錄（允許從任意路徑呼叫）
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST="$SCRIPT_DIR/dist"

mkdir -p "$DIST"

echo "=== 合併 CSS → dist/design-system.css ==="

# 12 個 Design System CSS（順序對應 template.php include 順序）
CSS_FILES=(
    "design-system/tokens/colors.css"
    "design-system/tokens/spacing.css"
    "design-system/tokens/typography.css"
    "design-system/tokens/effects.css"
    "design-system/components/header.css"
    "design-system/components/smart-search-box.css"
    "design-system/components/table.css"
    "design-system/components/card.css"
    "design-system/components/button.css"
    "design-system/components/form.css"
    "design-system/components/status-tag.css"
    "design-system/components/pagination.css"
)

# 清空目標檔
> "$DIST/design-system.css"

for f in "${CSS_FILES[@]}"; do
    path="$SCRIPT_DIR/$f"
    if [ ! -f "$path" ]; then
        echo "  [錯誤] 找不到：$f" >&2
        exit 1
    fi
    echo "/* === $f === */" >> "$DIST/design-system.css"
    cat "$path" >> "$DIST/design-system.css"
    echo "" >> "$DIST/design-system.css"
done

# 末尾附加 template.php <style> 區塊內的額外 CSS（全站基礎 reset + Skeleton Loading + SPA Transition）
cat >> "$DIST/design-system.css" << 'EOF'
/* === template.php 額外全站 CSS === */
* { box-sizing: border-box; }
a { text-decoration: none; }
button { font-family: inherit; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border-width: 0; }
.hidden-mobile { display: none; }
@media (min-width: 768px) { .hidden-mobile { display: block; } }
.hidden-desktop { display: block; }
@media (min-width: 768px) { .hidden-desktop { display: none; } }
/* Skeleton Loading */
.buygo-skeleton { display: flex; min-height: 100vh; background: #f8fafc; }
.buygo-skeleton-sidebar { width: 12rem; background: #fff; border-right: 1px solid #e2e8f0; padding: 1.5rem 1rem; }
.buygo-skeleton-logo { height: 2rem; width: 6rem; background: #e2e8f0; border-radius: 0.5rem; margin-bottom: 2rem; }
.buygo-skeleton-menu-item { height: 2.5rem; background: #f1f5f9; border-radius: 0.5rem; margin-bottom: 0.5rem; }
.buygo-skeleton-menu-item.active { background: #dbeafe; }
.buygo-skeleton-content { flex: 1; padding: 1.5rem; }
.buygo-skeleton-header { height: 2rem; width: 12rem; background: #e2e8f0; border-radius: 0.5rem; margin-bottom: 1.5rem; }
.buygo-skeleton-table { background: #fff; border-radius: 0.75rem; padding: 1.5rem; }
.buygo-skeleton-row { height: 3rem; background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; border-radius: 0.5rem; margin-bottom: 0.75rem; animation: buygo-shimmer 1.5s infinite; }
@keyframes buygo-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
@media (max-width: 768px) { .buygo-skeleton-sidebar { display: none; } .buygo-skeleton-content { padding: 1rem; } }
/* SPA Page Transition */
.buygo-page-enter { opacity: 0; }
.buygo-page-loaded { opacity: 1; transition: opacity 0.15s ease-in; }
/* Page Content Skeleton（SPA 切換時各頁面的 loading 狀態） */
.buygo-content-skeleton {
    animation: buygo-shimmer 1.5s infinite;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    border-radius: 0.5rem;
}
EOF

echo "  完成：$DIST/design-system.css"

echo ""
echo "=== 合併 JS → dist/app.js ==="

# 14 個 JS 檔案（順序對應依賴關係：core → composables）
JS_FILES=(
    "admin/js/RouterMixin.js"
    "admin/js/DesignSystem.js"
    "admin/js/BuyGoCache.js"
    "includes/views/composables/useRouter.js"
    "components/shared/header-component.js"
    "includes/views/composables/useCurrency.js"
    "includes/views/composables/useApi.js"
    "includes/views/composables/usePermissions.js"
    "includes/views/composables/useDataLoader.js"
    "includes/views/composables/useOrders.js"
    "includes/views/composables/useProducts.js"
    "includes/views/composables/useShipmentProducts.js"
    "includes/views/composables/useShipmentDetails.js"
    "includes/views/composables/useBatchCreate.js"
)

# 清空目標檔
> "$DIST/app.js"

for f in "${JS_FILES[@]}"; do
    path="$SCRIPT_DIR/$f"
    if [ ! -f "$path" ]; then
        echo "  [錯誤] 找不到：$f" >&2
        exit 1
    fi
    echo "/* === $f === */" >> "$DIST/app.js"
    cat "$path" >> "$DIST/app.js"
    # 檔案間加分號換行，避免因缺少分號導致的合併錯誤
    printf ';\n' >> "$DIST/app.js"
done

echo "  完成：$DIST/app.js"

echo ""
echo "=== 完成 ==="
echo "  CSS：$(wc -c < "$DIST/design-system.css") bytes"
echo "  JS ：$(wc -c < "$DIST/app.js") bytes"
