# Phase 11: 按鈕與狀態標籤遷移 - Research

**Researched:** 2026-01-28
**Domain:** CSS Design System Migration - Button & Status Tag Components
**Confidence:** HIGH

## Summary

Phase 11 focuses on migrating button and status tag elements from legacy CSS (Tailwind inline styles and `buygo-btn-*` classes) to the new design system classes (`.btn` and `.status-tag`). This research examines the existing design system implementation, current button/tag usage patterns across 5 pages, Vue.js integration requirements, and responsive design considerations.

The standard approach is straightforward class replacement following the design system's semantic naming conventions. The key challenge is preserving Vue.js interactivity (directives like `@click`, `:class`, `v-if`) while ensuring visual consistency across desktop and mobile breakpoints.

Critical finding: The design system is already complete and loaded globally via WordPress, but missing the `.btn-danger` class needed for delete operations. All other required classes exist.

**Primary recommendation:** Perform systematic class replacement page-by-page, add missing `.btn-danger` class to design system, preserve all Vue directives and existing functionality, and use the special icon-based design for products.php allocation buttons as specified in CONTEXT.md.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| CSS Variables (Custom Properties) | Native | Design tokens for colors, spacing, typography | Industry standard for design system implementation, enables runtime theming |
| Media Queries | Native | Responsive breakpoint at 768px | Standard desktop/mobile split, matches existing system |
| Vue.js 3 | 3.x | Frontend interactivity (directives: @click, v-if, :class) | Already integrated in project, lightweight reactivity |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Tailwind CSS | 3.x | Legacy inline utilities (being phased out) | Only for non-migrated sections |
| DesignSystem.js | Current | Legacy `buygo-btn-*` classes (backward compatibility) | Transition period only |

### Current Design System Structure

```
design-system/
├── index.css                    # Main entry (loaded globally in WordPress)
├── tokens/
│   ├── colors.css              # --color-primary, --color-success, etc.
│   ├── spacing.css             # Responsive spacing tokens
│   ├── typography.css          # Font sizes and weights
│   └── effects.css             # Transitions, shadows, focus rings
└── components/
    ├── button.css              # .btn, .btn-primary, .btn-secondary (missing .btn-danger)
    ├── status-tag.css          # .status-tag, .status-tag-success/error/warning/info/neutral
    ├── header.css              # Already migrated in Phase 3
    ├── table.css               # Already migrated in Phase 10
    └── card.css                # Already migrated in Phase 10
```

**Installation:**
Already installed. Design system loaded globally via:
```php
// admin/class-admin.php (existing)
wp_enqueue_style('buygo-design-system', plugins_url('design-system/index.css', ...));
```

## Architecture Patterns

### Recommended Migration Structure

```
Phase 11 Migration/
├── Wave 1: Design System補充
│   └── Add .btn-danger to button.css
├── Wave 2: 頁面遷移 (5 plans, parallelizable)
│   ├── shipment-products.php (SP-04)
│   ├── shipment-details.php (SD-04)
│   ├── orders.php (ORD-04)
│   ├── products.php (PROD-03) - special allocation icon
│   └── settings.php (SET-02)
└── Wave 3: 驗證
    └── Functional testing across all pages
```

### Pattern 1: Simple Button Migration

**What:** Replace Tailwind/legacy classes with design system classes
**When to use:** Standard buttons without special styling requirements

**Before (Tailwind/Legacy):**
```html
<button class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
    查看詳情
</button>
<!-- OR -->
<button class="buygo-btn buygo-btn-primary">查看詳情</button>
```

**After (Design System):**
```html
<button class="btn btn-primary">查看詳情</button>
```

**Vue.js Preservation:**
```html
<!-- CRITICAL: Preserve all Vue directives -->
<button
    @click="handleClick"
    :disabled="loading"
    :class="['btn', isPrimary ? 'btn-primary' : 'btn-secondary']"
    v-if="showButton">
    {{ buttonText }}
</button>
```

### Pattern 2: Status Tag Migration

