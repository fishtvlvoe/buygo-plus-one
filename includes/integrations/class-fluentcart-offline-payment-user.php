<?php
/**
 * FluentCart Offline Payment User Creation Integration
 *
 * 修復 FluentCart 線下付款訂單不會自動建立使用者的問題
 *
 * @package BuygoPlus
 */

namespace BuygoPlus\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FluentCartOfflinePaymentUser
 *
 * 問題：FluentCart 預設只在 OrderPaid 事件觸發時建立使用者
 * 線下付款（offline_payment）訂單永遠不會觸發 OrderPaid 事件
 * 導致使用線下付款的顧客無法自動建立 WordPress 帳號
 *
 * 解決方案：監聽訂單建立事件，如果是線下付款且需要建立帳號，立即建立使用者
 */
class FluentCartOfflinePaymentUser {

	/**
	 * 線下付款方式列表
	 */
	const OFFLINE_PAYMENT_METHODS = [
		'offline_payment',  // FluentCart 內建線下付款
		'cod',              // 貨到付款
		'bank_transfer',    // 銀行轉帳
		'cash',             // 現金
	];

	/**
	 * 註冊 hooks
	 */
	public static function register_hooks(): void {
		// 監聽訂單建立事件（priority 20，晚於 FluentCart 原生處理）
		\add_action( 'fluent_cart/order_created', [ __CLASS__, 'handle_order_created' ], 20 );
	}

	/**
	 * 處理訂單建立事件
	 *
	 * @param object $order FluentCart 訂單物件
	 */
	public static function handle_order_created( $order ): void {
		// 只處理線下付款訂單
		if ( ! self::is_offline_payment( $order ) ) {
			return;
		}

		// 檢查是否需要建立使用者
		if ( ! self::should_create_user( $order ) ) {
			return;
		}

		// 建立使用者
		self::create_user_from_order( $order );
	}

	/**
	 * 檢查是否為線下付款訂單
	 *
	 * @param object $order 訂單物件
	 * @return bool
	 */
	private static function is_offline_payment( $order ): bool {
		$payment_method = $order->payment_method ?? '';

		return in_array( $payment_method, self::OFFLINE_PAYMENT_METHODS, true );
	}

	/**
	 * 檢查是否應該建立使用者
	 *
	 * 條件：
	 * 1. 訂單設定中啟用了「付款後建立帳號」
	 * 2. 或全局設定啟用了「自動建立使用者」
	 * 3. 且顧客尚未連結到 WordPress 使用者
	 *
	 * @param object $order 訂單物件
	 * @return bool
	 */
	private static function should_create_user( $order ): bool {
		global $wpdb;

		// 檢查訂單設定
		$config = is_string( $order->config ) ? json_decode( $order->config, true ) : (array) $order->config;
		$order_setting = $config['create_account_after_paid'] ?? 'no';

		// 檢查全局設定
		$global_setting = self::get_global_user_creation_setting();

		// 如果訂單或全局設定都不允許建立使用者，返回 false
		if ( $order_setting !== 'yes' && $global_setting !== 'all' ) {
			return false;
		}

		// 檢查顧客是否已連結到 WordPress 使用者
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->prefix}fct_customers WHERE id = %d",
			$order->customer_id
		) );

		// 如果已連結，不需要建立
		if ( $customer && $customer->user_id ) {
			return false;
		}

		return true;
	}

	/**
	 * 取得全局使用者建立設定
	 *
	 * @return string 'all'|'subscription'|'no'
	 */
	private static function get_global_user_creation_setting(): string {
		global $wpdb;

		$setting = $wpdb->get_var(
			"SELECT option_value FROM {$wpdb->prefix}fct_meta
			WHERE option_key = 'user_account_creation_mode'
			LIMIT 1"
		);

		return $setting ?? 'no';
	}

	/**
	 * 從訂單建立使用者
	 *
	 * 參考 FluentCart 原生邏輯：
	 * fluent-cart/app/Services/AuthService.php::createUserFromCustomer()
	 *
	 * @param object $order 訂單物件
	 */
	private static function create_user_from_order( $order ): void {
		global $wpdb;

		// 取得顧客資料
		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fct_customers WHERE id = %d",
			$order->customer_id
		) );

		if ( ! $customer || ! $customer->email ) {
			return;
		}

		// 檢查 email 是否已被使用
		if ( \email_exists( $customer->email ) ) {
			// Email 已存在，連結現有使用者
			$user = \get_user_by( 'email', $customer->email );
			if ( $user ) {
				self::link_customer_to_user( $customer->id, $user->ID );
			}
			return;
		}

		// 建立使用者名稱（從 email 或 full_name）
		$username = sanitize_user( $customer->email );
		if ( \username_exists( $username ) ) {
			// 如果使用者名稱已存在，加上隨機數字
			$username = $username . '_' . wp_rand( 100, 999 );
		}

		// 準備使用者資料
		$user_data = [
			'user_login' => $username,
			'user_email' => $customer->email,
			'user_pass'  => \wp_generate_password( 12, false ),
			'first_name' => $customer->first_name ?? '',
			'last_name'  => $customer->last_name ?? '',
			'role'       => 'customer', // FluentCart 預設角色
		];

		// 建立使用者
		$user_id = \wp_insert_user( $user_data );

		if ( \is_wp_error( $user_id ) ) {
			// 記錄錯誤
			error_log( sprintf(
				'[BuyGo+1] Failed to create user for customer #%d: %s',
				$customer->id,
				$user_id->get_error_message()
			) );
			return;
		}

		// 連結顧客到 WordPress 使用者
		self::link_customer_to_user( $customer->id, $user_id );

		// 發送密碼重設 email（讓顧客可以設定密碼）
		\wp_new_user_notification( $user_id, null, 'user' );

		// 記錄到訂單日誌
		self::add_order_note( $order->id, sprintf(
			'WordPress 使用者已自動建立（線下付款訂單）。使用者 ID: %d，Email: %s',
			$user_id,
			$customer->email
		) );

		// 記錄到 debug log
		error_log( sprintf(
			'[BuyGo+1] User created for offline payment order #%d. Customer: %s, User ID: %d',
			$order->id,
			$customer->email,
			$user_id
		) );
	}

	/**
	 * 連結顧客到 WordPress 使用者
	 *
	 * @param int $customer_id FluentCart 顧客 ID
	 * @param int $user_id WordPress 使用者 ID
	 */
	private static function link_customer_to_user( int $customer_id, int $user_id ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'fct_customers',
			[ 'user_id' => $user_id ],
			[ 'id' => $customer_id ],
			[ '%d' ],
			[ '%d' ]
		);
	}

	/**
	 * 新增訂單備註
	 *
	 * @param int $order_id 訂單 ID
	 * @param string $note 備註內容
	 */
	private static function add_order_note( int $order_id, string $note ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'fct_order_notes',
			[
				'order_id' => $order_id,
				'note'     => $note,
				'type'     => 'system',
				'created_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s' ]
		);
	}
}
