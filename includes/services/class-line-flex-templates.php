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
}
