<?php
/**
 * LINE Messaging Facade
 *
 * 統一的 LINE 訊息發送介面 - Reply Token 失效時自動切換到 Push API
 * 透過 LineHub MessagingService 發送所有訊息
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
	 * @param string       $line_uid    LINE user ID（Push fallback 用）
	 * @return bool
	 */
	public function send_reply( $reply_token, $message, $line_uid = null ) {
		if ( ! class_exists( '\\LineHub\\Messaging\\MessagingService' ) ) {
			$this->logger->log( 'error', array(
				'message' => 'LINE Hub MessagingService not available',
				'action'  => 'send_reply',
			), null, $line_uid );
			return false;
		}

		$messaging = new \LineHub\Messaging\MessagingService();
		$messages  = is_array( $message ) ? array( $message ) : array( array( 'type' => 'text', 'text' => $message ) );

		// 先嘗試 Reply
		$result = false;
		if ( ! empty( $reply_token ) ) {
			$result = $messaging->replyMessage( $reply_token, $messages );
		}

		// Reply 失敗 → Push fallback（需要 line_uid 轉 user_id）
		if ( ! $result || is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) ) {
				$this->logger->log( 'reply_failed_fallback_to_push', array(
					'error'    => $result->get_error_message(),
					'fallback' => 'push_message',
				), null, $line_uid );
			}

			if ( ! empty( $line_uid ) && class_exists( '\\LineHub\\Services\\UserService' ) ) {
				$user_id = \LineHub\Services\UserService::getUserByLineUid( $line_uid );
				if ( $user_id ) {
					$result = $messaging->pushMessage( $user_id, $messages );
					if ( is_wp_error( $result ) ) {
						$this->logger->log( 'error', array(
							'message' => 'Failed to send LINE message (both reply and push failed)',
							'error'   => $result->get_error_message(),
							'action'  => 'send_reply',
						), null, $line_uid );
						return false;
					}
				} else {
					$this->logger->log( 'error', array(
						'message' => 'Cannot push: LINE UID not bound to any user',
						'action'  => 'send_reply',
					), null, $line_uid );
					return false;
				}
			} else {
				$this->logger->log( 'error', array(
					'message' => 'Cannot send message: no reply token and no LINE UID',
					'action'  => 'send_reply',
				), null, $line_uid );
				return false;
			}
		}

		return ! is_wp_error( $result );
	}

	/**
	 * 發送 Flex Message（Reply Token 失效時自動切換到 Push API）
	 *
	 * @param string $reply_token  Reply token
	 * @param array  $flex_contents Flex Message 內容（bubble 或 carousel）
	 * @param string $line_uid     LINE user ID（Push fallback 用）
	 * @param string $alt_text     替代文字（不支援 Flex 的裝置顯示）
	 * @return bool
	 */
	public function send_flex( $reply_token, $flex_contents, $line_uid = null, $alt_text = '商品通知' ) {
		if ( ! class_exists( '\\LineHub\\Messaging\\MessagingService' ) ) {
			$this->logger->log( 'error', array(
				'message' => 'LINE Hub MessagingService not available',
				'action'  => 'send_flex',
			), null, $line_uid );
			return false;
		}

		$messaging    = new \LineHub\Messaging\MessagingService();
		$flex_message = array(
			'type'     => 'flex',
			'altText'  => $alt_text,
			'contents' => $flex_contents,
		);
		$messages = array( $flex_message );

		// 先嘗試 Reply
		$result = false;
		if ( ! empty( $reply_token ) ) {
			$result = $messaging->replyMessage( $reply_token, $messages );
		}

		// Reply 失敗 → Push fallback
		if ( ! $result || is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) ) {
				$this->logger->log( 'flex_reply_failed_fallback_to_push', array(
					'error'    => $result->get_error_message(),
					'fallback' => 'push_message',
				), null, $line_uid );
			}

			if ( ! empty( $line_uid ) && class_exists( '\\LineHub\\Services\\UserService' ) ) {
				$user_id = \LineHub\Services\UserService::getUserByLineUid( $line_uid );
				if ( $user_id ) {
					$result = $messaging->pushFlex( $user_id, $flex_message );
					if ( is_wp_error( $result ) ) {
						$this->logger->log( 'error', array(
							'message' => 'Failed to send Flex (both reply and push failed)',
							'error'   => $result->get_error_message(),
							'action'  => 'send_flex',
						), null, $line_uid );
						return false;
					}
				} else {
					$this->logger->log( 'error', array(
						'message' => 'Cannot push flex: LINE UID not bound to any user',
						'action'  => 'send_flex',
					), null, $line_uid );
					return false;
				}
			} else {
				$this->logger->log( 'error', array(
					'message' => 'Cannot send flex: no reply token and no LINE UID',
					'action'  => 'send_flex',
				), null, $line_uid );
				return false;
			}
		}

		return ! is_wp_error( $result );
	}

	/**
	 * 發送說明訊息
	 *
	 * @param string $reply_token Reply token
	 * @param string $line_uid    LINE user ID
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
