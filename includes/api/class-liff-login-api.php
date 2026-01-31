<?php
/**
 * LIFF Login API
 *
 * 處理 LINE LIFF 登入的 REST API 端點
 *
 * @package BuyGo_Plus_One
 * @since 1.0.0
 */

namespace BuyGo_Plus_One\API;

defined( 'ABSPATH' ) || exit;

/**
 * LIFF_Login_API Class
 */
class LIFF_Login_API {

	/**
	 * Initialize the API
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public static function register_routes() {
		register_rest_route(
			'buygo/v1',
			'/liff-login',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_liff_login' ),
				'permission_callback' => '__return_true', // 公開端點,稍後驗證 Access Token
			)
		);

		register_rest_route(
			'buygo/v1',
			'/liff-config',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_liff_config' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle LIFF login request
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function handle_liff_login( $request ) {
		try {
			// 取得參數
			$line_user_id  = $request->get_param( 'user_id' );
			$display_name  = $request->get_param( 'display_name' );
			$picture_url   = $request->get_param( 'picture_url' );
			$email         = $request->get_param( 'email' );
			$access_token  = $request->get_param( 'access_token' );

			// 驗證必要參數
			if ( empty( $line_user_id ) ) {
				return new \WP_Error(
					'missing_user_id',
					'LINE User ID is required',
					array( 'status' => 400 )
				);
			}

			// 記錄日誌 (開發階段)
			error_log( sprintf(
				'[LIFF Login] Attempting login for LINE User ID: %s, Name: %s, Email: %s',
				$line_user_id,
				$display_name,
				$email ? $email : '(未提供)'
			) );

			// 檢查是否已綁定 WordPress 用戶
			$user = self::get_user_by_line_uid( $line_user_id );

			if ( $user ) {
				// 已綁定用戶,直接登入
				error_log( sprintf(
					'[LIFF Login] Found existing user: %s (ID: %d)',
					$user->user_login,
					$user->ID
				) );

				// 更新 LINE 資料
				update_user_meta( $user->ID, 'buygo_line_display_name', $display_name );
				update_user_meta( $user->ID, 'buygo_line_picture_url', $picture_url );
				update_user_meta( $user->ID, 'buygo_line_last_login', current_time( 'mysql' ) );

				// 如果有提供 Email 且用戶尚未設定,則更新
				if ( ! empty( $email ) && empty( $user->user_email ) ) {
					wp_update_user( array(
						'ID'         => $user->ID,
						'user_email' => $email,
					) );
					error_log( sprintf( '[LIFF Login] Updated email for user %d: %s', $user->ID, $email ) );
				}

				// 設定登入
				self::set_user_logged_in( $user->ID );

				return rest_ensure_response( array(
					'success'      => true,
					'message'      => '登入成功',
					'user_id'      => $user->ID,
					'display_name' => $user->display_name,
					'redirect_url' => home_url( '/' ),
				) );

			} else {
				// 檢查當前用戶是否已登入 WordPress
				if ( is_user_logged_in() ) {
					// 綁定到當前用戶
					$current_user_id = get_current_user_id();

					error_log( sprintf(
						'[LIFF Login] Binding LINE UID to current user: %d',
						$current_user_id
					) );

					update_user_meta( $current_user_id, 'buygo_line_user_id', $line_user_id );
					update_user_meta( $current_user_id, 'buygo_line_display_name', $display_name );
					update_user_meta( $current_user_id, 'buygo_line_picture_url', $picture_url );
					update_user_meta( $current_user_id, 'buygo_line_last_login', current_time( 'mysql' ) );

					// 如果有提供 Email,則更新
					if ( ! empty( $email ) ) {
						$current_user = get_userdata( $current_user_id );
						if ( empty( $current_user->user_email ) ) {
							wp_update_user( array(
								'ID'         => $current_user_id,
								'user_email' => $email,
							) );
						}
					}

					return rest_ensure_response( array(
						'success'      => true,
						'message'      => 'LINE 帳號綁定成功',
						'user_id'      => $current_user_id,
						'redirect_url' => home_url( '/' ),
					) );

				} else {
					// 建立新用戶
					error_log( '[LIFF Login] Creating new WordPress user' );

					$new_user_id = self::create_user_from_line(
						$line_user_id,
						$display_name,
						$picture_url,
						$email
					);

					if ( is_wp_error( $new_user_id ) ) {
						throw new \Exception( $new_user_id->get_error_message() );
					}

					// 設定登入
					self::set_user_logged_in( $new_user_id );

					return rest_ensure_response( array(
						'success'      => true,
						'message'      => '註冊並登入成功',
						'user_id'      => $new_user_id,
						'redirect_url' => home_url( '/' ),
					) );
				}
			}

		} catch ( \Exception $e ) {
			error_log( sprintf(
				'[LIFF Login] Error: %s',
				$e->getMessage()
			) );

			return new \WP_Error(
				'liff_login_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get LIFF configuration
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_liff_config() {
		// TODO: 從 WordPress 設定中讀取 LIFF ID
		$liff_id = get_option( 'buygo_liff_id', '' );

		return rest_ensure_response( array(
			'liff_id' => $liff_id,
			'api_url' => rest_url( 'buygo/v1/liff-login' ),
			'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
		) );
	}

	/**
	 * Get WordPress user by LINE UID
	 *
	 * @param string $line_user_id LINE User ID.
	 * @return \WP_User|false
	 */
	private static function get_user_by_line_uid( $line_user_id ) {
		$users = get_users( array(
			'meta_key'   => 'buygo_line_user_id',
			'meta_value' => $line_user_id,
			'number'     => 1,
		) );

		return ! empty( $users ) ? $users[0] : false;
	}

