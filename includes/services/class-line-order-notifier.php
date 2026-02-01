<?php
/**
 * Line Order Notifier
 *
 * @package BuyGoPlus
 */
 
namespace BuyGoPlus\Services;
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 檢查 buygo-line-notify 外掛是否啟用（在 admin_init 時檢查，確保所有外掛已載入）
add_action( 'admin_init', function() {
	if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo 'BuyGo+ Plus One 需要啟用 BuyGo Line Notify 外掛才能正常運作 LINE 訂單通知功能。';
			echo '</p></div>';
		} );
	}
} );
 
use FluentCart\App\Models\Order;
 
/**
 * Class LineOrderNotifier
 *
 * 負責：
 * - 監聽 FluentCart 訂單/出貨事件
 * - 延遲推播 + 重試（1 / 2 / 5 分鐘，最多 3 次）
 * - 去重（同一事件同一訂單只送一次）
 */
class LineOrderNotifier {
	/**
	 * Cron hook name.
	 */
	private const CRON_HOOK = 'buygo_plus_one_line_notify_attempt';
 
	/**
	 * Retry schedule seconds since trigger.
	 *
	 * attempt=1 => +60s, attempt=2 => +120s, attempt=3 => +300s
	 */
	private const RETRY_SCHEDULE = [ 60, 120, 300 ];
 
	/**
	 * Debug service.
	 *
	 * @var DebugService
	 */
	private $debugService;
 
	/**
	 * Constructor.
	 *
	 * @param bool $registerHooks Whether to register WordPress hooks.
	 */
	public function __construct( bool $registerHooks = true ) {
		$this->debugService = DebugService::get_instance();
 
		if ( $registerHooks ) {
			$this->registerHooks();
		}
	}
 
	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// FluentCart: order created.
		\add_action( 'fluent_cart/order_created', [ $this, 'onOrderCreated' ], 10, 1 );
 
		// FluentCart: shipping status changed to shipped.
		\add_action( 'fluent_cart/shipping_status_changed_to_shipped', [ $this, 'onOrderShipped' ], 10, 1 );
 
		// BuyGo internal hook (若未來由內部流程觸發 shipped 也能補到).
		\add_action( 'buygo_order_shipped', [ $this, 'onBuyGoOrderShipped' ], 10, 1 );
 