**What:** Convert status indicators from buttons/spans to semantic status tags
**When to use:** All status displays (order status, product status, shipment status)

**Before:**
```html
<!-- Current pattern: Using button or inline Tailwind -->
<button class="px-2.5 py-1 text-xs font-semibold rounded-full border bg-green-100 text-green-800">
    已上架
</button>
```

**After:**
```html
<!-- MUST use <span>, not <button> - status tags are not interactive -->
<span class="status-tag status-tag-success">已上架</span>
```

**Color Semantics (from CONTEXT.md):**
```html
<span class="status-tag status-tag-success">已完成</span>   <!-- Green: Success states -->
<span class="status-tag status-tag-neutral">未出貨</span>   <!-- Gray: Neutral/initial -->
<span class="status-tag status-tag-info">待處理</span>      <!-- Blue: Needs action -->
<span class="status-tag status-tag-warning">處理中</span>   <!-- Yellow: In progress -->
<span class="status-tag status-tag-danger">已取消</span>    <!-- Red: Cancelled/error -->
```

### Pattern 3: Products Page Allocation Button (Special Case)

**What:** Use stacked icon design instead of standard button
**When to use:** Only for allocation buttons in products.php

**Standard button (DON'T use for allocation):**
```html
<button class="btn btn-primary">分配</button>
```

**Special icon design (DO use):**
```html
<!-- Three stacked mini icons representing allocation -->
<button @click="navigateTo('allocation', product)"
        class="allocation-icon-button"
        title="分配">
    <!-- Custom icon implementation per CONTEXT.md -->
    <svg class="w-5 h-5">...</svg>
</button>
```

Note: This requires custom CSS not in design system. Implementation details in CONTEXT.md.

### Pattern 4: Responsive Button Sizing

**What:** Buttons adapt sizing for mobile/desktop
**When to use:** Buttons in responsive layouts

```html
<!-- Desktop: Standard size, Mobile: Smaller padding -->
<button class="btn btn-primary">
    <span class="hidden md:inline">查看詳細資訊</span>
    <span class="md:hidden">詳情</span>
</button>

<!-- Or use btn-sm for consistently smaller buttons -->
<button class="btn btn-primary btn-sm">編輯</button>
```

### Anti-Patterns to Avoid

- **Mixed styling**: Don't combine Tailwind utilities with design system classes
  ```html
  <!-- ❌ BAD -->
  <button class="btn btn-primary px-8 py-4 shadow-lg">...</button>

  <!-- ✅ GOOD -->
  <button class="btn btn-primary">...</button>
  ```

- **Interactive status tags**: Status tags should NOT be clickable
  ```html
  <!-- ❌ BAD - using button element -->
  <button class="status-tag status-tag-success" @click="toggle">已上架</button>

  <!-- ✅ GOOD - using span, separate button for interaction -->
  <span class="status-tag status-tag-success">已上架</span>
  <button class="btn btn-sm" @click="toggle">切換</button>
  ```

- **Breaking Vue directives**: Always preserve existing Vue functionality
  ```html
  <!-- ❌ BAD - removing Vue directives during migration -->
  <button class="btn btn-primary">刪除</button>

  <!-- ✅ GOOD - preserving all directives -->
  <button @click="deleteProduct(product.id)"
          :disabled="deleting"
          v-if="canDelete"
          class="btn btn-danger">
      刪除
  </button>
  ```

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Button styling variations | Custom CSS for each button type | `.btn .btn-primary/.btn-secondary/.btn-danger` | Design system provides consistent hover states, focus rings, disabled states, and responsive sizing |
| Status color semantics | Inline color styles or Tailwind utilities | `.status-tag .status-tag-{type}` | Semantic naming prevents color inconsistencies, centralizes color management |
| Responsive button sizing | Media queries for each button | Design system's responsive tokens | Automatic sizing based on `--spacing-button-padding-*` tokens |
| Icon buttons | Custom icon + padding combinations | Design system patterns + existing SVGs | Consistent sizing, proper accessibility attributes |
| Loading/disabled states | Custom opacity/pointer-events CSS | `.btn:disabled` (built into design system) | Handles all edge cases (cursor, opacity, pointer events) |

**Key insight:** The design system already handles 90% of button/status-tag variations. Custom solutions would duplicate effort and break design consistency. Only exception: products.php allocation icon per special requirements.

## Common Pitfalls

### Pitfall 1: Destroying Vue Reactivity During Migration

**What goes wrong:** Replacing HTML without checking for Vue directives causes functionality loss
**Why it happens:** Focus on visual migration without testing interactivity
**How to avoid:**
1. Before editing, grep for `@click`, `v-if`, `:class`, `v-for` in target file
2. Keep all Vue directives when replacing classes
3. Test ALL button interactions after migration
**Warning signs:**
- Buttons don't respond to clicks
- Conditional rendering breaks
- Dynamic classes disappear

**Prevention checklist:**
```bash
# Before migrating buttons in any file:
grep -n "@click\|v-if\|:class\|:disabled\|v-for" filename.php

# After migration, verify all found lines still work
```

### Pitfall 2: Missing .btn-danger Class

**What goes wrong:** Delete buttons fail to render with correct danger styling
**Why it happens:** Design system's button.css lacks `.btn-danger` definition
**How to avoid:** Add `.btn-danger` to design-system/components/button.css BEFORE migrating delete buttons
**Warning signs:**
- Delete buttons look like secondary buttons
- No red color on dangerous actions

**Required addition to button.css:**
```css
/* Danger Button */
.btn-danger {
  background-color: var(--color-error);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background-color: var(--color-error-600);
}
```

### Pitfall 3: Status Tags as Buttons

**What goes wrong:** Using `<button>` for status tags makes them appear clickable
**Why it happens:** Existing code uses buttons for status displays
**How to avoid:** Always use `<span>` for status tags, never `<button>`
**Warning signs:**
- Status tags have hover effects
- Cursor changes to pointer over status
- Accidental status clicks

**Migration rule:**
```html
<!-- IF status is INTERACTIVE (changes state on click) -->
<button @click="toggleStatus" class="btn btn-sm">
    切換狀態
</button>

<!-- IF status is DISPLAY ONLY (shows current state) -->
<span class="status-tag status-tag-success">已完成</span>
```

### Pitfall 4: Wrong Status Color Mapping

**What goes wrong:** Using wrong status-tag class for semantic meaning
**Why it happens:** Not following color semantics from CONTEXT.md
**How to avoid:** Reference CONTEXT.md color semantics table
**Warning signs:**
- Green used for "processing" instead of "completed"
- Red used for "pending" instead of "cancelled"

**Correct mapping reference:**
| State Type | Correct Class | Wrong Class |
|------------|---------------|-------------|
| 已完成/已上架/已出貨 | `status-tag-success` (green) | ❌ `status-tag-info` |
| 未出貨/待處理(初始) | `status-tag-neutral` (gray) | ❌ `status-tag-warning` |
| 待處理(需行動) | `status-tag-info` (blue) | ❌ `status-tag-warning` |
| 處理中 | `status-tag-warning` (yellow) | ❌ `status-tag-info` |
| 已取消/失敗 | `status-tag-danger` (red) | ❌ `status-tag-error` |

Note: Design system uses `status-tag-error` but CONTEXT.md specifies `status-tag-danger`. Need to verify which is correct or add both as aliases.

### Pitfall 5: Breaking Responsive Layouts

**What goes wrong:** Button text/sizing doesn't adapt for mobile
**Why it happens:** Not testing at 768px breakpoint
**How to avoid:** Test every migrated page at both >768px and <768px
**Warning signs:**
- Buttons overflow on mobile
- Text truncates incorrectly
- Layout breaks at 768px exactly

**Responsive testing protocol:**
```
1. Desktop (1920px): Full button text visible
2. Tablet (768px): Verify no breaking at exact breakpoint
3. Mobile (375px): Verify btn-sm or shortened text
4. Mobile landscape (667px): Verify still readable
```

## Code Examples

Verified patterns from existing codebase:

### Example 1: Primary Action Button
```html
<!-- Source: shipment-products.php line 104 -->
<!-- BEFORE -->
<button @click="loadShipments" class="buygo-btn buygo-btn-primary mt-4">
    重新載入
</button>

<!-- AFTER -->
<button @click="loadShipments" class="btn btn-primary mt-4">
    重新載入
</button>
```

### Example 2: Secondary Action Button
```html
<!-- Source: shipment-products.php line 423 -->
<!-- BEFORE -->
<button @click="closeModal" class="buygo-btn buygo-btn-secondary">
    取消
</button>

<!-- AFTER -->
<button @click="closeModal" class="btn btn-secondary">
    取消
</button>
```

### Example 3: Danger Button (Delete)
```html
<!-- Source: products.php line 186 -->
<!-- BEFORE -->
<button @click="deleteProduct(product.id)"
        class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition"
        title="刪除">
    <svg>...</svg>
</button>

<!-- AFTER -->
<button @click="deleteProduct(product.id)"
        class="btn btn-danger btn-sm"
        title="刪除">
    <svg>...</svg>
</button>
```

### Example 4: Status Tag with Vue Binding
```html
<!-- Source: products.php line 171 -->
<!-- BEFORE -->
<button @click="toggleStatus(product)"
        :class="product.status === 'published'
            ? 'bg-green-100 text-green-800 border-green-200'
            : 'bg-slate-100 text-slate-800 border-slate-200'"
        class="px-2.5 py-1 text-xs font-semibold rounded-full border">
    {{ product.status === 'published' ? '已上架' : '已下架' }}
</button>

<!-- AFTER (if status is DISPLAY only) -->
<span :class="['status-tag', product.status === 'published'
    ? 'status-tag-success'
    : 'status-tag-neutral']">
    {{ product.status === 'published' ? '已上架' : '已下架' }}
</span>

<!-- AFTER (if status is INTERACTIVE - can toggle on click) -->
<button @click="toggleStatus(product)"
        :class="['btn', 'btn-sm', product.status === 'published'
            ? 'btn-success'
            : 'btn-secondary']">
    {{ product.status === 'published' ? '已上架' : '已下架' }}
</button>
```

Note: Need to verify from CONTEXT.md if product status should remain interactive or become display-only.

### Example 5: Button with Icon
```html
<!-- Source: products.php line 185 -->
<!-- BEFORE -->
<button @click="navigateTo('edit', product)"
        class="p-2 text-slate-500 hover:bg-slate-50 rounded-lg transition"
        title="編輯">
    <svg class="w-5 h-5">...</svg>
</button>

<!-- AFTER -->
<button @click="navigateTo('edit', product)"
        class="btn btn-secondary btn-sm"
        title="編輯">
    <svg class="w-5 h-5">...</svg>
</button>
```

### Example 6: Responsive Button Group
```html
<!-- Source: products.php card actions -->
<!-- BEFORE -->
<button class="py-3 flex items-center justify-center gap-1.5 text-blue-600 hover:bg-blue-50">
    <span class="text-xs font-bold">分配</span>
</button>

<!-- AFTER -->
<button @click="navigateTo('allocation', product)"
        class="btn btn-primary btn-sm">
    <span class="hidden md:inline">分配</span>
    <span class="md:hidden">分配</span>
</button>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Inline Tailwind utilities | Design system semantic classes | Phase 1-3 (Header migration) | Separation of style and structure, easier maintenance |
| `buygo-btn-*` classes in DesignSystem.js | `.btn-*` in design-system/components/button.css | Phase 11 (current) | Migration to pure CSS, removal of JS dependency |
| Status as `<button>` elements | Status as `<span class="status-tag">` | Phase 11 (current) | Proper semantic HTML, no accidental clicks |
| Per-page button styling | Centralized design tokens | Ongoing (Phases 3-11) | Consistent UI, single source of truth |

**Deprecated/outdated:**
- `.buygo-btn-*` classes: Legacy classes in DesignSystem.js, keep for backward compatibility during transition only
- Tailwind utilities on buttons: Being phased out, use design system classes instead
- Status buttons: Should be `<span>` tags for non-interactive status displays

**Current best practices (2026):**
- Use CSS custom properties (variables) for theming
- Semantic class names over utility classes for components
- Responsive design via design tokens, not inline media queries
- Separation of concerns: HTML structure, CSS styling, JS behavior

## Open Questions

Things that couldn't be fully resolved:

1. **Status Tag Interactive vs Display-Only**
   - What we know: CONTEXT.md doesn't clearly specify which status tags should remain interactive
   - What's unclear: products.php line 171 has `@click="toggleStatus"` - should this become a separate button or remain interactive?
   - Recommendation: Review with user during planning - likely need separate toggle button + display-only status tag

2. **Design System Class Naming Inconsistency**
   - What we know: Design system uses `.status-tag-error`, CONTEXT.md specifies `.status-tag-danger`
   - What's unclear: Which naming convention to use, or should both exist as aliases?
   - Recommendation: Add `.status-tag-danger` as alias to `.status-tag-error` in status-tag.css for consistency with button.css (`.btn-danger`)

3. **Products.php Allocation Icon Implementation**
   - What we know: CONTEXT.md specifies "三個疊加的小 icon" for allocation button
   - What's unclear: Exact visual design (icon choice, stacking method, sizing)
   - Recommendation: Create mockup or reference existing design during Wave 2 planning

4. **Button Size Classes for Mobile**
   - What we know: Design system has `.btn-sm` and `.btn-lg`
   - What's unclear: Should mobile automatically use `.btn-sm`, or should responsive sizing be built into `.btn`?
   - Recommendation: Check design system's `button.css` for responsive padding tokens - if not present, add them

5. **Vue Reactivity with Status Tags**
   - What we know: Current code uses `<button>` with `@click` for some status displays
   - What's unclear: Best pattern for status that CAN be changed but shouldn't look like primary action
   - Recommendation: Use toggle switch or separate edit button next to display-only status tag

## Sources

### Primary (HIGH confidence)
- Design system files (`/buygo-plus-one-dev/design-system/`)
  - `button.css` - Existing button styles, confirmed missing `.btn-danger`
  - `status-tag.css` - Complete status tag implementation
  - `DESIGN-SYSTEM.md` - Official design philosophy and principles
  - `USAGE.md` - Usage patterns and migration examples
- Phase 11 CONTEXT.md - User decisions on button types, status colors, icon strategy
- Current codebase patterns:
  - `admin/partials/products.php` - Complex button/status usage
  - `admin/partials/orders.php` - Status tag patterns
  - `admin/partials/shipment-*.php` - Button patterns with Vue
  - `admin/partials/settings.php` - Form buttons

### Secondary (MEDIUM confidence)
- Vue.js 3 directive documentation (for preservation patterns)
- CSS Custom Properties browser support (universally supported in 2026)
- Responsive design breakpoint standards (768px is industry standard for tablet/desktop split)

### Tertiary (LOW confidence - need validation)
- Exact implementation of products.php allocation icon stacking (need mockup/example)
- Whether status tags should ever be interactive (need user clarification)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Design system already implemented, just need to use it
- Architecture: HIGH - Clear migration path, existing patterns to follow
- Pitfalls: HIGH - Based on actual codebase patterns and Vue.js integration requirements
- Products.php allocation icon: MEDIUM - Special case needs design clarification
- Status interactivity: MEDIUM - Need to verify which status displays should remain clickable

**Research date:** 2026-01-28
**Valid until:** 2026-03-28 (60 days - stable design system, unlikely to change rapidly)

**Key dependencies:**
- Design system must be loaded globally (already done)
- `.btn-danger` must be added before migrating delete buttons
- Vue.js directives must be preserved in ALL cases
- Responsive testing required at 768px breakpoint
- CONTEXT.md color semantics must be followed exactly
