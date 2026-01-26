# BuyGo+1 設計系統

> **目的**：統一所有頁面的視覺風格、顏色、間距、字體、表格樣式
> **原則**：設計與程式碼分離，所有設計 token 集中管理

---

## 📁 資料夾結構

```
design-system/
├── README.md                    # 本檔案 - 設計系統總覽
├── MASTER.md                    # 來自 ui-ux-pro-max 的主設計系統
├── tokens/                      # 設計 Token (顏色、間距、字體)
│   ├── colors.css              # 顏色系統
│   ├── spacing.css             # 間距系統
│   ├── typography.css          # 字體系統
│   └── shadows.css             # 陰影系統
├── components/                  # 共用 UI 元件樣式
│   ├── buttons.css             # 按鈕樣式
│   ├── tables.css              # 表格樣式
│   ├── cards.css               # 卡片樣式
│   ├── forms.css               # 表單樣式
│   └── badges.css              # 徽章樣式
└── pages/                       # 頁面特定覆寫 (非必要)
    ├── products.md             # 商品頁特定設計
    └── orders.md               # 訂單頁特定設計
```

---

## 🎨 設計 Token

### 顏色系統 (來自 ui-ux-pro-max)

| 角色 | Hex | CSS Variable | 使用場景 |
|------|-----|--------------|----------|
| **Primary** | `#7C3AED` | `--color-primary` | 主要按鈕、連結、強調 |
| **Secondary** | `#A78BFA` | `--color-secondary` | 次要按鈕、輔助資訊 |
| **CTA** | `#F97316` | `--color-cta` | 行動呼籲按鈕 |
| **Background** | `#FAF5FF` | `--color-background` | 頁面背景 |
| **Surface** | `#FFFFFF` | `--color-surface` | 卡片、表格背景 |
| **Text** | `#4C1D95` | `--color-text` | 主要文字 |
| **Text Muted** | `#6B7280` | `--color-text-muted` | 次要文字 |
| **Border** | `#E5E7EB` | `--color-border` | 邊框 |
| **Success** | `#10B981` | `--color-success` | 成功狀態 |
| **Warning** | `#F59E0B` | `--color-warning` | 警告狀態 |
| **Error** | `#EF4444` | `--color-error` | 錯誤狀態 |

### 字體系統 (來自 ui-ux-pro-max)

| 類型 | 字體 | CSS Variable | 使用場景 |
|------|------|--------------|----------|
| **標題** | Fira Code | `--font-heading` | H1-H6, 重要標題 |
| **內文** | Fira Sans | `--font-body` | 正文、段落 |
| **等寬** | Fira Code | `--font-mono` | 代碼、數據 |

**Google Fonts 載入**:
```css
@import url('https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&display=swap');
```

### 間距系統 (基於 Tailwind 8px 基準)

| Token | 值 | CSS Variable | 使用場景 |
|-------|-----|--------------|----------|
| `xs` | `4px` | `--spacing-xs` | 緊密間距 |
| `sm` | `8px` | `--spacing-sm` | 小間距 |
| `md` | `16px` | `--spacing-md` | 中間距 (預設) |
| `lg` | `24px` | `--spacing-lg` | 大間距 |
| `xl` | `32px` | `--spacing-xl` | 特大間距 |
| `2xl` | `48px` | `--spacing-2xl` | 區塊間距 |

### 陰影系統

| Token | 值 | CSS Variable | 使用場景 |
|-------|-----|--------------|----------|
| `shadow-sm` | `0 1px 2px 0 rgba(0, 0, 0, 0.05)` | `--shadow-sm` | 微陰影 |
| `shadow-md` | `0 4px 6px -1px rgba(0, 0, 0, 0.1)` | `--shadow-md` | 中陰影 |
| `shadow-lg` | `0 10px 15px -3px rgba(0, 0, 0, 0.1)` | `--shadow-lg` | 大陰影 |

---

## 🧩 共用元件

### 表格樣式 (統一所有頁面)

**設計規範**:
- 表頭背景: `--color-background` (#FAF5FF)
- 表頭文字: 粗體 (font-weight: 600)
- 行高亮: hover 時背景 `--color-secondary/10`
- 邊框: `--color-border` (#E5E7EB)
- 間距: padding `12px 16px`

### 按鈕樣式 (3 種變體)

1. **Primary** - 主要按鈕 (背景: `--color-primary`)
2. **Secondary** - 次要按鈕 (背景: `--color-secondary`)
3. **CTA** - 行動呼籲 (背景: `--color-cta`)

### 卡片樣式

- 背景: `--color-surface`
- 邊框: `--color-border`
- 陰影: `--shadow-md`
- 圓角: `8px`

---

## 📖 使用方式

### 1. 在 PHP 頁面中載入設計系統

```php
<!-- 載入設計 tokens -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../design-system/tokens/colors.css', __FILE__)); ?>" />
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../design-system/tokens/spacing.css', __FILE__)); ?>" />
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../design-system/tokens/typography.css', __FILE__)); ?>" />
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../design-system/tokens/shadows.css', __FILE__)); ?>" />

<!-- 載入共用元件樣式 -->
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../design-system/components/tables.css', __FILE__)); ?>" />
<link rel="stylesheet" href="<?php echo esc_url(plugins_url('../design-system/components/buttons.css', __FILE__)); ?>" />
```

### 2. 使用設計 Token

**❌ 舊方式** (每個頁面自己定義):
```css
.button {
    background-color: #2563EB; /* 硬編碼顏色 */
    padding: 12px 24px; /* 硬編碼間距 */
}
```

**✅ 新方式** (使用設計 token):
```css
.button {
    background-color: var(--color-primary);
    padding: var(--spacing-md) var(--spacing-lg);
}
```

### 3. 頁面特定樣式覆寫

如果某個頁面需要特殊設計,在 `pages/` 資料夾建立 Markdown 文件記錄,然後建立對應的 CSS 檔案。

---

## 🎯 遷移計畫

### Phase 1: 建立設計 Token ✅
- [x] 建立 `tokens/colors.css`
- [x] 建立 `tokens/spacing.css`
- [x] 建立 `tokens/typography.css`
- [x] 建立 `tokens/shadows.css`

### Phase 2: 建立共用元件樣式
- [ ] 建立 `components/tables.css`
- [ ] 建立 `components/buttons.css`
- [ ] 建立 `components/cards.css`
- [ ] 建立 `components/forms.css`
- [ ] 建立 `components/badges.css`

### Phase 3: 遷移現有頁面
- [ ] 更新 `products.php` 使用設計系統
- [ ] 更新 `orders.php` 使用設計系統
- [ ] 更新 `customers.php` 使用設計系統
- [ ] 更新 `shipment-details.php` 使用設計系統
- [ ] 更新 `shipment-products.php` 使用設計系統

### Phase 4: 清理舊 CSS
- [ ] 刪除 `admin/css/products.css` 中重複的樣式
- [ ] 刪除 `admin/css/orders.css` 中重複的樣式
- [ ] 保留頁面特定的樣式

---

## 📚 參考資源

- **MASTER.md** - ui-ux-pro-max 生成的主設計系統
- **Tailwind CSS** - 間距和顏色參考: https://tailwindcss.com/docs
- **Fira Fonts** - Google Fonts: https://fonts.google.com/share?selection.family=Fira+Code:wght@400;500;600;700|Fira+Sans:wght@300;400;500;600;700

---

**最後更新**: 2026-01-27
**維護者**: Development Team
