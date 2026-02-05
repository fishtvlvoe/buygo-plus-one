<?php
/**
 * Webhook Logger
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebhookLogger
 *
 * Logs all Webhook activities for tracking and debugging
 */
class WebhookLogger {

	/**
	 * Logger instance
	 *
	 * @var WebhookLogger
	 */
	private static $instance = null;

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Get logger instance
	 *
	 * @return WebhookLogger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'buygo_webhook_logs';
	}

	/**
	 * Log webhook event
	 *
	 * @param string $event_type Event type (e.g., 'image_upload', 'product_created', 'error')
	 * @param array  $event_data Event data (will be JSON encoded)
	 * @param int    $user_id WordPress user ID (optional)
	 * @param string $line_user_id LINE user ID (optional)
	 * @return int|false Log ID on success, false on failure
	 */
	public function log( $event_type, $event_data = array(), $user_id = null, $line_user_id = null ) {
		global $wpdb;

		// Ensure table exists
		$this->maybe_create_table();

		$data = array(
			'event_type'  => sanitize_text_field( $event_type ),
			'event_data'  => wp_json_encode( $event_data ),
			'user_id'     => $user_id ? intval( $user_id ) : null,
			'line_user_id' => $line_user_id ? sanitize_text_field( $line_user_id ) : null,
			'created_at'  => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			// Log database error for debugging
			if ( ! empty( $wpdb->last_error ) ) {
				error_log( sprintf(
					'[BuyGoPlus] WebhookLogger INSERT failed: %s | Query: %s',
					$wpdb->last_error,
					$wpdb->last_query
				) );
			}
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get logs
	 *
	 * @param array $args Query arguments
	 * @return array Log entries
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'event_type'  => '',
			'user_id'     => null,
			'line_user_id' => '',
			'limit'       => 50,
			'offset'      => 0,
			'order_by'    => 'created_at',
			'order'       => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = 'event_type = %s';
			$where_values[] = $args['event_type'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$where_values[] = intval( $args['user_id'] );
		}

		if ( ! empty( $args['line_user_id'] ) ) {
			$where[] = 'line_user_id = %s';
			$where_values[] = $args['line_user_id'];
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $where_values ) ) {
			$where_clause = $wpdb->prepare( $where_clause, $where_values );
		}

		$order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
		if ( ! $order_by ) {
			$order_by = 'created_at DESC';
		}

		$limit = intval( $args['limit'] );
		$offset = intval( $args['offset'] );

		// Use $wpdb->prepare for LIMIT and OFFSET to follow WordPress standards
		$query = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d",
			$limit,
			$offset
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Decode JSON data
		foreach ( $results as &$result ) {
			if ( ! empty( $result['event_data'] ) ) {
				$result['event_data'] = json_decode( $result['event_data'], true );
			}
		}

		return $results;
	}

	/**
	 * Get log count
	 *
	 * @param array $args Query arguments
	 * @return int Log count
	 */
	public function get_log_count( $args = array() ) {
		global $wpdb;

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = 'event_type = %s';
			$where_values[] = $args['event_type'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$where_values[] = intval( $args['user_id'] );
		}

		if ( ! empty( $args['line_user_id'] ) ) {
			$where[] = 'line_user_id = %s';
			$where_values[] = $args['line_user_id'];
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $where_values ) ) {
			$where_clause = $wpdb->prepare( $where_clause, $where_values );
		}

		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get statistics
	 *
	 * @param string $period Period: 'today', 'week', 'month'
	 * @return array Statistics
	 */
	public function get_statistics( $period = 'today' ) {
		global $wpdb;

		$date_format = '';
		switch ( $period ) {
			case 'today':
				$date_format = '%Y-%m-%d';
				$date_condition = "DATE(created_at) = CURDATE()";
				break;
			case 'week':
				$date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
				break;
			case 'month':
				$date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
				break;
			default:
				$date_condition = "1=1";
		}

		$query = "SELECT 
			event_type,
			COUNT(*) as count
		FROM {$this->table_name}
		WHERE {$date_condition}
		GROUP BY event_type";

		$results = $wpdb->get_results( $query, ARRAY_A );

		$stats = array();
		foreach ( $results as $result ) {
			$stats[ $result['event_type'] ] = intval( $result['count'] );
		}

		return $stats;
	}

	/**
	 * Create database table if it doesn't exist
	 */
	private function maybe_create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			event_type VARCHAR(50) NOT NULL,
			event_data TEXT,
			user_id BIGINT UNSIGNED,
			line_user_id VARCHAR(100),
			created_at DATETIME NOT NULL,
			INDEX idx_event_type (event_type),
			INDEX idx_created_at (created_at),
			INDEX idx_line_user_id (line_user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create table on plugin activation
	 */
	public static function create_table() {
		$logger = self::get_instance();
		$logger->maybe_create_table();
	}
}
