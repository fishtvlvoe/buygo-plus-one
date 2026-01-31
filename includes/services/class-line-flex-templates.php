<?php
/**
 * LINE Flex Templates
 *
 * 提供商品上架相關的 Flex Message 模板
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LineFlexTemplates
 *
 * 管理商品上架流程中使用的 LINE Flex Message 模板
 */
class LineFlexTemplates {

	/**
	 * 取得商品類型選單 Flex Message
	 *
	 * 當賣家上傳圖片後，顯示兩個選項讓賣家選擇：
	 * - 單一商品（simple product）
	 * - 多樣式商品（variable product）
	 *
	 * @return array Flex Message Bubble 格式
	 */
	public static function getProductTypeMenu() {
		return [
			'type' => 'bubble',
			'body' => [
				'type' => 'box',
				'layout' => 'vertical',
				'contents' => [
					// 標題
					[
						'type' => 'text',
						'text' => '選擇商品類型',
						'weight' => 'bold',
						'size' => 'xl',
						'margin' => 'md',
					],
					// 分隔線
					[
						'type' => 'separator',
						'margin' => 'xl',
					],
					// 單一商品說明
					[
						'type' => 'box',
						'layout' => 'vertical',
						'margin' => 'lg',
						'spacing' => 'sm',
						'contents' => [
							[
								'type' => 'text',
								'text' => '📦 單一商品',
								'weight' => 'bold',
								'size' => 'md',
								'color' => '#1DB446',
							],
							[
								'type' => 'text',
								'text' => '只有一個價格和數量',
								'size' => 'sm',
								'color' => '#666666',
								'wrap' => true,
							],
						],
					],
					// 多樣式商品說明
					[
						'type' => 'box',
						'layout' => 'vertical',
						'margin' => 'lg',
						'spacing' => 'sm',
						'contents' => [
							[
								'type' => 'text',
								'text' => '🎨 多樣式商品',
								'weight' => 'bold',
								'size' => 'md',
								'color' => '#06C755',
							],
							[
								'type' => 'text',
								'text' => '有多個款式（如顏色、尺寸）',
								'size' => 'sm',
								'color' => '#666666',
								'wrap' => true,
							],
						],
					],
				],
			],
			'footer' => [
				'type' => 'box',
				'layout' => 'vertical',
				'spacing' => 'sm',
				'contents' => [
					// 單一商品按鈕
					[
						'type' => 'button',
						'style' => 'primary',
						'color' => '#1DB446',
						'action' => [
							'type' => 'postback',
							'label' => '單一商品',
							'data' => 'action=product_type&type=simple',
						],
					],
					// 多樣式商品按鈕
					[
						'type' => 'button',
						'style' => 'primary',
						'color' => '#06C755',
						'action' => [
							'type' => 'postback',
							'label' => '多樣式商品',
							'data' => 'action=product_type&type=variable',
						],
					],
				],
			],
		];
	}

	/**
	 * 取得單一商品確認訊息 Flex Message
	 *
	 * @param array $product 商品資料
	 *   - name: 商品名稱
	 *   - price: 價格
	 *   - original_price: 原價 (可選)
	 *   - quantity: 數量
	 *   - url: 商品連結
	 *   - image_url: 商品圖片連結
	 * @return array Flex Message Bubble 格式
	 */
	public static function getSimpleProductConfirmation( $product ) {
		$contents = [
			[
				'type' => 'text',
				'text' => '✅ 商品已成功上架！',
				'weight' => 'bold',
				'color' => '#1DB446',
				'size' => 'sm',
			],
			[
				'type' => 'text',
				'text' => $product['name'],
				'weight' => 'bold',
				'size' => 'xl',
				'margin' => 'md',
				'wrap' => true,
			],
			[
				'type' => 'box',
				'layout' => 'baseline',
				'margin' => 'md',
				'contents' => [
					[
						'type' => 'text',
						'text' => '價格',
						'size' => 'sm',
						'color' => '#999999',
						'flex' => 0,
					],
					[
						'type' => 'text',
						'text' => '¥' . number_format( $product['price'] ),
						'size' => 'sm',
						'color' => '#111111',
						'weight' => 'bold',
						'margin' => 'lg',
					],
				],
			],
			[
				'type' => 'box',
				'layout' => 'baseline',
				'margin' => 'sm',
				'contents' => [
					[
						'type' => 'text',
						'text' => '數量',
						'size' => 'sm',
						'color' => '#999999',
						'flex' => 0,
					],
					[
						'type' => 'text',
						'text' => $product['quantity'] . ' 件',
						'size' => 'sm',
						'color' => '#111111',
						'margin' => 'lg',
					],
				],
			],
		];

		// 如果有原價，加入原價顯示
		if ( ! empty( $product['original_price'] ) && $product['original_price'] > $product['price'] ) {
			$contents[] = [
				'type' => 'box',
				'layout' => 'baseline',
				'margin' => 'sm',
				'contents' => [
					[
						'type' => 'text',
						'text' => '原價',
						'size' => 'sm',
						'color' => '#999999',
						'flex' => 0,
					],
					[
						'type' => 'text',
						'text' => '¥' . number_format( $product['original_price'] ),
						'size' => 'sm',
						'color' => '#999999',
						'margin' => 'lg',
						'decoration' => 'line-through',
					],
				],
			];
		}

		$bubble = [
			'type' => 'bubble',
			'body' => [
				'type' => 'box',
				'layout' => 'vertical',
				'contents' => $contents,
			],
			'footer' => [
				'type' => 'box',
				'layout' => 'vertical',
				'contents' => [
					[
						'type' => 'button',
						'action' => [
							'type' => 'uri',
							'label' => '🛒 查看商品',
							'uri' => $product['url'],
						],
						'style' => 'primary',
						'color' => '#1DB446',
					],
				],
			],
		];

		// 如果有圖片，加入 hero 區域
		if ( ! empty( $product['image_url'] ) ) {
			$bubble['hero'] = [
				'type' => 'image',
				'url' => $product['image_url'],
				'size' => 'full',
				'aspectRatio' => '1:1',
				'aspectMode' => 'cover',
			];
		}

		return $bubble;
	}

