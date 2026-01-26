/**
 * Permissions Composable - 統一權限管理
 *
 * 功能:
 * - 檢查用戶是否為管理員
 * - 檢查用戶是否為小幫手
 * - 檢查特定功能權限
 * - 提供權限相關的 UI 控制
 *
 * 使用方式:
 * const { isAdmin, isHelper, can, loadPermissions } = usePermissions();
 *
 * // 檢查是否為管理員
 * if (isAdmin.value) {
 *     // 管理員專屬功能
 * }
 *
 * // 檢查特定權限
 * if (can('manage_helpers')) {
 *     // 可以管理小幫手
 * }
 *
 * @version 1.0.0
 * @date 2026-01-24
 */

// 注意: 這是一個全局函數,不使用 ES6 import/export
// 因為 WordPress 環境中 ES6 模組可能不被支持
function usePermissions() {
    const { ref, computed } = Vue;

    // ============================================
    // 1. 權限狀態
    // ============================================

    /**
     * 是否為管理員
     * @type {Ref<boolean>}
     */
    const isAdmin = ref(false);

    /**
     * 是否為小幫手
     * @type {Ref<boolean>}
     */
    const isHelper = ref(false);

    /**
     * 用戶角色
     * 可能的值: 'admin', 'helper', 'customer', null
     * @type {Ref<string|null>}
     */
    const userRole = ref(null);

    /**
     * 用戶 ID
     * @type {Ref<number|null>}
     */
    const userId = ref(null);

    /**
     * 用戶顯示名稱
     * @type {Ref<string>}
     */
    const displayName = ref('');

    /**
     * 權限載入狀態
     * @type {Ref<boolean>}
     */
    const loading = ref(false);

    /**
     * 權限載入錯誤
     * @type {Ref<string|null>}
     */
    const error = ref(null);

    /**
     * 具體權限清單
     * @type {Ref<Object>}
     */
    const permissions = ref({
        // 管理員專屬權限
        manage_helpers: false,      // 管理小幫手
        manage_settings: false,     // 管理設定
        view_all_orders: false,     // 查看所有訂單
        export_data: false,         // 匯出數據

        // 小幫手權限
        view_products: false,       // 查看商品
        manage_products: false,     // 管理商品
        view_orders: false,         // 查看訂單
        manage_orders: false,       // 管理訂單
        view_customers: false,      // 查看客戶
        manage_shipments: false     // 管理出貨
    });

    // ============================================
    // 2. 計算屬性
    // ============================================

    /**
     * 是否已登入
     */
    const isLoggedIn = computed(() => {
        return userId.value !== null && userId.value > 0;
    });

    /**
     * 是否有任何管理權限
     */
    const hasAnyPermission = computed(() => {
        return isAdmin.value || isHelper.value;
    });

    /**
     * 用戶角色顯示名稱
     */
    const roleDisplayName = computed(() => {
        const roleMap = {
            'admin': '管理員',
            'helper': '小幫手',
            'customer': '客戶'
        };
        return roleMap[userRole.value] || '訪客';
    });

    // ============================================
    // 3. 權限檢查方法
    // ============================================

    /**
     * 檢查是否擁有特定權限
     * @param {string} permission - 權限名稱
     * @returns {boolean} 是否擁有該權限
     */
    const can = (permission) => {
        // 管理員擁有所有權限
        if (isAdmin.value) {
            return true;
        }

        // 檢查具體權限
        return permissions.value[permission] === true;
    };

    /**
     * 檢查是否擁有任一權限（OR 邏輯）
     * @param {string[]} permissionList - 權限名稱陣列
     * @returns {boolean} 是否擁有任一權限
     */
    const canAny = (permissionList) => {
        if (isAdmin.value) {
            return true;
        }

        return permissionList.some(permission => permissions.value[permission] === true);
    };

    /**
     * 檢查是否擁有所有權限（AND 邏輯）
     * @param {string[]} permissionList - 權限名稱陣列
     * @returns {boolean} 是否擁有所有權限
     */
    const canAll = (permissionList) => {
        if (isAdmin.value) {
            return true;
        }

        return permissionList.every(permission => permissions.value[permission] === true);
    };

    /**
     * 檢查是否可以訪問某個頁面
     * @param {string} page - 頁面名稱 (products, orders, customers, shipments, settings)
     * @returns {boolean} 是否可以訪問
     */
    const canAccessPage = (page) => {
        // 管理員可以訪問所有頁面
        if (isAdmin.value) {
            return true;
        }

        // 根據頁面類型檢查權限
        const pagePermissions = {
            'products': 'view_products',
            'orders': 'view_orders',
            'customers': 'view_customers',
            'shipments': 'manage_shipments',
            'settings': 'manage_settings'
        };

        const requiredPermission = pagePermissions[page];
        return requiredPermission ? can(requiredPermission) : false;
    };

    // ============================================
    // 4. 權限載入方法
    // ============================================

    /**
     * 從 API 載入用戶權限
     * @returns {Promise<Object>} 權限數據
     */
    const loadPermissions = async () => {
        loading.value = true;
        error.value = null;

        try {
            const wpNonce = window.buygoWpNonce || '';

            const response = await window.fetch('/wp-json/buygo-plus-one/v1/settings/permissions', {
                headers: {
                    'X-WP-Nonce': wpNonce,
                    'Cache-Control': 'no-cache'
                },
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success && result.data) {
                // 更新基礎狀態
                isAdmin.value = result.data.is_admin || false;
                isHelper.value = result.data.is_helper || false;
                userRole.value = result.data.role || null;
                userId.value = result.data.user_id || null;
                displayName.value = result.data.display_name || '';

                // 更新具體權限
                if (result.data.permissions) {
                    permissions.value = {
                        ...permissions.value,
                        ...result.data.permissions
                    };
                } else {
                    // 根據角色設定預設權限
                    updatePermissionsByRole();
                }

                return result.data;
            } else {
                throw new Error(result.message || '載入權限失敗');
            }

        } catch (err) {
            error.value = err.message;
            console.error('載入權限錯誤:', err);

            // 失敗時設定為訪客權限
            resetPermissions();
            throw err;

        } finally {
            loading.value = false;
        }
    };

    /**
     * 根據角色更新權限（用於後端未提供具體權限時）
     */
    const updatePermissionsByRole = () => {
        if (isAdmin.value) {
            // 管理員擁有所有權限
            Object.keys(permissions.value).forEach(key => {
                permissions.value[key] = true;
            });
        } else if (isHelper.value) {
            // 小幫手擁有部分權限
            permissions.value = {
                manage_helpers: false,
                manage_settings: false,
                view_all_orders: false,
                export_data: false,

                view_products: true,
                manage_products: true,
                view_orders: true,
                manage_orders: true,
                view_customers: true,
                manage_shipments: true
            };
        } else {
            // 訪客/客戶無權限
            resetPermissions();
        }
    };

    /**
     * 重置為訪客權限（無權限）
     */
    const resetPermissions = () => {
        isAdmin.value = false;
        isHelper.value = false;
        userRole.value = null;
        userId.value = null;
        displayName.value = '';

        Object.keys(permissions.value).forEach(key => {
            permissions.value[key] = false;
        });
    };

    /**
     * 手動設定權限（用於測試或特殊情況）
     * @param {Object} data - 權限數據
     */
    const setPermissions = (data) => {
        if (data.is_admin !== undefined) {
            isAdmin.value = data.is_admin;
        }
        if (data.is_helper !== undefined) {
            isHelper.value = data.is_helper;
        }
        if (data.role !== undefined) {
            userRole.value = data.role;
        }
        if (data.user_id !== undefined) {
            userId.value = data.user_id;
        }
        if (data.display_name !== undefined) {
            displayName.value = data.display_name;
        }
        if (data.permissions) {
            permissions.value = {
                ...permissions.value,
                ...data.permissions
            };
        }
    };

    // ============================================
    // 5. UI 輔助方法
    // ============================================

    /**
     * 權限不足時的提示訊息
     * @param {string} action - 動作名稱
     */
    const showPermissionDenied = (action = '執行此操作') => {
        if (window.showToast) {
            window.showToast(`您沒有權限${action}`, 'error');
        } else {
            alert(`您沒有權限${action}`);
        }
    };

    /**
     * 需要權限時的確認檢查
     * @param {string|string[]} permission - 權限名稱或陣列
     * @param {string} action - 動作名稱（用於錯誤訊息）
     * @returns {boolean} 是否通過檢查
     */
    const requirePermission = (permission, action = '執行此操作') => {
        const hasPermission = Array.isArray(permission)
            ? canAny(permission)
            : can(permission);

        if (!hasPermission) {
            showPermissionDenied(action);
            return false;
        }

        return true;
    };

    // ============================================
    // 6. 公開接口
    // ============================================

    return {
        // 狀態
        isAdmin,
        isHelper,
        userRole,
        userId,
        displayName,
        permissions,
        loading,
        error,

        // 計算屬性
        isLoggedIn,
        hasAnyPermission,
        roleDisplayName,

        // 權限檢查方法
        can,
        canAny,
        canAll,
        canAccessPage,

        // 權限管理方法
        loadPermissions,
        setPermissions,
        resetPermissions,

        // UI 輔助方法
        showPermissionDenied,
        requirePermission
    };
}
