<?php
/**
 * LINE 關鍵字回覆處理器
 *
 * 處理用戶在 LINE 中發送的關鍵字指令，例如：
 * - /ID 或 /id - 查詢綁定狀態
 * - /help 或 /說明 - 顯示可用指令
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LineKeywordResponder {

	/**
	 * 單例實例
	 */
	private static ?self $instance = null;

	/**
	 * 取得單例
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 初始化
	 */
	public function init(): void {
		// 監聽 LINE 訊息回覆 filter
		add_filter( 'buygo_line_notify/get_response', [ $this, 'handle_keyword' ], 10, 5 );
	}

	/**
	 * 處理關鍵字指令
	 *
	 * @param mixed       $response    現有的回覆內容（null 表示尚無回覆）
	 * @param string      $action_type 事件類型
	 * @param array       $event       LINE Webhook 事件
	 * @param string      $line_uid    LINE User ID
	 * @param int|null    $user_id     WordPress User ID
	 * @return mixed 回覆內容或 null
	 */
	public function handle_keyword( $response, string $action_type, array $event, string $line_uid, ?int $user_id ) {
		// 只處理文字訊息
		if ( $action_type !== 'message_text' ) {
			return $response;
		}

		// 如果已經有回覆內容，不覆蓋
		if ( $response !== null ) {
			return $response;
		}

		// 取得用戶發送的文字
		$text = trim( $event['message']['text'] ?? '' );
		$text_lower = strtolower( $text );

		// 關鍵字對照表
		switch ( $text_lower ) {
			case '/id':
			case '/綁定':
			case '/狀態':
				return $this->get_binding_status_message( $line_uid, $user_id );

			case '/help':
			case '/說明':
			case '/指令':
				return $this->get_help_message();

			default:
				// 不是關鍵字，不回覆
				return $response;
		}
	}

	/**
	 * 取得綁定狀態訊息
	 *
	 * @param string   $line_uid LINE User ID
	 * @param int|null $user_id  WordPress User ID
	 * @return array LINE Flex Message
	 */
	private function get_binding_status_message( string $line_uid, ?int $user_id ): array {
		$site_name = get_bloginfo( 'name' );

		if ( $user_id && $user_id > 0 ) {
			// 已綁定
			$user = get_userdata( $user_id );
			$display_name = $user ? $user->display_name : '未知';
			$email = $user ? $user->user_email : '';

			// 檢查用戶角色
			$roles = $user ? $user->roles : [];
			$role_labels = [];
			if ( in_array( 'administrator', $roles, true ) ) {
				$role_labels[] = '管理員';
			}
			if ( in_array( 'buygo_admin', $roles, true ) ) {
				$role_labels[] = '管理員';
			}
			if ( in_array( 'buygo_helper', $roles, true ) ) {
				$role_labels[] = '小幫手';
			}
			if ( in_array( 'seller', $roles, true ) || in_array( 'fct_seller', $roles, true ) ) {
				$role_labels[] = '賣家';
			}
			if ( empty( $role_labels ) ) {
				$role_labels[] = '買家';
			}

			return [
				'type' => 'flex',
				'altText' => '✅ 綁定狀態確認',
				'contents' => [
					'type' => 'bubble',
					'size' => 'kilo',
					'header' => [
						'type' => 'box',
						'layout' => 'vertical',
						'contents' => [
							[
								'type' => 'text',
								'text' => '✅ 帳號已綁定',
								'weight' => 'bold',
								'size' => 'lg',
								'color' => '#1DB446',
							],
						],
						'backgroundColor' => '#F0FDF4',
						'paddingAll' => 'lg',
					],
					'body' => [
						'type' => 'box',
						'layout' => 'vertical',
						'contents' => [
							[
								'type' => 'box',
								'layout' => 'horizontal',
								'contents' => [
									[
										'type' => 'text',
										'text' => '網站',
										'color' => '#666666',
										'size' => 'sm',
										'flex' => 2,
									],
									[
										'type' => 'text',
										'text' => $site_name,
										'wrap' => true,
										'color' => '#111111',
										'size' => 'sm',
										'flex' => 5,
									],
								],
								'margin' => 'md',
							],
							[
								'type' => 'box',
								'layout' => 'horizontal',
								'contents' => [
									[
										'type' => 'text',
										'text' => '用戶名稱',
										'color' => '#666666',
										'size' => 'sm',
										'flex' => 2,
									],
									[
										'type' => 'text',
										'text' => $display_name,
										'wrap' => true,
										'color' => '#111111',
										'size' => 'sm',
										'flex' => 5,
									],
								],
								'margin' => 'md',
							],
							[
								'type' => 'box',
								'layout' => 'horizontal',
								'contents' => [
									[
										'type' => 'text',
										'text' => '身份',
										'color' => '#666666',
										'size' => 'sm',
										'flex' => 2,
									],
									[
										'type' => 'text',
										'text' => implode( '、', $role_labels ),
										'wrap' => true,
										'color' => '#111111',
										'size' => 'sm',
										'flex' => 5,
									],
								],
								'margin' => 'md',
							],
						],
						'paddingAll' => 'lg',
					],
					'footer' => [
						'type' => 'box',
						'layout' => 'vertical',
						'contents' => [
							[
								'type' => 'text',
								'text' => '您可以接收訂單通知和重要資訊',
								'color' => '#888888',
								'size' => 'xs',
								'align' => 'center',
							],
						],
						'paddingAll' => 'md',
					],
				],
			];
		} else {
			// 未綁定
			$login_url = home_url( '/buygo/line-binduser/' );

			return [
				'type' => 'flex',
				'altText' => '❌ 尚未綁定帳號',
				'contents' => [
					'type' => 'bubble',
					'size' => 'kilo',
					'header' => [
						'type' => 'box',
						'layout' => 'vertical',
						'contents' => [
							[
								'type' => 'text',
								'text' => '❌ 尚未綁定帳號',
								'weight' => 'bold',
								'size' => 'lg',
								'color' => '#DC2626',
							],
						],
						'backgroundColor' => '#FEF2F2',
						'paddingAll' => 'lg',
					],
					'body' => [
						'type' => 'box',
						'layout' => 'vertical',
						'contents' => [
							[
								'type' => 'text',
								'text' => "您的 LINE 尚未與 {$site_name} 帳號綁定。",
								'wrap' => true,
								'color' => '#333333',
								'size' => 'sm',
							],
							[
								'type' => 'text',
								'text' => '綁定後可以接收：',
								'wrap' => true,
								'color' => '#666666',
								'size' => 'sm',
								'margin' => 'lg',
							],
							[
								'type' => 'text',
								'text' => '• 訂單成立通知',
								'color' => '#666666',
								'size' => 'sm',
								'margin' => 'sm',
							],
							[
								'type' => 'text',
								'text' => '• 出貨通知',
								'color' => '#666666',
								'size' => 'sm',
								'margin' => 'sm',
							],
							[
								'type' => 'text',
								'text' => '• 重要系統通知',
								'color' => '#666666',
								'size' => 'sm',
								'margin' => 'sm',
							],
						],
						'paddingAll' => 'lg',
					],
					'footer' => [
						'type' => 'box',
						'layout' => 'vertical',
						'contents' => [
							[
								'type' => 'button',
								'style' => 'primary',
								'height' => 'sm',
								'action' => [
									'type' => 'uri',
									'label' => '立即綁定帳號',
									'uri' => $login_url,
								],
								'color' => '#06C755',
							],
						],
						'paddingAll' => 'md',
					],
				],
			];
		}
	}

	/**
	 * 取得說明訊息
	 *
	 * @return array LINE Flex Message
	 */
	private function get_help_message(): array {
		$site_name = get_bloginfo( 'name' );

		return [
			'type' => 'flex',
			'altText' => '📋 指令說明',
			'contents' => [
				'type' => 'bubble',
				'size' => 'kilo',
				'header' => [
					'type' => 'box',
					'layout' => 'vertical',
					'contents' => [
						[
							'type' => 'text',
							'text' => '📋 可用指令',
							'weight' => 'bold',
							'size' => 'lg',
							'color' => '#1E40AF',
						],
					],
					'backgroundColor' => '#EFF6FF',
					'paddingAll' => 'lg',
				],
				'body' => [
					'type' => 'box',
					'layout' => 'vertical',
					'contents' => [
						[
							'type' => 'box',
							'layout' => 'horizontal',
							'contents' => [
								[
									'type' => 'text',
									'text' => '/ID',
									'weight' => 'bold',
									'color' => '#06C755',
									'size' => 'sm',
									'flex' => 2,
								],
								[
									'type' => 'text',
									'text' => '查詢帳號綁定狀態',
									'wrap' => true,
									'color' => '#333333',
									'size' => 'sm',
									'flex' => 5,
								],
							],
							'margin' => 'md',
						],
						[
							'type' => 'box',
							'layout' => 'horizontal',
							'contents' => [
								[
									'type' => 'text',
									'text' => '/綁定',
									'weight' => 'bold',
									'color' => '#06C755',
									'size' => 'sm',
									'flex' => 2,
								],
								[
									'type' => 'text',
									'text' => '同上，查詢綁定狀態',
									'wrap' => true,
									'color' => '#333333',
									'size' => 'sm',
									'flex' => 5,
								],
							],
							'margin' => 'md',
						],
						[
							'type' => 'box',
							'layout' => 'horizontal',
							'contents' => [
								[
									'type' => 'text',
									'text' => '/help',
									'weight' => 'bold',
									'color' => '#06C755',
									'size' => 'sm',
									'flex' => 2,
								],
								[
									'type' => 'text',
									'text' => '顯示此說明',
									'wrap' => true,
									'color' => '#333333',
									'size' => 'sm',
									'flex' => 5,
								],
							],
							'margin' => 'md',
						],
						[
							'type' => 'separator',
							'margin' => 'lg',
						],
						[
							'type' => 'text',
							'text' => "💡 輸入指令即可查詢 {$site_name} 相關資訊",
							'wrap' => true,
							'color' => '#888888',
							'size' => 'xs',
							'margin' => 'lg',
						],
					],
					'paddingAll' => 'lg',
				],
			],
		];
	}
}
