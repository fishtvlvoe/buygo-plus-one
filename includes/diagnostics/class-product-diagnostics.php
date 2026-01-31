<?php
/**
 * Product Diagnostics
 *
 * 诊断工具：比对新旧外挂建立的商品差异
 * 用于找出「订阅产品 vs 实体产品」的问题根源
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProductDiagnostics
 */
class ProductDiagnostics {

	/**
	 * 运行完整诊断
	 */
	public static function run_full_diagnostics() {
		echo "=== BuyGo Plus One - 商品诊断工具 ===\n\n";

		self::check_fluentcart_version();
		self::check_database_tables();
		self::compare_products();
		self::analyze_latest_product();

		echo "\n=== 诊断完成 ===\n";
	}

	/**
	 * 检查 FluentCart 版本
	 */
	private static function check_fluentcart_version() {
		echo "【1】检查 FluentCart 版本\n";
		echo str_repeat('-', 50) . "\n";

		if ( ! class_exists( 'FluentCart\App\App' ) ) {
			echo "❌ FluentCart 未安装\n\n";
			return;
		}

		// 尝试获取版本号
		$version = defined( 'FLUENT_CART_VERSION' ) ? FLUENT_CART_VERSION : '未知';
		echo "✅ FluentCart 版本: {$version}\n\n";
	}

	/**
	 * 检查数据表结构
	 */
	private static function check_database_tables() {
		global $wpdb;

		echo "【2】检查数据表结构\n";
		echo str_repeat('-', 50) . "\n";

		$tables = array(
			'fct_product_details',
			'fct_product_variations',
		);

		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;

			// 检查表是否存在
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );

			if ( ! $exists ) {
				echo "❌ 表 {$table} 不存在\n";
				continue;
			}

			echo "✅ 表 {$table} 存在\n";

