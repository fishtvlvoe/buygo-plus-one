<?php
/**
 * Diagnostics WP-CLI Command
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DiagnosticsCommand
 */
class DiagnosticsCommand {

	/**
	 * 注册 WP-CLI 命令
	 */
	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'buygo diagnose', array( __CLASS__, 'diagnose' ) );
		\WP_CLI::add_command( 'buygo compare-product', array( __CLASS__, 'compare_product' ) );
		\WP_CLI::add_command( 'buygo fix-product', array( __CLASS__, 'fix_product' ) );
	}

	/**
	 * 运行完整诊断
	 *
	 * ## 范例
	 *
	 *     wp buygo diagnose
	 *
	 * @when after_wp_load
	 */
	public static function diagnose( $args, $assoc_args ) {
		require_once __DIR__ . '/class-product-diagnostics.php';
		ProductDiagnostics::run_full_diagnostics();
	}

	/**
	 * 比对特定商品
	 *
	 * ## OPTIONS
	 *
	 * <product_id>
	 * : 商品 ID
	 *
	 * ## 范例
	 *
	 *     wp buygo compare-product 123
	 *
	 * @when after_wp_load
	 */
	public static function compare_product( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( '请提供商品 ID' );
			return;
		}

		$product_id = intval( $args[0] );

		require_once __DIR__ . '/class-product-diagnostics.php';
		ProductDiagnostics::generate_fix_recommendations( $product_id );
	}

	/**
	 * 修复商品设定（将订阅产品改为实体产品）
	 *
	 * ## OPTIONS
	 *
	 * <product_id>
	 * : 商品 ID
	 *
	 * [--dry-run]
	 * : 只显示会进行的修改，不实际执行
	 *
	 * ## 范例
	 *
	 *     wp buygo fix-product 123
	 *     wp buygo fix-product 123 --dry-run
	 *
	 * @when after_wp_load
	 */
	public static function fix_product( $args, $assoc_args ) {
		global $wpdb;

		if ( empty( $args[0] ) ) {
			\WP_CLI::error( '请提供商品 ID' );
			return;
		}

		$product_id = intval( $args[0] );
		$dry_run = isset( $assoc_args['dry-run'] );

		\WP_CLI::log( "正在检查商品 #{$product_id}..." );

		// 获取当前设定
		$details = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fct_product_details WHERE post_id = %d",
				$product_id
			),
			ARRAY_A
		);

		if ( ! $details ) {
			\WP_CLI::error( "找不到商品 #{$product_id} 的详情" );
			return;
		}

		\WP_CLI::log( "\n当前设定:" );
		\WP_CLI::log( "  fulfillment_type: " . ( $details['fulfillment_type'] ?? 'NULL' ) );
		\WP_CLI::log( "  variation_type: " . ( $details['variation_type'] ?? 'NULL' ) );

		// 准备更新
		$updates = array();

		if ( $details['fulfillment_type'] !== 'physical' ) {
			$updates['fulfillment_type'] = 'physical';
		}

		if ( ! in_array( $details['variation_type'], array( 'simple', 'variable' ), true ) ) {
			$updates['variation_type'] = 'simple';
		}

		// 检查并移除订阅相关欄位
		$subscription_fields = array( 'billing_type', 'is_recurring', 'subscription_type' );
		foreach ( $subscription_fields as $field ) {
			if ( isset( $details[ $field ] ) && ! empty( $details[ $field ] ) ) {
				$updates[ $field ] = null;
			}
		}

		if ( empty( $updates ) ) {
			\WP_CLI::success( '商品设定已经正确，无需修改' );
			return;
		}

		\WP_CLI::log( "\n" . ( $dry_run ? '将会' : '正在' ) . "进行以下修改:" );
		foreach ( $updates as $key => $value ) {
			$display_value = is_null( $value ) ? 'NULL' : $value;
			\WP_CLI::log( "  {$key}: " . ( $details[ $key ] ?? 'NULL' ) . " → {$display_value}" );
		}

		if ( $dry_run ) {
			\WP_CLI::log( "\n这是 dry-run 模式，不会实际修改。" );
			\WP_CLI::log( "移除 --dry-run 参数以执行修改。" );
			return;
		}

		// 执行更新
		$result = $wpdb->update(
			$wpdb->prefix . 'fct_product_details',
			$updates,
			array( 'post_id' => $product_id )
		);

		if ( $result === false ) {
			\WP_CLI::error( '更新失败: ' . $wpdb->last_error );
			return;
		}

		\WP_CLI::success( "商品 #{$product_id} 已成功修复为实体产品" );

		// 清除缓存
		wp_cache_delete( $product_id, 'posts' );
		\WP_CLI::log( '已清除缓存' );
	}
}

// 注册命令
DiagnosticsCommand::register();