	/**
	 * Create WordPress user from LINE profile
	 *
	 * @param string $line_user_id LINE User ID.
	 * @param string $display_name Display name.
	 * @param string $picture_url  Picture URL.
	 * @return int|\WP_Error User ID or error.
	 */
	private static function create_user_from_line( $line_user_id, $display_name, $picture_url, $email = '' ) {
		// 生成唯一的用戶名
		$username = 'line_' . substr( $line_user_id, 0, 10 );
		$counter  = 1;

		while ( username_exists( $username ) ) {
			$username = 'line_' . substr( $line_user_id, 0, 10 ) . '_' . $counter;
			$counter++;
		}

		// 生成隨機密碼
		$password = wp_generate_password( 20, true, true );

		// 建立用戶 (如果有 Email,一併設定)
		if ( ! empty( $email ) ) {
			$user_id = wp_create_user( $username, $password, $email );
		} else {
			$user_id = wp_create_user( $username, $password );
		}

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// 更新用戶資料
		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $display_name,
			'nickname'     => $display_name,
		) );

		// 儲存 LINE 資料
		update_user_meta( $user_id, 'buygo_line_user_id', $line_user_id );
		update_user_meta( $user_id, 'buygo_line_display_name', $display_name );
		update_user_meta( $user_id, 'buygo_line_picture_url', $picture_url );
		update_user_meta( $user_id, 'buygo_line_registered_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, 'buygo_line_last_login', current_time( 'mysql' ) );

		error_log( sprintf(
			'[LIFF Login] Created new user: %s (ID: %d)',
			$username,
			$user_id
		) );

		return $user_id;
	}

	/**
	 * Set user as logged in
	 *
	 * @param int $user_id User ID.
	 */
	private static function set_user_logged_in( $user_id ) {
		// 清除舊的認證 Cookie
		wp_clear_auth_cookie();

		// 設定新的認證 Cookie
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		// 觸發登入 action
		$user = get_userdata( $user_id );
		do_action( 'wp_login', $user->user_login, $user );

		error_log( sprintf(
			'[LIFF Login] User logged in: %s (ID: %d)',
			$user->user_login,
			$user_id
		) );
	}
}

// Initialize
LIFF_Login_API::init();
