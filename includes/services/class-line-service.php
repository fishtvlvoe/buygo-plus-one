<?php
/**
 * LINE Service
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LineService
 *
 * 處理 LINE 使用者綁定與查詢
 * 遷移自舊外掛（buygo）的 LineService
 */
class LineService {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Debug Service
	 *
	 * @var DebugService
	 */
	private $debugService;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'buygo_line_bindings';
		$this->debugService = DebugService::get_instance();

		// Register cleanup hook
		add_action( 'buygo_daily_cleanup', [ $this, 'cleanup_expired_bindings' ] );
	}

	/**
	 * Get User ID by LINE UID.
	 *
	 * 查詢順序：
	 * 1. wp_buygo_line_bindings 資料表（優先）
	 * 2. wp_usermeta 表（_mygo_line_uid，向後相容）
	 * 3. wp_social_users 表（NSL 外掛）
	 *
	 * @param string $line_uid LINE User ID
	 * @return \WP_User|null WordPress User 物件，如果未找到則返回 null
	 */
	public function get_user_by_line_uid( $line_uid ) {
		global $wpdb;

		// 輸入驗證
		if ( empty( $line_uid ) ) {
			$this->debugService->log( 'LineService', '查詢使用者失敗：LINE UID 為空', [], 'warning' );
			return null;
		}

		$this->debugService->log( 'LineService', '開始查詢使用者', array(
			'line_uid' => $line_uid,
		) );

		try {
			// 1. Check buygo_line_bindings table first (優先)
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name;

			if ( $table_exists ) {
				$user_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT user_id FROM {$this->table_name} WHERE line_uid = %s AND status = 'completed' ORDER BY id DESC LIMIT 1",
					$line_uid
				) );

				if ( $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user ) {
						$this->debugService->log( 'LineService', '從 buygo_line_bindings 找到使用者', array(
							'line_uid' => $line_uid,
							'user_id' => $user_id,
						) );
						return $user;
					}
				}
			}

			// 2. Check wp_usermeta (向後相容，支援舊系統的 _mygo_line_uid)
			$meta_keys = [ '_mygo_line_uid', 'buygo_line_user_id', 'm_line_user_id', 'line_user_id' ];

			foreach ( $meta_keys as $meta_key ) {
				$user_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
					$meta_key,
					$line_uid
				) );

				if ( $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user ) {
						$this->debugService->log( 'LineService', '從 usermeta 找到使用者', array(
							'line_uid' => $line_uid,
							'user_id' => $user_id,
							'meta_key' => $meta_key,
						) );
						return $user;
					}
				}
			}

			// 3. Check NSL table (Nextend Social Login)
			$nsl_table = $wpdb->prefix . 'social_users';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$nsl_table'" ) === $nsl_table ) {
				$user_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT ID FROM {$nsl_table} WHERE identifier = %s AND type = 'line' LIMIT 1",
					$line_uid
				) );

				if ( $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user ) {
						$this->debugService->log( 'LineService', '從 NSL 找到使用者', array(
							'line_uid' => $line_uid,
							'user_id' => $user_id,
						) );
						return $user;
					}
				}
			}

			$this->debugService->log( 'LineService', '未找到使用者', array(
				'line_uid' => $line_uid,
			), 'warning' );

			return null;

		} catch ( \Exception $e ) {
			$this->debugService->log( 'LineService', '查詢使用者時發生錯誤', array(
				'line_uid' => $line_uid,
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			), 'error' );
			return null;
		}
	}

	/**
	 * Get LINE UID by WordPress User ID.
	 *
	 * @param int $user_id WordPress User ID
	 * @return string|null LINE UID，如果未綁定則返回 null
	 */
	public function get_line_uid( $user_id ) {
		global $wpdb;

		// 1. Check buygo_line_bindings table first (優先)
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name;
		
		if ( $table_exists ) {
			$line_uid = $wpdb->get_var( $wpdb->prepare(
				"SELECT line_uid FROM {$this->table_name} WHERE user_id = %d AND status = 'completed' ORDER BY id DESC LIMIT 1",
				$user_id
			) );

			if ( $line_uid ) {
				return $line_uid;
			}
		}

		// 2. Check wp_usermeta (向後相容)
		$meta_keys = [ '_mygo_line_uid', 'buygo_line_user_id', 'm_line_user_id', 'line_user_id' ];
		
		foreach ( $meta_keys as $meta_key ) {
			$line_uid = get_user_meta( $user_id, $meta_key, true );
			if ( ! empty( $line_uid ) ) {
				return $line_uid;
			}
		}

		// 3. Check NSL table
		$nsl_table = $wpdb->prefix . 'social_users';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$nsl_table'" ) === $nsl_table ) {
			$line_uid = $wpdb->get_var( $wpdb->prepare(
				"SELECT identifier FROM {$nsl_table} WHERE ID = %d AND type = 'line' LIMIT 1",
				$user_id
			) );
			
			if ( $line_uid ) {
				return $line_uid;
			}
		}

		return null;
	}

	/**
	 * Generate a binding code for a user.
	 *
	 * @param int $user_id WordPress User ID
	 * @return string|WP_Error Binding code or error
	 */
	public function generate_binding_code( $user_id ) {
		global $wpdb;

		$this->debugService->log( 'LineService', '開始產生綁定碼', array(
			'user_id' => $user_id,
		) );

		try {
			// 輸入驗證
			if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
				throw new \Exception( 'Invalid user ID' );
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				throw new \Exception( 'User not found' );
			}

			// Ensure table exists
			$this->maybe_create_table();

			$code = $this->generate_unique_code();
			$expires_at = date( 'Y-m-d H:i:s', strtotime( '+10 minutes' ) );

			$inserted = $wpdb->insert(
				$this->table_name,
				[
					'user_id' => $user_id,
					'binding_code' => $code,
					'status' => 'pending',
					'created_at' => current_time( 'mysql' ),
					'expires_at' => $expires_at,
				],
				[ '%d', '%s', '%s', '%s', '%s' ]
			);

			if ( $inserted === false ) {
				throw new \Exception( 'Database insert failed: ' . $wpdb->last_error );
			}

			$this->debugService->log( 'LineService', '綁定碼產生成功', array(
				'user_id' => $user_id,
				'code' => $code,
				'expires_at' => $expires_at,
			) );

			return $code;

		} catch ( \Exception $e ) {
			$this->debugService->log( 'LineService', '產生綁定碼失敗', array(
				'user_id' => $user_id,
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			), 'error' );

			return new \WP_Error( 'code_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Verify a binding code and link LINE UID.
	 *
	 * @param string $code Binding code
	 * @param string $line_uid LINE User ID
	 * @return array|WP_Error User ID and LINE UID, or error
	 */
	public function verify_binding_code( $code, $line_uid ) {
		global $wpdb;

		$this->debugService->log( 'LineService', '開始驗證綁定碼', array(
			'code' => $code,
			'line_uid' => $line_uid,
		) );

		try {
			// 輸入驗證
			if ( empty( $code ) ) {
				throw new \Exception( 'Binding code is empty' );
			}

			if ( empty( $line_uid ) ) {
				throw new \Exception( 'LINE UID is empty' );
			}

			$this->maybe_create_table();

			$binding = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE binding_code = %s ORDER BY id DESC LIMIT 1",
				$code
			) );

			if ( ! $binding ) {
				$this->debugService->log( 'LineService', '綁定碼不存在', array(
					'code' => $code,
				), 'warning' );
				throw new \Exception( 'Invalid binding code' );
			}

			if ( $binding->status !== 'pending' ) {
				$this->debugService->log( 'LineService', '綁定碼狀態無效', array(
					'code' => $code,
					'status' => $binding->status,
				), 'warning' );
				throw new \Exception( 'Code already used or expired' );
			}

			if ( strtotime( $binding->expires_at ) < time() ) {
				$wpdb->update( $this->table_name, [ 'status' => 'expired' ], [ 'id' => $binding->id ] );
				$this->debugService->log( 'LineService', '綁定碼已過期', array(
					'code' => $code,
					'expires_at' => $binding->expires_at,
				), 'warning' );
				throw new \Exception( 'Code expired' );
			}

			// Complete Binding
			$updated = $wpdb->update(
				$this->table_name,
				[
					'line_uid' => $line_uid,
					'status' => 'completed',
					'completed_at' => current_time( 'mysql' ),
				],
				[ 'id' => $binding->id ]
			);

			if ( $updated === false ) {
				throw new \Exception( 'Database update failed: ' . $wpdb->last_error );
			}

			$this->debugService->log( 'LineService', '綁定完成', array(
				'user_id' => $binding->user_id,
				'line_uid' => $line_uid,
				'code' => $code,
			) );

			// Fire Event for other services
			do_action( 'buygo_line_binding_completed', $binding->user_id, $line_uid );

			return [
				'user_id' => $binding->user_id,
				'line_uid' => $line_uid,
			];

		} catch ( \Exception $e ) {
			$this->debugService->log( 'LineService', '驗證綁定碼失敗', array(
				'code' => $code,
				'line_uid' => $line_uid,
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			), 'error' );

			$error_codes = [
				'Invalid binding code' => 'invalid_code',
				'Code already used or expired' => 'invalid_status',
				'Code expired' => 'expired_code',
			];

			$error_code = $error_codes[ $e->getMessage() ] ?? 'verification_failed';
			return new \WP_Error( $error_code, $e->getMessage() );
		}
	}

	/**
	 * Manually bind a user to a LINE UID.
	 *
	 * @param int $user_id WordPress User ID
	 * @param string $line_uid LINE User ID
	 * @return bool|WP_Error
	 */
	public function manual_bind( $user_id, $line_uid ) {
		global $wpdb;

		$this->debugService->log( 'LineService', '開始手動綁定', array(
			'user_id' => $user_id,
			'line_uid' => $line_uid,
		) );

		try {
			// 輸入驗證
			if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
				throw new \Exception( 'Invalid user ID' );
			}

			if ( empty( $line_uid ) ) {
				throw new \Exception( 'LINE UID is empty' );
			}

			$this->maybe_create_table();

			// Check if already bound
			$existing = $this->get_line_uid( $user_id );
			if ( $existing ) {
				if ( $existing === $line_uid ) {
					$this->debugService->log( 'LineService', '使用者已綁定相同的 LINE UID', array(
						'user_id' => $user_id,
						'line_uid' => $line_uid,
					), 'warning' );
					return true;
				}
			}

			$inserted = $wpdb->insert(
				$this->table_name,
				[
					'user_id' => $user_id,
					'binding_code' => 'manual-' . time(),
					'line_uid' => $line_uid,
					'status' => 'completed',
					'created_at' => current_time( 'mysql' ),
					'expires_at' => current_time( 'mysql' ),
					'completed_at' => current_time( 'mysql' ),
				],
				[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
			);

			if ( $inserted === false ) {
				throw new \Exception( 'Database insert failed: ' . $wpdb->last_error );
			}

			$this->debugService->log( 'LineService', '手動綁定成功', array(
				'user_id' => $user_id,
				'line_uid' => $line_uid,
			) );

			do_action( 'buygo_line_binding_completed', $user_id, $line_uid );

			return true;

		} catch ( \Exception $e ) {
			$this->debugService->log( 'LineService', '手動綁定失敗', array(
				'user_id' => $user_id,
				'line_uid' => $line_uid,
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			), 'error' );

			return new \WP_Error( 'manual_bind_failed', $e->getMessage() );
		}
	}

	/**
	 * Generate unique binding code.
	 *
	 * @return string
	 */
	private function generate_unique_code() {
		global $wpdb;

		$this->maybe_create_table();

		do {
			$code = str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE binding_code = %s AND status = 'pending'",
				$code
			) );
		} while ( $exists > 0 );

		return $code;
	}

	/**
	 * Cleanup expired bindings.
	 */
	public function cleanup_expired_bindings() {
		global $wpdb;

		$this->debugService->log( 'LineService', '開始清理過期綁定碼', [] );

		try {
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name;

			if ( $table_exists ) {
				$result = $wpdb->query( "UPDATE {$this->table_name} SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()" );

				$this->debugService->log( 'LineService', '清理過期綁定碼完成', array(
					'expired_count' => $result !== false ? $result : 0,
				) );
			} else {
				$this->debugService->log( 'LineService', '綁定表不存在，跳過清理', [], 'warning' );
			}
		} catch ( \Exception $e ) {
			$this->debugService->log( 'LineService', '清理過期綁定碼失敗', array(
				'error' => $e->getMessage(),
			), 'error' );
		}
	}

	/**
	 * Create database table if it doesn't exist.
	 */
	private function maybe_create_table() {
		global $wpdb;

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name;
		
		if ( $table_exists ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			line_uid varchar(100) NOT NULL,
			binding_code varchar(20),
			binding_code_expires_at datetime,
			status varchar(20) DEFAULT 'unbound',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			completed_at datetime,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY line_uid (line_uid),
			KEY binding_code (binding_code)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create table on plugin activation.
	 */
	public static function create_table() {
		$service = new self();
		$service->maybe_create_table();
	}
}
