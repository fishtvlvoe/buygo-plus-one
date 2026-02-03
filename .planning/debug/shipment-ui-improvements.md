---
status: verifying
trigger: "修復出貨頁面的 UI/UX 問題"
created: 2026-02-03T10:00:00Z
updated: 2026-02-03T10:30:00Z
---

## Current Focus

hypothesis: 修改已完成，需要驗證瀏覽器效果
test: 在 https://test.buygo.me 檢查出貨頁面
expecting: 移除 Emoji、響應式橫排、彩色物流下拉選單全部生效
next_action: 瀏覽器驗證

## Symptoms

expected:
1. 移除所有 Emoji，使用與 BuyGo Plus One 一致的 icon 風格
2. 電腦版（≥768px）：三欄橫排佈局，欄寬等比例縮放
3. 手機版（<768px）：直排佈局
4. 到貨時間選擇器不超出框線
5. 物流方式下拉選單：
   - 外觀與運送狀態下拉一致
   - 8 個物流公司使用彩虹配色（紅橙黃綠藍靛紫粉）
   - 淺色底 + 深色字，確保清晰可讀

actual:
1. 使用 Emoji 表情符號（📦 📅 🚚）
2. 電腦版是直排，右側大量空白
3. 到貨時間選擇器的灰色背景超出邊框
4. 物流方式下拉選單樣式不一致，沒有顏色標籤

errors: 無錯誤訊息，是 UI/UX 改進需求

reproduction: 開啟 https://test.buygo.me/buygo-portal/shipment-details/?view=shipment-mark&id=73

started: 這是對現有功能的 UI 改進

## Eliminated

## Evidence

- timestamp: 2026-02-03T10:05:00Z
  checked: admin/partials/shipment-details.php (line 629-677)
  found: |
    「出貨設定」區塊使用 Emoji 表情符號：
    - Line 635: 📦 出貨時間
    - Line 647: 📅 到貨時間（選填）
    - Line 659: 🚚 物流方式（選填）

    佈局問題：
    - Line 515: 出貨資訊區使用 grid-cols-1 md:grid-cols-3（已有響應式）
    - Line 632: 出貨設定區使用 space-y-4（直排佈局）

    物流下拉選單：
    - Line 660-673: 普通 select 元素，8 個 option，無樣式
  implication: |
    需要修改：
    1. 移除 Emoji，改用 SVG icon
    2. 將出貨設定區改為響應式橫排（類似出貨資訊區）
    3. 物流下拉選單需要自訂樣式（參考訂單頁面的運送狀態）

- timestamp: 2026-02-03T10:15:00Z
  checked: components/order/order-detail-modal.php (line 59-98, 255-262)
  found: |
    運送狀態下拉選單實作模式：
    - 自訂 button 觸發下拉（不是 select）
    - 顯示彩色標籤（bg-xxx-100 text-xxx-800 border）
    - 下拉選單用 absolute positioning + z-50
    - shippingStatuses 陣列定義顏色和文字
  implication: |
    複製此模式到物流方式下拉：
    1. 定義 shippingMethods 陣列（8 個物流公司 + 彩虹配色）
    2. 在 markShippedData 加入 showShippingMethodDropdown
    3. 改用 button + dropdown 替代 select

## Resolution

root_cause: |
  出貨頁面的 UI 不一致：
  1. 使用 Emoji 而非 icon system
  2. 電腦版使用直排佈局，未充分利用空間
  3. 物流下拉選單是普通 select，沒有視覺標籤

fix: |
  【shipment-details.php 修改】
  1. 移除 Emoji（📦 📅 🚚），改用 SVG icon：
     - 出貨時間：package icon
     - 到貨時間：calendar icon
     - 物流方式：truck icon
  2. 改佈局：space-y-4 → grid-cols-1 md:grid-cols-3 gap-4
  3. 移除欄位寬度限制：w-full md:w-64 → w-full
  4. 物流下拉：select → button + dropdown（模仿運送狀態）
  5. 到貨時間 input 加上 bg-white 避免灰底超出

  【ShipmentDetailsPage.js 修改】
  1. 定義 shippingMethods 陣列（8 個物流公司 + 彩虹配色）：
     - 易利：紅色（bg-red-100 text-red-800）
     - 千森：橙色（bg-orange-100 text-orange-800）
     - OMI：黃色（bg-yellow-100 text-yellow-800）
     - 多賀：綠色（bg-green-100 text-green-800）
     - 賀來：藍色（bg-blue-100 text-blue-800）
     - 神奈川：靛色（bg-indigo-100 text-indigo-800）
     - 新日本：紫色（bg-purple-100 text-purple-800）
     - EMS：粉色（bg-pink-100 text-pink-800）
  2. 加入 showShippingMethodDropdown 狀態管理
  3. 加入控制函數：toggleShippingMethodDropdown、selectShippingMethod、getShippingMethodColor
  4. 加入點擊外部關閉下拉選單的事件監聽

verification: 需在瀏覽器驗證（https://test.buygo.me/buygo-portal/shipment-details/?view=shipment-mark&id=73）
files_changed:
  - admin/partials/shipment-details.php
  - admin/js/components/ShipmentDetailsPage.js

root_cause:
fix:
verification:
files_changed: []