			// 获取表结构
			$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );
			echo "   欄位列表:\n";
			foreach ( $columns as $column ) {
				echo "   - {$column->Field} ({$column->Type})\n";
			}
			echo "\n";
		}
	}

	/**
	 * 比对新旧商品
	 */
	private static function compare_products() {
		global $wpdb;

		echo "【3】比对新旧外挂建立的商品\n";
		echo str_repeat('-', 50) . "\n";

		// 查找最近的商品（假设旧外挂和新外挂都有建立商品）
		$old_products = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_date
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'fluent-products'
			AND pm.meta_key = '_mygo_line_user_id'
			ORDER BY p.post_date DESC
			LIMIT 5"
		);

		if ( empty( $old_products ) ) {
			echo "❌ 找不到商品\n\n";
			return;
		}

		echo "找到 " . count( $old_products ) . " 个商品\n\n";

		foreach ( $old_products as $product ) {
			echo "商品 #{$product->ID}: {$product->post_title}\n";
			echo "建立时间: {$product->post_date}\n";

			// 获取商品详情
			$details = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}fct_product_details WHERE post_id = %d",
					$product->ID
				),
				ARRAY_A
			);

			if ( $details ) {
				echo "【Product Details】\n";
				echo "  - fulfillment_type: " . ( $details['fulfillment_type'] ?? 'NULL' ) . "\n";
				echo "  - variation_type: " . ( $details['variation_type'] ?? 'NULL' ) . "\n";
				echo "  - min_price: " . ( $details['min_price'] ?? 'NULL' ) . "\n";
				echo "  - max_price: " . ( $details['max_price'] ?? 'NULL' ) . "\n";
				echo "  - manage_stock: " . ( $details['manage_stock'] ?? 'NULL' ) . "\n";
				echo "  - stock_availability: " . ( $details['stock_availability'] ?? 'NULL' ) . "\n";

				// 检查是否有可能标记为订阅的欄位
				foreach ( $details as $key => $value ) {
					if ( stripos( $key, 'recurring' ) !== false ||
					     stripos( $key, 'subscription' ) !== false ||
					     stripos( $key, 'billing' ) !== false ) {
						echo "  - {$key}: {$value}\n";
					}
				}
			} else {
				echo "❌ 找不到 product_details\n";
			}

			// 获取变体
			$variations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}fct_product_variations WHERE post_id = %d",
					$product->ID
				),
				ARRAY_A
			);

			if ( $variations ) {
				echo "【Variations】\n";
				foreach ( $variations as $variation ) {
					echo "  - Variation #{$variation['id']}: {$variation['variation_title']}\n";
					echo "    价格: " . ( $variation['item_price'] ?? 'NULL' ) . "\n";
					echo "    库存: " . ( $variation['total_stock'] ?? 'NULL' ) . "\n";
					echo "    状态: " . ( $variation['item_status'] ?? 'NULL' ) . "\n";
				}
			} else {
				echo "❌ 找不到 variations\n";
			}

			// 获取所有 post_meta
			$meta = get_post_meta( $product->ID );
			echo "【Post Meta (订阅相关)】\n";
			foreach ( $meta as $key => $values ) {
				if ( stripos( $key, 'fct' ) !== false ||
				     stripos( $key, 'subscription' ) !== false ||
				     stripos( $key, 'recurring' ) !== false ||
				     stripos( $key, 'billing' ) !== false ||
				     stripos( $key, 'payment' ) !== false ) {
					echo "  - {$key}: " . print_r( $values, true );
				}
			}

			echo "\n" . str_repeat('-', 50) . "\n\n";
		}
	}

	/**
	 * 分析最新建立的商品
	 */
	private static function analyze_latest_product() {
		global $wpdb;

		echo "【4】分析最新建立的商品\n";
		echo str_repeat('-', 50) . "\n";

		$latest = $wpdb->get_row(
			"SELECT p.ID, p.post_title, p.post_date
			FROM {$wpdb->posts} p
			WHERE p.post_type = 'fluent-products'
			ORDER BY p.post_date DESC
			LIMIT 1"
		);

		if ( ! $latest ) {
			echo "❌ 找不到商品\n\n";
			return;
		}

		echo "最新商品: #{$latest->ID} - {$latest->post_title}\n";
		echo "建立时间: {$latest->post_date}\n\n";

		// 完整输出所有资料
		$details = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fct_product_details WHERE post_id = %d",
				$latest->ID
			),
			ARRAY_A
		);

		echo "【完整 Product Details】\n";
		if ( $details ) {
			foreach ( $details as $key => $value ) {
				$display_value = is_null( $value ) ? 'NULL' : $value;
				echo "  {$key}: {$display_value}\n";
			}
		}

		echo "\n【完整 Post Meta】\n";
		$all_meta = get_post_meta( $latest->ID );
		foreach ( $all_meta as $key => $values ) {
			echo "  {$key}: " . ( is_array( $values ) ? json_encode( $values ) : $values ) . "\n";
		}

		echo "\n";
	}

	/**
	 * 生成修复建议
	 */
	public static function generate_fix_recommendations( $product_id ) {
		global $wpdb;

		echo "【5】生成修复建议\n";
		echo str_repeat('-', 50) . "\n";

		$details = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fct_product_details WHERE post_id = %d",
				$product_id
			),
			ARRAY_A
		);

		if ( ! $details ) {
			echo "❌ 找不到商品详情\n";
			return;
		}

		$recommendations = array();

		// 检查 fulfillment_type
		if ( $details['fulfillment_type'] !== 'physical' ) {
			$recommendations[] = "❌ fulfillment_type 应该是 'physical'，当前是 '{$details['fulfillment_type']}'";
		} else {
			echo "✅ fulfillment_type 正确 (physical)\n";
		}

		// 检查 variation_type
		if ( ! in_array( $details['variation_type'], array( 'simple', 'variable' ), true ) ) {
			$recommendations[] = "❌ variation_type 应该是 'simple' 或 'variable'，当前是 '{$details['variation_type']}'";
		} else {
			echo "✅ variation_type 正确 ({$details['variation_type']})\n";
		}

		// 检查可能的订阅相关欄位
		$subscription_fields = array( 'billing_type', 'is_recurring', 'subscription_type' );
		foreach ( $subscription_fields as $field ) {
			if ( isset( $details[ $field ] ) && ! empty( $details[ $field ] ) ) {
				$recommendations[] = "⚠️ 发现订阅相关欄位: {$field} = {$details[$field]}";
			}
		}

		if ( empty( $recommendations ) ) {
			echo "\n✅ 商品设定看起来正确！\n";
		} else {
			echo "\n建议修正:\n";
			foreach ( $recommendations as $rec ) {
				echo $rec . "\n";
			}
		}

		echo "\n";
	}
}
