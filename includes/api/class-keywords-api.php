<?php
namespace BuyGoPlus\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Keywords_API {
    
    private $namespace = 'buygo-plus-one/v1';
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * 註冊 REST API 路由
     */
    public function register_routes() {
        // GET /settings/line-keywords - 取得關鍵字列表
        register_rest_route($this->namespace, '/settings/line-keywords', [
            'methods' => 'GET',
            'callback' => [$this, 'get_keywords'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // POST /settings/line-keywords - 更新關鍵字列表（新增、編輯、刪除、排序）
        register_rest_route($this->namespace, '/settings/line-keywords', [
            'methods' => 'POST',
            'callback' => [$this, 'update_keywords'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    /**
     * 權限檢查
     * 使用統一的權限檢查方法，與其他 API 一致
     */
    public function check_permission() {
        // 暫時使用統一的權限檢查（測試階段）
        // 測試完成後，改為使用 SettingsService 的權限檢查
        return \BuyGoPlus\Api\API::check_permission();
    }
    
    /**
     * 取得關鍵字列表
     */
    public function get_keywords() {
        try {
            $keywords = get_option('buygo_line_keywords', []);
            
            // 確保是陣列格式
            if (!is_array($keywords)) {
                $keywords = [];
            }
            
            // 驗證並清理資料格式
            $validated_keywords = [];
            foreach ($keywords as $keyword) {
                // 確保是陣列且有必要的欄位
                if (is_array($keyword) && !empty($keyword['keyword'])) {
                    $validated_keywords[] = [
                        'id' => isset($keyword['id']) ? sanitize_key($keyword['id']) : 'kw_' . uniqid(),
                        'keyword' => sanitize_text_field($keyword['keyword']),
                        'aliases' => is_array($keyword['aliases'] ?? null) 
                            ? array_map('sanitize_text_field', $keyword['aliases']) 
                            : [],
                        'message' => sanitize_textarea_field($keyword['message'] ?? ''),
                        'order' => isset($keyword['order']) ? intval($keyword['order']) : 0
                    ];
                }
            }
            
            // 如果沒有有效的關鍵字，提供預設的 /help 關鍵字
            if (empty($validated_keywords)) {
                $validated_keywords = [
                    [
                        'id' => 'help',
                        'keyword' => '/help',
                        'aliases' => ['/幫助', '?help', '幫助'],
                        'message' => "📱 商品上架說明\n\n【步驟】\n1️⃣ 發送商品圖片\n2️⃣ 發送商品資訊\n\n【必填欄位】\n商品名稱\n價格：350\n數量：20\n\n【選填欄位】\n原價：500\n分類：服飾\n到貨：01/25\n預購：01/20\n描述：商品描述\n\n【範例】\n冬季外套\n價格：1200\n原價：1800\n數量：15\n分類：服飾\n到貨：01/15\n\n💡 輸入 /分類 查看可用分類",
                        'order' => 0
                    ]
                ];
            }
            
            // 按照 order 排序
            usort($validated_keywords, function($a, $b) {
                return ($a['order'] ?? 0) - ($b['order'] ?? 0);
            });
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $validated_keywords
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得關鍵字列表失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 更新關鍵字列表
     */
    public function update_keywords($request) {
        try {
            $body = json_decode($request->get_body(), true);
            
            if (!isset($body['keywords']) || !is_array($body['keywords'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的資料格式'
                ], 400);
            }
            
            // 驗證並清理資料
            $keywords = [];
            $order = 0;
            
            foreach ($body['keywords'] as $keyword) {
                // 跳過空的關鍵字
                if (empty($keyword['keyword'])) {
                    continue;
                }
                
                // 生成 ID（如果沒有提供）
                $id = !empty($keyword['id']) ? sanitize_key($keyword['id']) : 'kw_' . uniqid();
                
                // 清理資料
                $keywords[] = [
                    'id' => $id,
                    'keyword' => sanitize_text_field($keyword['keyword']),
                    'aliases' => array_map('sanitize_text_field', $keyword['aliases'] ?? []),
                    'message' => sanitize_textarea_field($keyword['message'] ?? ''),
                    'order' => isset($keyword['order']) ? intval($keyword['order']) : $order++
                ];
            }
            
            // 按照 order 排序
            usort($keywords, function($a, $b) {
                return ($a['order'] ?? 0) - ($b['order'] ?? 0);
            });
            
            // 儲存到資料庫
            update_option('buygo_line_keywords', $keywords);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '關鍵字已儲存',
                'data' => $keywords
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '更新關鍵字列表失敗：' . $e->getMessage()
            ], 500);
        }
    }
}
