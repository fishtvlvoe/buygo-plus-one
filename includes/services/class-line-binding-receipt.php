<?php
/**
 * LINE Binding Receipt Block
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FluentCart\App\Models\Order;

/**
 * Class LineBindingReceipt
 *
 * 在 FluentCart 收據/完成頁插入「綁定碼」提示，讓買家把可推播的 source.userId 綁到 WP 帳號。
 */
class LineBindingReceipt {
	/**
	 * Debug service.
	 *
	 * @var DebugService
	 */
	private $debugService;

	public function __construct() {
		$this->debugService = DebugService::get_instance();

		$this->registerHooks();
	}

	private function registerHooks(): void {
		\add_action( 'fluent_cart/after_receipt_first_time', [ $this, 'render' ], 20, 1 );
	}

	/**
	 * Render binding code block.
	 *
	 * @param array $data Hook payload from FluentCart.
	 */
	public function render( $data ): void {
		$order = $data['order'] ?? null;
		if ( ! ( $order instanceof Order ) ) {
			return;
		}

		try {
			$order->load( 'customer' );
		} catch ( \Exception $e ) {
			// ignore.
		}

		// 如果已綁定就不顯示。
		$userId = $this->resolveWpUserIdFromOrder( $order );
		if ( ! $userId ) {
			return;
		}

		$existingLineUid = \BuyGoPlus\Core\BuyGoPlus_Core::line()->get_line_uid( (int) $userId );
		if ( ! empty( $existingLineUid ) ) {
			return;
		}

		$lineService = new LineService();
		$code = $lineService->generate_binding_code( (int) $userId );

		if ( \is_wp_error( $code ) ) {
			$this->debugService->log( 'LineBind', '產生綁定碼失敗', [
				'order_id' => (int) $order->id,
				'user_id'  => (int) $userId,
				'error'    => $code->get_error_message(),
			], 'error' );
			return;
		}

		$code = esc_html( (string) $code );

		// 簡單可讀，不依賴 CSS framework。
		echo '<div style="margin:16px 0;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#ffffff;max-width:680px">';
		echo '<div style="font-size:16px;font-weight:700;margin-bottom:8px">LINE 通知綁定</div>';
		echo '<div style="line-height:1.6;margin-bottom:10px">';
		echo '為了讓我們能把「下單成功 / 已出貨」推播到你的 LINE，請到 BuyGo 官方帳號輸入下面 6 位數綁定碼：';
		echo '</div>';
		echo '<div style="font-size:24px;font-weight:800;letter-spacing:4px;margin:10px 0">' . $code . '</div>';
		echo '<div style="font-size:13px;color:#6b7280;line-height:1.6">';
		echo '提示：加入官方帳號後，把綁定碼直接傳給官方帳號即可完成綁定。';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Resolve WP user id from FluentCart order (best effort).
	 *
	 * @return int|null
	 */
	private function resolveWpUserIdFromOrder( Order $order ): ?int {
		$customer = $order->customer ?? null;

		if ( $customer && ! empty( $customer->user_id ) ) {
			$userId = (int) $customer->user_id;
			if ( $userId > 0 ) {
				return $userId;
			}
		}

		$email = $customer->email ?? '';
		if ( ! empty( $email ) ) {
			$user = \get_user_by( 'email', $email );
			if ( $user && ! empty( $user->ID ) ) {
				return (int) $user->ID;
			}
		}

		$current = (int) \get_current_user_id();
		if ( $current > 0 ) {
			return $current;
		}

		return null;
	}
}

