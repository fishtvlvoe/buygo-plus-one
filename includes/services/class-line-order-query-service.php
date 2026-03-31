<?php
/**
 * LINE 訂單查詢服務
 *
 * 處理用戶在 LINE 輸入 /訂單 時，查詢該用戶的進行中訂單，
 * 組裝 Flex Message 回覆。
 *
 * @package BuyGoPlus\Services
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LineOrderQueryService {

	/**
	 * 進行中訂單的排除狀態（這些狀態不顯示）
	 */
	const EXCLUDED_STATUSES = [ 'cancelled', 'refunded', 'completed' ];

	/**
	 * 取得用戶的進行中訂單摘要，並組裝 Flex Message
	 *
	 * @param int $user_id WordPress User ID
	 * @return array LINE Flex Message 陣列
	 */
	public function getOrderSummary( int $user_id ): array {
		global $wpdb;

		// 查詢進行中的父訂單（parent_id IS NULL，且狀態不在排除清單）
		// 狀態值為系統常數，直接組 IN 子句（無 SQL injection 風險）
		$excluded_list = "'" . implode( "','", self::EXCLUDED_STATUSES ) . "'";
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"SELECT o.id, o.status, o.total, o.currency
				 FROM {$wpdb->prefix}fct_orders o
				 WHERE o.customer_id = (
				     SELECT id FROM {$wpdb->prefix}fct_customers WHERE user_id = %d LIMIT 1
				 )
				 AND (o.parent_id IS NULL OR o.parent_id = 0)
				 AND o.status NOT IN ({$excluded_list})
				 ORDER BY o.id DESC
				 LIMIT 20",
				$user_id
			),
			ARRAY_A
		);

		// 沒有進行中訂單 → 回覆純文字
		if ( empty( $rows ) ) {
			return [
				'type' => 'text',
				'text' => '目前沒有進行中的訂單 📭',
			];
		}

		$order_count   = count( $rows );
		$grand_total   = 0.0;
		$currency      = '';
		$order_bubbles = [];

		foreach ( $rows as $order ) {
			$order_id       = (int) $order['id'];
			$order_total    = (float) $order['total'];
			$grand_total   += $order_total;
			$currency       = $order['currency'] ?? $currency;

			// 查詢該訂單的商品項目
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT oi.id, oi.title, oi.quantity,
					        oi.unit_price, oi.shipping_status,
					        COALESCE( (
					            SELECT SUM(oi2.quantity)
					            FROM {$wpdb->prefix}fct_order_items oi2
					            INNER JOIN {$wpdb->prefix}fct_orders o2 ON oi2.order_id = o2.id
					            WHERE o2.parent_id = %d
					            AND oi2.object_id = oi.object_id
					        ), 0 ) AS shipped_qty,
					        COALESCE(
					            (SELECT meta_value FROM {$wpdb->prefix}fct_order_item_meta
					             WHERE order_item_id = oi.id AND meta_key = '_allocated_qty' LIMIT 1),
					        0) AS allocated_qty
					 FROM {$wpdb->prefix}fct_order_items oi
					 WHERE oi.order_id = %d",
					$order_id,
					$order_id
				),
				ARRAY_A
			);

			if ( empty( $items ) ) {
				continue;
			}

			// 組裝每筆訂單的 Flex box
			$order_bubbles[] = $this->build_order_box( $order_id, $items, $order_total, $currency );
		}

		// 若所有訂單都沒有項目（理論上不應發生）
		if ( empty( $order_bubbles ) ) {
			return [
				'type' => 'text',
				'text' => '目前沒有進行中的訂單 📭',
			];
		}

		$currency_symbol = $this->get_currency_symbol( $currency );

		return $this->build_flex_message( $order_count, $order_bubbles, $grand_total, $currency_symbol );
	}

	/**
	 * 判斷單一訂單項目的顯示狀態
	 *
	 * 邏輯（依優先序）：
	 * 1. shipped_qty >= quantity → 已出貨 🚚
	 * 2. shipping_status = 'preparing' → 備貨中 📦
	 * 3. allocated_qty > 0 || shipped_qty > 0 → 已分配 ✅
	 * 4. else → 待分配 ⏳
	 *
	 * @param array $item 訂單項目資料（含 quantity, shipped_qty, shipping_status, allocated_qty）
	 * @return array ['label' => string, 'icon' => string]
	 */
	public function getItemStatus( array $item ): array {
		$quantity        = (int) ( $item['quantity'] ?? 1 );
		$shipped_qty     = (int) ( $item['shipped_qty'] ?? 0 );
		$shipping_status = $item['shipping_status'] ?? '';
		$allocated_qty   = (int) ( $item['allocated_qty'] ?? 0 );

		if ( $shipped_qty >= $quantity ) {
			return [ 'label' => '已出貨', 'icon' => '🚚' ];
		}

		if ( $shipping_status === 'preparing' ) {
			return [ 'label' => '備貨中', 'icon' => '📦' ];
		}

		if ( $allocated_qty > 0 || $shipped_qty > 0 ) {
			return [ 'label' => '已分配', 'icon' => '✅' ];
		}

		return [ 'label' => '待分配', 'icon' => '⏳' ];
	}

	/**
	 * 組裝單一訂單的 Flex box 內容
	 *
	 * @param int    $order_id   訂單 ID
	 * @param array  $items      商品項目
	 * @param float  $order_total 訂單小計
	 * @param string $currency   幣別代碼
	 * @return array Flex box 元件
	 */
	private function build_order_box( int $order_id, array $items, float $order_total, string $currency ): array {
		$currency_symbol = $this->get_currency_symbol( $currency );
		$contents        = [];

		// 訂單標題列
		$contents[] = [
			'type'   => 'text',
			'text'   => "#{$order_id}",
			'weight' => 'bold',
			'size'   => 'sm',
			'color'  => '#1E40AF',
			'margin' => 'md',
		];

		// 逐項商品
		foreach ( $items as $item ) {
			$status    = $this->getItemStatus( $item );
			$title     = $item['title'] ?? '商品';
			$qty       = (int) ( $item['quantity'] ?? 1 );
			$price     = (float) ( $item['unit_price'] ?? 0 );
			$subtotal  = $qty * $price;

			$contents[] = [
				'type'   => 'box',
				'layout' => 'vertical',
				'margin' => 'sm',
				'contents' => [
					[
						'type' => 'box',
						'layout' => 'horizontal',
						'contents' => [
							[
								'type'  => 'text',
								'text'  => "・{$title}",
								'wrap'  => true,
								'color' => '#333333',
								'size'  => 'sm',
								'flex'  => 5,
							],
							[
								'type'  => 'text',
								'text'  => "{$status['icon']} {$status['label']}",
								'color' => '#888888',
								'size'  => 'sm',
								'flex'  => 3,
								'align' => 'end',
							],
						],
					],
					[
						'type'  => 'text',
						'text'  => "  {$qty} × {$currency_symbol}" . number_format( $price ),
						'color' => '#888888',
						'size'  => 'xs',
						'margin' => 'xs',
					],
				],
			];
		}

		// 小計（多項商品才顯示）
		if ( count( $items ) > 1 ) {
			$contents[] = [
				'type'  => 'text',
				'text'  => "  小計：{$currency_symbol}" . number_format( $order_total ),
				'color' => '#555555',
				'size'  => 'xs',
				'align' => 'end',
				'margin' => 'sm',
			];
		}

		return [
			'type'     => 'box',
			'layout'   => 'vertical',
			'contents' => $contents,
		];
	}

	/**
	 * 組裝完整 Flex Message（bubble 格式）
	 *
	 * @param int    $order_count     訂單數量
	 * @param array  $order_bubbles   各訂單 box 陣列
	 * @param float  $grand_total     所有訂單合計
	 * @param string $currency_symbol 幣別符號
	 * @return array LINE Flex Message
	 */
	public function build_flex_message( int $order_count, array $order_bubbles, float $grand_total, string $currency_symbol ): array {
		// 組裝 body 的 contents：header + separator + 每筆訂單
		$body_contents = [
			[
				'type'   => 'text',
				'text'   => "📦 您目前有 {$order_count} 筆進行中訂單",
				'weight' => 'bold',
				'size'   => 'md',
				'wrap'   => true,
			],
			[
				'type'   => 'separator',
				'margin' => 'md',
			],
		];

		foreach ( $order_bubbles as $box ) {
			$body_contents[] = $box;
		}

		// 合計
		$body_contents[] = [
			'type'   => 'separator',
			'margin' => 'lg',
		];
		$body_contents[] = [
			'type'   => 'text',
			'text'   => "合計：{$currency_symbol}" . number_format( $grand_total ),
			'weight' => 'bold',
			'size'   => 'sm',
			'align'  => 'end',
			'margin' => 'md',
		];
		$body_contents[] = [
			'type'   => 'text',
			'text'   => '如有問題請聯絡客服 🙏',
			'color'  => '#888888',
			'size'   => 'xs',
			'align'  => 'center',
			'margin' => 'md',
		];

		return [
			'type'     => 'flex',
			'altText'  => "📦 您目前有 {$order_count} 筆進行中訂單",
			'contents' => [
				'type' => 'bubble',
				'body' => [
					'type'     => 'box',
					'layout'   => 'vertical',
					'contents' => $body_contents,
					'paddingAll' => 'lg',
				],
			],
		];
	}

	/**
	 * 將幣別代碼轉為顯示符號
	 *
	 * @param string $currency 幣別代碼（JPY、TWD、USD...）
	 * @return string 顯示符號
	 */
	private function get_currency_symbol( string $currency ): string {
		$map = [
			'JPY' => '¥',
			'TWD' => 'NT$',
			'USD' => 'US$',
			'CNY' => '¥',
			'EUR' => '€',
			'KRW' => '₩',
		];
		return $map[ strtoupper( $currency ) ] ?? $currency;
	}
}
