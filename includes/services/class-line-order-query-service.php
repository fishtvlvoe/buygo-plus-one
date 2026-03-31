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

		$table_orders         = $wpdb->prefix . 'fct_orders';
		$table_items          = $wpdb->prefix . 'fct_order_items';
		$table_customers      = $wpdb->prefix . 'fct_customers';
		$table_variations     = $wpdb->prefix . 'fct_product_variations';
		$table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';

		// 用 email 對應 FluentCart customer_id（跟 FluentCartCustomerPortal 一致）
		$user           = get_userdata( $user_id );
		$customer_email = $user ? $user->user_email : '';

		$customer_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_customers} WHERE email = %s LIMIT 1",
			$customer_email
		) );

		if ( ! $customer_id ) {
			return [
				'type' => 'text',
				'text' => '目前沒有進行中的訂單',
			];
		}

		// 查詢進行中訂單的所有項目（跟 FluentCartCustomerPortal 同一套 SQL）
		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT o.id as order_id, o.invoice_no, o.shipping_status, o.currency,
			        oi.quantity, oi.unit_price, oi.object_id, oi.id as order_item_id,
			        oi.line_meta, oi.title,
			        pv.variation_title,
			        COALESCE(p.post_title, oi.title, '') as product_name,
			        COALESCE((SELECT SUM(si.quantity) FROM {$table_shipment_items} si WHERE si.order_item_id = oi.id), 0) as shipped_qty
			 FROM {$table_orders} o
			 INNER JOIN {$table_items} oi ON oi.order_id = o.id
			 LEFT JOIN {$table_variations} pv ON pv.id = oi.object_id
			 LEFT JOIN {$wpdb->posts} p ON p.ID = pv.post_id
			 WHERE o.customer_id = %d
			   AND o.parent_id IS NULL
			   AND o.status NOT IN ('cancelled', 'refunded', 'completed')
			 ORDER BY o.id DESC",
			$customer_id
		), ARRAY_A );

		if ( empty( $items ) ) {
			return [
				'type' => 'text',
				'text' => '目前沒有進行中的訂單',
			];
		}

		// 依訂單分組
		$grand_total   = 0.0;
		$currency      = '';
		$order_groups  = [];

		foreach ( $items as $item ) {
			$meta      = json_decode( $item['line_meta'] ?? '{}', true ) ?: [];
			$allocated = (int) ( $meta['_allocated_qty'] ?? 0 );
			$shipped   = max( (int) $item['shipped_qty'], (int) ( $meta['_shipped_qty'] ?? 0 ) );
			$qty       = (int) $item['quantity'];

			// 商品名稱與規格分開
			$product_name    = $item['product_name'] ?: '商品';
			$variation_title = $item['variation_title'] ?? '';

			// FluentCart 金額以分為單位
			$unit_price = (float) $item['unit_price'] / 100;
			$line_total = $unit_price * $qty;
			$grand_total += $line_total;
			$currency    = $item['currency'] ?: $currency;

			$order_id = $item['order_id'];
			if ( ! isset( $order_groups[ $order_id ] ) ) {
				$order_groups[ $order_id ] = [
					'invoice_no' => $item['invoice_no'] ?: "#{$order_id}",
					'items'      => [],
					'subtotal'   => 0,
				];
			}

			$order_groups[ $order_id ]['items'][] = [
				'title'           => $product_name,
				'variation_title' => $variation_title,
				'quantity'       => $qty,
				'unit_price'     => $unit_price,
				'shipped_qty'    => $shipped,
				'allocated_qty'  => $allocated,
				'shipping_status' => $item['shipping_status'] ?? '',
			];
			$order_groups[ $order_id ]['subtotal'] += $line_total;
		}

		$order_count     = count( $order_groups );
		$currency_symbol = $this->get_currency_symbol( $currency );

		// 組裝純文字訂單明細（供模板 {order_details} 變數使用）
		$order_details = $this->build_order_details_text( $order_groups, $currency_symbol );

		// 會員中心 URL（動態產生，對應賣家部署的網域）
		$account_url = function_exists( 'home_url' ) ? home_url( '/my-account/' ) : '/my-account/';

		// 走模板系統：後台可編輯的文字模板
		$result = NotificationTemplates::get( 'order_query', [
			'order_count'     => $order_count,
			'order_details'   => $order_details,
			'currency_symbol' => $currency_symbol,
			'total'           => number_format( $grand_total ),
			'account_url'     => $account_url,
		] );

		if ( $result && ! empty( $result['line']['text'] ) ) {
			return [
				'type' => 'text',
				'text' => $result['line']['text'],
			];
		}

		// Fallback：模板為空時直接組文字
		return [
			'type' => 'text',
			'text' => "您目前有 {$order_count} 筆進行中訂單\n\n{$order_details}\n\n合計：" . number_format( $grand_total ) . "\n\n查看完整訂單明細：\n{$account_url}\n\n如需客服協助，請直接在此回覆訊息",
		];
	}

	/**
	 * 組裝純文字訂單明細
	 *
	 * @param array  $order_groups   依訂單分組的資料
	 * @param string $currency_symbol 幣別符號
	 * @return string 純文字格式的訂單明細
	 */
	private function build_order_details_text( array $order_groups, string $currency_symbol ): string {
		$blocks = [];
		$seq    = 1;

		foreach ( $order_groups as $group ) {
			foreach ( $group['items'] as $item ) {
				$status    = $this->getItemStatus( $item );
				$title     = $item['title'] ?? '商品';
				$variation = $item['variation_title'] ?? '';
				$qty       = (int) ( $item['quantity'] ?? 1 );
				$price     = (float) ( $item['unit_price'] ?? 0 );
				$subtotal  = number_format( $price * $qty );
				$price_f   = number_format( $price );

				$block   = [];
				$block[] = "編號：[{$seq}] {$group['invoice_no']}";
				$block[] = "產品：{$title}";
				if ( ! empty( $variation ) ) {
					$block[] = "規格：{$variation}";
				}
				$block[] = "下單：{$price_f} x {$qty} = {$subtotal}";
				$block[] = "狀態：{$status['label']}";

				$blocks[] = implode( "\n", $block );
				$seq++;
			}
		}

		return implode( "\n──────────────\n", $blocks );
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
	private function build_order_box( string $invoice_no, array $items, float $order_total, string $currency_symbol ): array {
		$contents = [];

		// 訂單標題列
		$contents[] = [
			'type'   => 'text',
			'text'   => $invoice_no,
			'weight' => 'bold',
			'size'   => 'sm',
			'color'  => '#1E40AF',
			'margin' => 'md',
		];

		// 逐項商品
		foreach ( $items as $item ) {
			$status = $this->getItemStatus( $item );
			$title  = $item['title'] ?? '商品';
			$qty    = (int) ( $item['quantity'] ?? 1 );
			$price  = (float) ( $item['unit_price'] ?? 0 );

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
