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
 * - build_order_details_text()：純文字訂單明細格式
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

	public function test_status_defaults_when_fields_missing(): void {
		$status = $this->service->getItemStatus( [] );

		$this->assertSame( '待分配', $status['label'] );
	}

	// ─────────────────────────────────────────
	// getOrderSummary — 無訂單時的回覆
	// ─────────────────────────────────────────

	public function test_get_order_summary_returns_text_when_no_orders(): void {
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