		// Cron attempt handler.
		\add_action( self::CRON_HOOK, [ self::class, 'handleAttempt' ], 10, 3 );
	}
 
	/**
	 * FluentCart hook: order created.
	 *
	 * @param array $eventData Event data.
	 */
	public function onOrderCreated( $eventData ): void {
		$order = $this->extractOrderFromEvent( $eventData );
		if ( ! $order ) {
			return;
		}
 
		$this->enqueueWithRetrySchedule( (int) $order->id, 'order_created' );
	}
 
	/**
	 * FluentCart hook: shipping status changed to shipped.
	 *
	 * @param array $eventData Event data.
	 */
	public function onOrderShipped( $eventData ): void {
		$order = $this->extractOrderFromEvent( $eventData );
		if ( ! $order ) {
			return;
		}
 
		$this->enqueueWithRetrySchedule( (int) $order->id, 'order_shipped' );
	}
 
	/**
	 * BuyGo hook: shipped.
	 *
	 * @param int|string $orderId Order ID.
	 */
	public function onBuyGoOrderShipped( $orderId ): void {
		$orderId = (int) $orderId;
		if ( $orderId <= 0 ) {
			return;
		}
 
		$this->enqueueWithRetrySchedule( $orderId, 'order_shipped' );
	}
 
	/**
	 * Enqueue attempt #1 and persist trigger time.
	 */
	private function enqueueWithRetrySchedule( int $orderId, string $event ): void {
		$order = Order::find( $orderId );
		if ( ! $order ) {
			return;
		}
 
		if ( self::isSent( $order, $event ) ) {
			return;
		}
 
		$triggerKey = self::triggerMetaKey( $event );
		$triggerAt  = (int) $order->getMeta( $triggerKey, 0 );
 
		if ( $triggerAt <= 0 ) {
			$triggerAt = time();
			$order->updateMeta( $triggerKey, (string) $triggerAt );
		}
 
		$runAt = $triggerAt + self::RETRY_SCHEDULE[0];
 
		self::scheduleAttempt( $orderId, $event, 1, $runAt );
	}
 
	/**
	 * Cron handler.
	 *
	 * @param int $orderId Order ID.
	 * @param string $event Event key.
	 * @param int $attempt Attempt number (1..3).
	 */
	public static function handleAttempt( $orderId, $event, $attempt ): void {
		$orderId = (int) $orderId;
		$attempt = (int) $attempt;
		$event   = (string) $event;
 
		$service = new self( false );
 
		$service->attemptSend( $orderId, $event, $attempt );
	}
 
	/**
	 * Attempt to send notification. If missing binding, schedule next attempt.
	 *
	 * Phase 31 更新：
	 * - 新訂單建立時：通知賣家 + 小幫手 + 買家
	 * - 訂單狀態變更時：僅通知買家
	 */
	private function attemptSend( int $orderId, string $event, int $attempt ): void {
		$order = Order::find( $orderId );
		if ( ! $order ) {
			return;
		}

		if ( self::isSent( $order, $event ) ) {
			return;
		}

		// 檢查 buygo-line-notify 是否啟用
		if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) || ! \BuygoLineNotify\BuygoLineNotify::is_active() ) {
			$this->recordAttempt( $order, $event, $attempt, 'plugin_not_active', 'BuyGo Line Notify 外掛未啟用' );
			return;
		}

		// 根據事件類型決定通知對象
		if ( $event === 'order_created' ) {
			// 新訂單：通知賣家 + 小幫手 + 買家
			$this->notifyOnOrderCreated( $order, $attempt );
		} else {
			// 訂單狀態變更（如 order_shipped）：僅通知買家
			$this->notifyBuyerOnly( $order, $event, $attempt );
		}
	}

	/**
	 * 新訂單建立時的通知邏輯
	 *
	 * Phase 31 需求：
	 * - ORD-01: 新訂單建立時，通知賣家
	 * - ORD-02: 新訂單建立時，通知所有已綁定 LINE 的小幫手
	 * - ORD-03: 新訂單建立時，通知買家（如果買家有 LINE 綁定）
	 *
	 * @param Order $order
	 * @param int $attempt
	 */
	private function notifyOnOrderCreated( Order $order, int $attempt ): void {
		$event = 'order_created';
		$notifiedAny = false;

		// 1. 取得訂單中的商品，找出賣家
		$sellerId = $this->getSellerIdFromOrder( $order );

		if ( $sellerId ) {
			// 準備賣家通知的模板參數
			$buyerName = $this->getBuyerNameFromOrder( $order );
			$sellerArgs = [
				'order_id' => (string) $order->id,
				'buyer_name' => $buyerName,
				'order_total' => $this->formatOrderTotal( $order ),
			];

			// 發送給賣家和小幫手
			$result = NotificationService::sendToSellerAndHelpers( $sellerId, 'seller_order_created', $sellerArgs );

			$this->debugService->log( 'LineOrderNotifier', '賣家/小幫手通知結果', [
				'order_id' => (int) $order->id,
				'seller_id' => $sellerId,
				'result' => $result,
			] );

			if ( $result['success'] > 0 ) {
				$notifiedAny = true;
			}
		}

		// 2. 通知買家
		$buyerNotified = $this->notifyBuyer( $order, $event, $attempt );
		if ( $buyerNotified ) {
			$notifiedAny = true;
		}

		// 如果有任何人被通知，標記為已發送
		if ( $notifiedAny ) {
			self::markSent( $order, $event );
			$this->recordAttempt( $order, $event, $attempt, 'sent', '推播成功（賣家/小幫手/買家）' );
		} else {
			// 沒有人被通知（可能都沒有綁定 LINE）
			$this->recordAttempt( $order, $event, $attempt, 'no_recipients', '沒有可通知的對象（都未綁定 LINE）' );
			// 不需要重試，標記為完成
			self::markSent( $order, $event );
		}
	}

	/**
	 * 僅通知買家（用於狀態變更通知）
	 *
	 * Phase 31 需求：
	 * - ORD-04: 訂單狀態變更時，僅通知買家
	 *
	 * @param Order $order
	 * @param string $event
	 * @param int $attempt
	 */
	private function notifyBuyerOnly( Order $order, string $event, int $attempt ): void {
		$buyerNotified = $this->notifyBuyer( $order, $event, $attempt );

		if ( $buyerNotified ) {
			self::markSent( $order, $event );
			$this->recordAttempt( $order, $event, $attempt, 'sent', '推播成功（買家）' );
		} else {
			// 買家沒有綁定 LINE，可能需要重試
			$this->maybeScheduleNextAttempt( $order, $event, $attempt );
		}
	}

	/**
	 * 通知買家
	 *
	 * @param Order $order
	 * @param string $event
	 * @param int $attempt
	 * @return bool 是否成功發送
	 */
	private function notifyBuyer( Order $order, string $event, int $attempt ): bool {
		$userId = $this->resolveWpUserIdFromOrder( $order );
		if ( ! $userId ) {
			$this->recordAttempt( $order, $event, $attempt, 'no_wp_user', '找不到買家對應的 WP 使用者' );
			return false;
		}

		// 檢查買家是否有 LINE 綁定
		if ( ! IdentityService::hasLineBinding( $userId ) ) {
			$this->recordAttempt( $order, $event, $attempt, 'buyer_no_line', '買家尚未綁定 LINE' );
			return false;
		}

		// 取得買家用的模板
		$templateKey = $event === 'order_shipped' ? 'order_shipped' : 'order_created';
		$args = [
			'order_id' => (string) $order->id,
			'total' => $this->formatOrderTotal( $order ),
		];

		// 發送通知
		$result = NotificationService::send( $userId, $templateKey, $args );

		$this->debugService->log( 'LineOrderNotifier', '買家通知結果', [
			'order_id' => (int) $order->id,
			'user_id' => $userId,
			'template_key' => $templateKey,
			'result' => $result,
		] );

		return $result;
	}

	/**
	 * 從訂單取得賣家 ID
	 *
	 * 邏輯：取得訂單中第一個商品的 post_author
	 *
	 * @param Order $order
	 * @return int|null
	 */
	private function getSellerIdFromOrder( Order $order ): ?int {
		try {
			$order->load( 'items' );
		} catch ( \Exception $e ) {
			// ignore
		}

		$items = $order->items ?? [];
		if ( empty( $items ) ) {
			return null;
		}

		// 取得第一個訂單項目的商品 ID
		foreach ( $items as $item ) {
			$productId = $item->product_id ?? 0;
			if ( $productId > 0 ) {
				$post = get_post( $productId );
				if ( $post && $post->post_author ) {
					return (int) $post->post_author;
				}
			}
		}

		return null;
	}

	/**
	 * 從訂單取得買家名稱
	 *
	 * @param Order $order
	 * @return string
	 */
	private function getBuyerNameFromOrder( Order $order ): string {
		try {
			$order->load( 'customer' );
		} catch ( \Exception $e ) {
			// ignore
		}

		$customer = $order->customer ?? null;
		if ( $customer ) {
			$name = trim( ( $customer->first_name ?? '' ) . ' ' . ( $customer->last_name ?? '' ) );
			if ( ! empty( $name ) ) {
				return $name;
			}
			if ( ! empty( $customer->email ) ) {
				return explode( '@', $customer->email )[0];
			}
		}

		return '顧客';
	}

	/**
	 * 格式化訂單金額
	 *
	 * @param Order $order
	 * @return string
	 */
	private function formatOrderTotal( Order $order ): string {
		$totalAmount = isset( $order->total_amount ) ? (float) $order->total_amount : 0;
		return number_format( $totalAmount / 100, 0, '.', ',' );
	}
 
	/**
	 * Schedule next attempt if any.
	 */
	private function maybeScheduleNextAttempt( Order $order, string $event, int $attempt ): void {
		$nextAttempt = $attempt + 1;
		if ( $nextAttempt > count( self::RETRY_SCHEDULE ) ) {
			return;
		}
 
		$triggerAt = (int) $order->getMeta( self::triggerMetaKey( $event ), 0 );
		if ( $triggerAt <= 0 ) {
			$triggerAt = time();
			$order->updateMeta( self::triggerMetaKey( $event ), (string) $triggerAt );
		}
 
		$runAt = $triggerAt + self::RETRY_SCHEDULE[ $nextAttempt - 1 ];
		if ( $runAt < time() ) {
			$runAt = time() + 5;
		}
 
		self::scheduleAttempt( (int) $order->id, $event, $nextAttempt, $runAt );
	}
 
	/**
	 * Schedule a single cron event if not scheduled yet.
	 */
	private static function scheduleAttempt( int $orderId, string $event, int $attempt, int $timestamp ): void {
		$args = [ $orderId, $event, $attempt ];
 
		if ( \wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return;
		}
 
		\wp_schedule_single_event( $timestamp, self::CRON_HOOK, $args );
	}
 
	/**
	 * Resolve WP user id from FluentCart order.
	 *
	 * @return int|null
	 */
	private function resolveWpUserIdFromOrder( Order $order ): ?int {
		try {
			$order->load( 'customer' );
		} catch ( \Exception $e ) {
			// ignore.
		}
 
		$customer = $order->customer ?? null;
		$userId   = null;
 
		if ( $customer && ! empty( $customer->user_id ) ) {
			$userId = (int) $customer->user_id;
		}
 
		if ( $userId > 0 ) {
			return $userId;
		}
 
		$email = $customer->email ?? '';
		if ( ! empty( $email ) ) {
			$user = \get_user_by( 'email', $email );
			if ( $user && ! empty( $user->ID ) ) {
				return (int) $user->ID;
			}
		}
 
		return null;
	}
 
	/**
	 * Build LINE message payload from NotificationTemplates.
	 *
	 * @return array|null LINE message object
	 */
	/**
	 * Get LINE message by trigger_condition-based templates.
	 *
	 * order_created: 使用既有的 order_created
	 * order_shipped: 使用 trigger_condition = order_shipped（需由既有模板設定提供）
	 */
	private function buildLineMessageFromTemplates( Order $order, string $event ): ?array {
		$args = [
			'order_id' => (string) $order->id,
		];
 
		$totalAmount = isset( $order->total_amount ) ? (float) $order->total_amount : 0;
		$args['total'] = number_format( $totalAmount / 100, 0, '.', ',' );

		$trigger = $event === 'order_shipped' ? 'order_shipped' : 'order_created';

		$templates = NotificationTemplates::get_by_trigger_condition( $trigger, $args );

		if ( empty( $templates ) || ! is_array( $templates ) ) {
			return null;
		}

		// 取第一則模板當推播內容（多則模板以後要做可配置的分段推播再加）
		$template = $templates[0] ?? null;
		if ( empty( $template ) || empty( $template['type'] ) ) {
			return null;
		}

		if ( $template['type'] === 'flex' ) {
			$flex = $template['line']['flex_template'] ?? [];
			$message = NotificationTemplates::build_flex_message( $flex );
			return $message ?: null;
		}

		$message = $template['line'] ?? null;
		if ( empty( $message ) || empty( $message['type'] ) ) {
			return null;
		}

		if ( $message['type'] === 'text' && trim( (string) ( $message['text'] ?? '' ) ) === '' ) {
			return null;
		}

		return $message;
	}
 
 
	/**
	 * Record attempt metadata and debug logs.
	 */
	private function recordAttempt( Order $order, string $event, int $attempt, string $status, string $message ): void {
		$order->updateMeta( self::attemptMetaKey( $event ), (string) $attempt );
		$order->updateMeta( self::statusMetaKey( $event ), $status );
		$order->updateMeta( self::lastErrorMetaKey( $event ), $message );
 
		$this->debugService->log( 'LineNotify', '訂單通知嘗試', [
			'order_id' => (int) $order->id,
			'event'    => $event,
			'attempt'  => $attempt,
			'status'   => $status,
			'message'  => $message,
		] );
	}
 
	/**
	 * Extract FluentCart Order from event array.
	 */
	private function extractOrderFromEvent( $eventData ): ?Order {
		if ( is_array( $eventData ) && isset( $eventData['order'] ) && $eventData['order'] instanceof Order ) {
			return $eventData['order'];
		}
 
		return null;
	}
 
	private static function sentMetaKey( string $event ): string {
		return 'buygo_line_notify_sent_' . $event;
	}
 
	private static function triggerMetaKey( string $event ): string {
		return 'buygo_line_notify_trigger_at_' . $event;
	}
 
	private static function attemptMetaKey( string $event ): string {
		return 'buygo_line_notify_attempt_' . $event;
	}
 
	private static function statusMetaKey( string $event ): string {
		return 'buygo_line_notify_status_' . $event;
	}
 
	private static function lastErrorMetaKey( string $event ): string {
		return 'buygo_line_notify_last_error_' . $event;
	}
 
	private static function isSent( Order $order, string $event ): bool {
		$value = $order->getMeta( self::sentMetaKey( $event ), '' );
		return ! empty( $value );
	}
 
	private static function markSent( Order $order, string $event ): void {
		$order->updateMeta( self::sentMetaKey( $event ), \current_time( 'mysql' ) );
	}
}