	/**
	 * 取得多樣式商品確認訊息 Flex Message
	 *
	 * @param array $product 商品資料
	 *   - name: 商品名稱
	 *   - variations: 款式陣列
	 *     - variation_title: 款式名稱
	 *     - price: 價格
	 *     - quantity: 數量
	 *   - url: 商品連結
	 *   - image_url: 商品圖片連結
	 * @return array Flex Message Bubble 格式
	 */
	public static function getVariableProductConfirmation( $product ) {
		$contents = [
			[
				'type' => 'text',
				'text' => '✅ 商品已成功上架！',
				'weight' => 'bold',
				'color' => '#1DB446',
				'size' => 'sm',
			],
			[
				'type' => 'text',
				'text' => $product['name'],
				'weight' => 'bold',
				'size' => 'xl',
				'margin' => 'md',
				'wrap' => true,
			],
			[
				'type' => 'separator',
				'margin' => 'md',
			],
			[
				'type' => 'text',
				'text' => '款式列表',
				'size' => 'sm',
				'color' => '#666666',
				'margin' => 'md',
			],
		];

		// 為每個 variation 建立 box
		if ( ! empty( $product['variations'] ) && is_array( $product['variations'] ) ) {
			foreach ( $product['variations'] as $variation ) {
				$contents[] = [
					'type' => 'box',
					'layout' => 'baseline',
					'margin' => 'sm',
					'contents' => [
						[
							'type' => 'text',
							'text' => '• ' . $variation['variation_title'],
							'size' => 'sm',
							'color' => '#555555',
							'flex' => 2,
							'wrap' => true,
						],
						[
							'type' => 'text',
							'text' => '¥' . number_format( $variation['price'] ),
							'size' => 'sm',
							'color' => '#111111',
							'flex' => 1,
							'align' => 'end',
						],
						[
							'type' => 'text',
							'text' => $variation['quantity'] . '件',
							'size' => 'sm',
							'color' => '#999999',
							'flex' => 1,
							'align' => 'end',
						],
					],
				];
			}
		}

		$bubble = [
			'type' => 'bubble',
			'body' => [
				'type' => 'box',
				'layout' => 'vertical',
				'contents' => $contents,
			],
			'footer' => [
				'type' => 'box',
				'layout' => 'vertical',
				'contents' => [
					[
						'type' => 'button',
						'action' => [
							'type' => 'uri',
							'label' => '🛒 查看商品',
							'uri' => $product['url'],
						],
						'style' => 'primary',
						'color' => '#06C755',
					],
				],
			],
		];

		// 如果有圖片，加入 hero 區域
		if ( ! empty( $product['image_url'] ) ) {
			$bubble['hero'] = [
				'type' => 'image',
				'url' => $product['image_url'],
				'size' => 'full',
				'aspectRatio' => '1:1',
				'aspectMode' => 'cover',
			];
		}

		return $bubble;
	}

	/**
	 * 取得商品確認訊息 Flex Message（統一入口）
	 *
	 * @param array  $product 商品資料
	 * @param string $type 商品類型 (simple/variable)
	 * @return array Flex Message Bubble 格式
	 */
	public static function getProductConfirmation( $product, $type ) {
		if ( $type === 'variable' ) {
			return self::getVariableProductConfirmation( $product );
		} else {
			return self::getSimpleProductConfirmation( $product );
		}
	}
}
