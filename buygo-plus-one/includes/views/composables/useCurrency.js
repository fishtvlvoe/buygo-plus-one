/**
 * Currency Composable - 全站幣別處理統一邏輯
 *
 * 功能:
 * - 統一的幣別格式化
 * - 匯率轉換
 * - 幣別符號管理
 *
 * 使用方式:
 * const { formatPrice, convertCurrency, getCurrencySymbol } = useCurrency();
 * formatPrice(1000, 'JPY'); // "¥1,000"
 *
 * @version 1.0.0
 * @date 2026-01-20
 */

// 注意: 這是一個全局函數,不使用 ES6 import/export
// 因為 WordPress 環境中 ES6 模組可能不被支持
function useCurrency() {
    const { ref, computed } = Vue;
    // 系統幣別 (從 WordPress 全局設定讀取)
    const systemCurrency = ref(window.buygoSettings?.currency || 'JPY');

    // 幣別符號對照表
    const currencySymbols = {
        'JPY': '¥',
        'TWD': 'NT$',
        'USD': '$',
        'THB': '฿',
        'CNY': '¥',
        'EUR': '€',
        'GBP': '£'
    };

    // 匯率對照表 (基準: JPY = 1)
    // 注意: 實際應用中應該從 API 或設定檔讀取即時匯率
    const exchangeRates = ref({
        'JPY': 1,
        'TWD': 0.23,    // 1 JPY = 0.23 TWD
        'USD': 0.0067,  // 1 JPY = 0.0067 USD
        'THB': 0.24,    // 1 JPY = 0.24 THB
        'CNY': 0.048,   // 1 JPY = 0.048 CNY
        'EUR': 0.0062,  // 1 JPY = 0.0062 EUR
        'GBP': 0.0053   // 1 JPY = 0.0053 GBP
    });

    /**
     * 取得幣別符號
     * @param {string} currency - 幣別代碼 (如: JPY, TWD)
     * @returns {string} 幣別符號
     */
    const getCurrencySymbol = (currency) => {
        return currencySymbols[currency] || '¥';
    };

    /**
     * 格式化價格 (不做匯率轉換)
     * @param {number|string} price - 價格
     * @param {string|null} currency - 幣別代碼，null 時使用系統預設
     * @returns {string} 格式化後的價格字串，如: "¥1,000"
     */
    const formatPrice = (price, currency = null) => {
        // 防護: 確保 price 是數字
        const safePrice = price ?? 0;
        const numPrice = typeof safePrice === 'string' ? parseFloat(safePrice) : safePrice;

        // 使用指定幣別或系統預設幣別
        const curr = currency || systemCurrency.value;

        // 取得幣別符號
        const symbol = getCurrencySymbol(curr);

        // 四捨五入到整數
        const roundedPrice = Math.round(numPrice);

        // 格式化數字 (加上千分位逗號)
        const formattedPrice = roundedPrice.toLocaleString('zh-TW');

        return `${symbol}${formattedPrice}`;
    };

    /**
     * 匯率轉換
     * @param {number} amount - 金額
     * @param {string} fromCurrency - 來源幣別
     * @param {string} toCurrency - 目標幣別
     * @returns {number} 轉換後的金額
     */
    const convertCurrency = (amount, fromCurrency, toCurrency) => {
        if (fromCurrency === toCurrency) {
            return amount;
        }

        // 先轉換為 JPY (基準幣別)
        const amountInJPY = amount / (exchangeRates.value[fromCurrency] || 1);

        // 再轉換為目標幣別
        const convertedAmount = amountInJPY * (exchangeRates.value[toCurrency] || 1);

        return convertedAmount;
    };

    /**
     * 格式化價格並做匯率轉換
     * @param {number|string} price - 原始價格
     * @param {string} fromCurrency - 原始幣別
     * @param {string|null} toCurrency - 目標幣別，null 時使用系統預設
     * @returns {string} 格式化後的價格字串
     */
    const formatPriceWithConversion = (price, fromCurrency, toCurrency = null) => {
        const safePrice = price ?? 0;
        const numPrice = typeof safePrice === 'string' ? parseFloat(safePrice) : safePrice;
        const targetCurrency = toCurrency || systemCurrency.value;

        // 執行匯率轉換
        const convertedPrice = convertCurrency(numPrice, fromCurrency, targetCurrency);

        // 格式化價格
        return formatPrice(convertedPrice, targetCurrency);
    };

    /**
     * 更新匯率表
     * @param {Object} newRates - 新的匯率對照表
     */
    const updateExchangeRates = (newRates) => {
        exchangeRates.value = { ...exchangeRates.value, ...newRates };
    };

    /**
     * 設定系統幣別
     * @param {string} currency - 新的系統幣別
     */
    const setSystemCurrency = (currency) => {
        systemCurrency.value = currency;
    };

    // 回傳所有公開的方法和狀態
    return {
        // 狀態
        systemCurrency,
        currencySymbols,
        exchangeRates,

        // 方法
        getCurrencySymbol,
        formatPrice,
        convertCurrency,
        formatPriceWithConversion,
        updateExchangeRates,
        setSystemCurrency
    };
}
