<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\LineOrderQueryService;

/**
 * LineOrderQueryService 單元測試
 *
 * 測試純 PHP 邏輯，不依賴 WordPress / 資料庫環境。
 * 涵蓋：
 * - getItemStatus()：狀態判斷邏輯
 * - build_flex_message()：Flex Message 組裝格式
 * - getOrderSummary()：無訂單時回覆純文字
 */
class LineOrderQueryServiceTest extends TestCase {

	/** @var LineOrderQueryService */
	private $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new LineOrderQueryService();
	}

	// ─────────────────────────────────────────
	// getItemStatus — 狀態判斷邏輯
	// ─────────────────────────────────────────

	/**
	 * shipped_qty >= quantity → 已出貨 🚚
	 */
	public function test_status_shipped_when_shipped_qty_covers_quantity(): void {
		$item = [
			'quantity'        => 2,
			'shipped_qty'     => 2,
			'shipping_status' => 'shipped',
			'allocated_qty'   => 2,
		];

		$status = $this->service->getItemStatus( $item );

		$this->assertSame( '已出貨', $status['label'] );
		$this->assertSame( '🚚', $status['icon'] );
	}

	/**
	 * shipped_qty > quantity（超出也算已出貨）
	 */
	public function test_status_shipped_when_shipped_qty_exceeds_quantity(): void {
		$item = [
			'quantity'        => 1,
			'shipped_qty'     => 3,
			'shipping_status' => 'shipped',
			'allocated_qty'   => 0,
		];

		$status = $this->service->getItemStatus( $item );

		$this->assertSame( '已出貨', $status['label'] );
	}

	/**
	 * shipping_status = preparing → 備貨中 📦（在已出貨判斷之後）
	 */
	public function test_status_preparing_when_not_fully_shipped(): void {
		$item = [
			'quantity'        => 3,
			'shipped_qty'     => 1,
			'shipping_status' => 'preparing',
			'allocated_qty'   => 2,
		];

		$status = $this->service->getItemStatus( $item );

		$this->assertSame( '備貨中', $status['label'] );
		$this->assertSame( '📦', $status['icon'] );
	}

	/**
	 * allocated_qty > 0 且未備貨中 → 已分配 ✅
	 */
	public function test_status_allocated_when_allocated_qty_positive(): void {
		$item = [
			'quantity'        => 2,
			'shipped_qty'     => 0,
			'shipping_status' => 'unshipped',
			'allocated_qty'   => 1,
		];

		$status = $this->service->getItemStatus( $item );

		$this->assertSame( '已分配', $status['label'] );
		$this->assertSame( '✅', $status['icon'] );
	}

	/**
	 * shipped_qty > 0 但未達 quantity，且不是 preparing → 已分配
	 */
	public function test_status_allocated_when_partial_shipped(): void {
		$item = [
			'quantity'        => 3,
			'shipped_qty'     => 1,
			'shipping_status' => 'unshipped',
			'allocated_qty'   => 0,
		];

		$status = $this->service->getItemStatus( $item );

		$this->assertSame( '已分配', $status['label'] );
	}

	/**
	 * 什麼都沒有 → 待分配 ⏳
	 */
	public function test_status_pending_when_nothing_allocated(): void {
		$item = [
			'quantity'        => 2,
			'shipped_qty'     => 0,
			'shipping_status' => 'unshipped',
			'allocated_qty'   => 0,
		];

		$status = $this->service->getItemStatus( $item );

		$this->assertSame( '待分配', $status['label'] );
		$this->assertSame( '⏳', $status['icon'] );
	}

	/**
	 * 欄位缺失時也不會 fatal（防呆）
	 */
	public function test_status_defaults_when_fields_missing(): void {
		$status = $this->service->getItemStatus( [] );

		$this->assertSame( '待分配', $status['label'] );
	}

	// ─────────────────────────────────────────
	// build_flex_message — Flex Message 格式
	// ─────────────────────────────────────────

	/**
	 * 回傳的陣列必須有 type=flex、altText、contents.type=bubble
	 */
	public function test_build_flex_message_structure(): void {
		$box = [
			'type'     => 'box',
			'layout'   => 'vertical',
			'contents' => [],
		];

		$message = $this->service->build_flex_message( 2, [ $box ], 5800.0, '¥' );

		$this->assertSame( 'flex', $message['type'] );
		$this->assertStringContainsString( '2', $message['altText'] );
		$this->assertSame( 'bubble', $message['contents']['type'] );
	}

	/**
	 * altText 包含訂單數量
	 */
	public function test_build_flex_message_alt_text_contains_count(): void {
		$message = $this->service->build_flex_message( 3, [], 0.0, 'NT$' );

		$this->assertStringContainsString( '3', $message['altText'] );
		$this->assertStringContainsString( '3', $message['altText'] );
	}

	/**
	 * body contents 應包含：標題文字 + separator + order boxes + 合計
	 */
	public function test_build_flex_message_body_has_header_and_total(): void {
		$box = [
			'type'     => 'box',
			'layout'   => 'vertical',
			'contents' => [],
		];

		$message  = $this->service->build_flex_message( 1, [ $box ], 2900.0, '¥' );
		$body     = $message['contents']['body'];
		$contents = $body['contents'];

		// 第一個應是標題文字
		$this->assertSame( 'text', $contents[0]['type'] );
		$this->assertStringContainsString( '1', $contents[0]['text'] );

		// 第二個應是分隔線
		$this->assertSame( 'separator', $contents[1]['type'] );

		// 最後應包含合計文字
		$last_texts = array_filter( $contents, fn( $c ) => $c['type'] === 'text' );
		$total_found = false;
		foreach ( $last_texts as $t ) {
			if ( isset( $t['text'] ) && strpos( $t['text'], '2,900' ) !== false ) {
				$total_found = true;
				break;
			}
		}
		$this->assertTrue( $total_found, '合計金額應出現在 Flex Message 中' );
	}

	// ─────────────────────────────────────────
	// getOrderSummary — 無訂單時的回覆
	// ─────────────────────────────────────────

	/**
	 * getOrderSummary() 當資料庫回傳空陣列時，應回傳純文字「目前沒有進行中的訂單」
	 *
	 * 透過覆寫全域 $wpdb mock 的 get_results，回傳空陣列模擬無訂單情境。
	 */
	public function test_get_order_summary_returns_text_when_no_orders(): void {
		// 覆寫 mock wpdb：讓所有 get_results 都回傳空陣列
		$GLOBALS['wpdb'] = new class {
			public $prefix = 'wp_';

			public function prepare( $query, ...$args ) {
				return $query;
			}

			public function get_results( $query, $output = OBJECT ) {
				return [];
			}

			public function get_row( $query, $output = OBJECT ) {
				return null;
			}

			public function get_var( $query ) {
				return null;
			}
		};

		$result = $this->service->getOrderSummary( 999 );

		$this->assertSame( 'text', $result['type'] );
		$this->assertStringContainsString( '目前沒有進行中的訂單', $result['text'] );
	}
}
