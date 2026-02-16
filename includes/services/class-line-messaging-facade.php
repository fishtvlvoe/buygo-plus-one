<?php
/**
 * LINE Messaging Facade
 *
 * 統一的 LINE 訊息發送介面 - Reply Token 失效時自動切換到 Push API
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LineMessagingFacade
 *
 * 職責：
 * - 智能訊息發送（Reply → Push fallback）
 * - 發送說明訊息
 */
class LineMessagingFacade {

	/**
	 * Webhook Logger
	 *
	 * @var WebhookLogger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = WebhookLogger::get_instance();
	}

	/**
	 * 發送訊息（Reply Token 失效時自動切換到 Push API）
	 *
	 * @param string       $reply_token Reply token
	 * @param string|array $message     Message content
	 * @param string       $line_uid    LINE user ID (optional, for logging)
	 * @return bool
	 */
	public function send_reply( $reply_token, $message, $line_uid = null ) {
		// 優先使用 LineHub MessagingService
		if ( class_exists( '\\LineHub\\Messaging\\MessagingService' ) ) {
			$messages = is_array( $message ) ? array( $message ) : array( array( 'type' => 'text', 'text' => $message ) );
			return $this->send_via_linehub( $messages, $reply_token, $line_uid, 'send_reply' );
		}

		// Fallback: buygo-line-notify
		// 檢查 buygo-line-notify 是否啟用
		if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) || ! \BuygoLineNotify\BuygoLineNotify::is_active() ) {
			$this->logger->log( 'error', array(
				'message' => 'BuyGo Line Notify plugin is not active, cannot send reply',
				'action' => 'send_reply',
			), null, $line_uid );
			return false;
		}

		// 使用 buygo-line-notify 的 LineMessagingService
		$messaging = \BuygoLineNotify\BuygoLineNotify::messaging();

		// 先嘗試 Reply（如果 Token 有效）
		$result = false;
		if ( ! empty( $reply_token ) ) {
			$result = $messaging->send_reply( $reply_token, $message, $line_uid );
		}

		// 如果 Reply 失敗（Token 無效、為空、或返回 false），改用 Push Message
		if ( ! $result || is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) ) {
				$this->logger->log( 'reply_failed_fallback_to_push', array(
					'error' => $result->get_error_message(),
					'fallback' => 'push_message',
				), null, $line_uid );
			}

			// 確保有 LINE UID 才能使用 Push
			if ( ! empty( $line_uid ) ) {
				// 將訊息包裝成 LINE 訊息格式
				$push_message = is_array( $message ) ? $message : array(
					'type' => 'text',
					'text' => $message,
				);

				$result = $messaging->push_message( $line_uid, $push_message );

				if ( is_wp_error( $result ) ) {
					$this->logger->log( 'error', array(
						'message' => 'Failed to send LINE message (both reply and push failed)',
						'error' => $result->get_error_message(),
						'action' => 'send_reply',
					), null, $line_uid );
					return false;
				}
			} else {
				$this->logger->log( 'error', array(
					'message' => 'Cannot send message: no reply token and no LINE UID',
					'action' => 'send_reply',
				), null, $line_uid );
				return false;
			}
		}

		return $result;
	}

	/**
	 * 發送 Flex Message（Reply Token 失效時自動切換到 Push API）
	 *
	 * @param string $reply_token Reply token
	 * @param array  $flex_contents Flex Message 內容（bubble 或 carousel）
	 * @param string $line_uid LINE user ID（Push fallback 用）
	 * @param string $alt_text 替代文字（不支援 Flex 的裝置顯示）
	 * @return bool
	 */
	public function send_flex( $reply_token, $flex_contents, $line_uid = null, $alt_text = '商品通知' ) {
		// 檢查 buygo-line-notify 是否啟用
		if ( ! class_exists( '\BuygoLineNotify\BuygoLineNotify' ) || ! \BuygoLineNotify\BuygoLineNotify::is_active() ) {
			$this->logger->log( 'error', array(
				'message' => 'BuyGo Line Notify plugin is not active, cannot send flex',
				'action' => 'send_flex',
			), null, $line_uid );
			return false;
		}

		$messaging = \BuygoLineNotify\BuygoLineNotify::messaging();

		// 先嘗試 Reply
		$result = false;
		if ( ! empty( $reply_token ) ) {
			$result = $messaging->replyFlex( $reply_token, $flex_contents, $alt_text );
		}

		// Reply 失敗 → Push fallback
		if ( ! $result || is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) ) {
				$this->logger->log( 'flex_reply_failed_fallback_to_push', array(
					'error' => $result->get_error_message(),
					'fallback' => 'push_message',
				), null, $line_uid );
			}

			if ( ! empty( $line_uid ) ) {
				$push_message = array(
					'type'     => 'flex',
					'altText'  => $alt_text,
					'contents' => $flex_contents,
				);
				$result = $messaging->push_message( $line_uid, $push_message );

				if ( is_wp_error( $result ) ) {
					$this->logger->log( 'error', array(
						'message' => 'Failed to send Flex (both reply and push failed)',
						'error' => $result->get_error_message(),
						'action' => 'send_flex',
					), null, $line_uid );
					return false;
				}
			} else {
				$this->logger->log( 'error', array(
					'message' => 'Cannot send flex: no reply token and no LINE UID',
					'action' => 'send_flex',
				), null, $line_uid );
				return false;
			}
		}

		return $result;
	}

	/**
	 * 發送說明訊息
	 *
	 * @param string $reply_token Reply token
	 * @return bool
	 */
	public function send_help( $reply_token, $line_uid = null ) {
		$message  = "📱 商品上架說明\n\n";
		$message .= "【步驟】\n";
		$message .= "1️⃣ 發送商品圖片\n";
		$message .= "2️⃣ 發送商品資訊\n\n";
		$message .= "【必填欄位】\n";
		$message .= "商品名稱\n";
		$message .= "價格：350\n";
		$message .= "數量：20\n\n";
		$message .= "💡 輸入 /分類 查看可用分類";

		return $this->send_reply( $reply_token, $message, $line_uid );
	}
}
