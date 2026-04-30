<?php
/**
 * WP-CLI 一次性修復腳本：修正多變體商品子訂單 object_id 錯標問題
 *
 * 用法：
 *   wp buygo fix-cross-variant-child-orders --post-id=2650 --dry-run
 *   wp buygo fix-cross-variant-child-orders --post-id=2650 --commit --purchased-d=50
 *
 * 背景：commit 3926231 修復了分配邏輯，但資料庫中既有的錯標子訂單需手動還原。
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * 修復跨變體子訂單錯標問題
 */
class BuyGo_Fix_Cross_Variant_Child_Orders_Command {

	/**
	 * 修復子訂單 object_id 錯標問題
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : 只印計畫，不動 DB（預設行為）
	 *
	 * [--commit]
	 * : 包在 transaction 內實際改 DB（與 --dry-run 互斥）
	 *
	 * [--post-id=<id>]
	 * : 限定處理某商品 ID（必填）
	 *
	 * [--purchased-d=<n>]
	 * : D 變體（缺 _buygo_purchased meta 的變體）要補幾個採購數
	 *
	 * ## EXAMPLES
	 *
	 *     wp buygo fix-cross-variant-child-orders --post-id=2650 --dry-run
	 *     wp buygo fix-cross-variant-child-orders --post-id=2650 --commit --purchased-d=50
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		// ── 參數解析與互斥驗證 ──────────────────────────────────────
		$has_dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$has_commit  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'commit', false );

		if ( $has_dry_run && $has_commit ) {
			WP_CLI::error( '--dry-run and --commit are mutually exclusive.' );
		}

		// 兩者都沒給 → 預設 dry-run
		$is_commit = $has_commit;

		if ( empty( $assoc_args['post-id'] ) ) {
			WP_CLI::error( '--post-id is required. Full-table scan is not allowed.' );
		}
		$post_id = (int) $assoc_args['post-id'];
		if ( $post_id <= 0 ) {
			WP_CLI::error( 'Invalid --post-id value.' );
		}

		$purchased_d = isset( $assoc_args['purchased-d'] ) ? (int) $assoc_args['purchased-d'] : null;

		WP_CLI::log( sprintf(
			'Mode: %s | post_id: %d',
			$is_commit ? 'COMMIT' : 'DRY-RUN',
			$post_id
		) );

		// ── Step 1：撈所有 active 變體 ──────────────────────────────
		$variation_ids = $this->get_active_variation_ids( $post_id );
		if ( empty( $variation_ids ) ) {
			WP_CLI::error( sprintf( 'No active variations found for post_id=%d.', $post_id ) );
		}
		WP_CLI::log( sprintf( 'Found %d active variation(s): %s', count( $variation_ids ), implode( ', ', $variation_ids ) ) );

		// ── Step 2：偵測缺 meta 的變體 ──────────────────────────────
		$missing_meta_variation_ids = $this->get_variations_missing_purchased_meta( $variation_ids );

		// 驗證 purchased-d 參數
		if ( ! empty( $missing_meta_variation_ids ) ) {
			if ( count( $missing_meta_variation_ids ) > 1 ) {
				WP_CLI::error( sprintf(
					'Multiple variations are missing _buygo_purchased meta: [%s]. ' .
					'Cannot blindly apply --purchased-d to all. Please fix manually.',
					implode( ', ', $missing_meta_variation_ids )
				) );
			}
			// 只有一個缺 meta 的變體
			if ( $is_commit && null === $purchased_d ) {
				WP_CLI::error(
					'One variation is missing _buygo_purchased meta but --purchased-d was not provided. ' .
					'Add --purchased-d=<n> to supply the value.'
				);
			}
		}

		// ── Step 3：偵測錯標子訂單 ──────────────────────────────────
		$child_repair_plan = $this->detect_mislabeled_child_orders( $post_id, $variation_ids );

		// ── Step 4：計算修復後的 allocated 總數 ─────────────────────
		$new_allocated = $this->calculate_new_allocated( $post_id, $variation_ids, $child_repair_plan );

		// ── Step 5：輸出計畫 ────────────────────────────────────────
		$this->print_child_repair_plan( $child_repair_plan );
		$this->print_meta_repair_plan( $missing_meta_variation_ids, $purchased_d );

		$n_child = count( $child_repair_plan );
		$m_meta  = count( $missing_meta_variation_ids );
		WP_CLI::log( '' );
		WP_CLI::log( sprintf(
			'Plan: %d child order(s) to relabel, %d variation meta(s) to insert, post_meta._buygo_allocated will be set to %d',
			$n_child,
			$m_meta,
			$new_allocated
		) );

		if ( ! $is_commit ) {
			WP_CLI::log( 'Run with --commit to apply.' );
			return;
		}

		// ── Step 6：COMMIT 模式實際執行 ─────────────────────────────
		$this->execute_repair(
			$post_id,
			$child_repair_plan,
			$missing_meta_variation_ids,
			$purchased_d,
			$new_allocated
		);
	}

	// ════════════════════════════════════════════════════════════════
	// 私有方法：資料撈取
	// ════════════════════════════════════════════════════════════════

	/**
	 * 撈指定商品的所有 active 變體 ID
	 *
	 * @param int $post_id
	 * @return int[]
	 */
	private function get_active_variation_ids( int $post_id ): array {
		global $wpdb;

		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}fct_product_variations
			 WHERE post_id = %d AND item_status = 'active'",
			$post_id
		) );

		return array_map( 'intval', $rows );
	}

	/**
	 * 找出缺 _buygo_purchased meta 的變體
	 *
	 * @param int[] $variation_ids
	 * @return int[]
	 */
	private function get_variations_missing_purchased_meta( array $variation_ids ): array {
		global $wpdb;

		if ( empty( $variation_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );

		$existing = $wpdb->get_col( $wpdb->prepare(
			"SELECT object_id FROM {$wpdb->prefix}fct_meta
			 WHERE object_type = 'variation'
			   AND object_id IN ($placeholders)
			   AND meta_key = '_buygo_purchased'",
			...$variation_ids
		) );

		$existing = array_map( 'intval', $existing );

		return array_values( array_diff( $variation_ids, $existing ) );
	}

	/**
	 * 撈指定商品所有有效 split 子訂單
	 *
	 * 回傳格式：
	 * [
	 *   child_id => [
	 *     'child_id'    => int,
	 *     'parent_id'   => int,
	 *     'object_id'   => int,  // 目前（可能錯標）的變體 ID
	 *     'quantity'    => int,
	 *   ],
	 *   ...
	 * ]
	 *
	 * @param int   $post_id
	 * @param int[] $variation_ids
	 * @return array
	 */
	private function get_split_child_orders( int $post_id, array $variation_ids ): array {
		global $wpdb;

		if ( empty( $variation_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT child_oi.id       AS child_id,
			        child_oi.parent_id AS parent_id,
			        child_oi.object_id AS object_id,
			        child_oi.quantity  AS quantity
			 FROM {$wpdb->prefix}fct_order_items AS child_oi
			 WHERE child_oi.type      = 'split'
			   AND child_oi.status   NOT IN ('cancelled', 'refunded')
			   AND child_oi.object_id IN ($placeholders)",
			...$variation_ids
		), ARRAY_A );

		$result = [];
		foreach ( $rows as $row ) {
			$result[ (int) $row['child_id'] ] = [
				'child_id'  => (int) $row['child_id'],
				'parent_id' => (int) $row['parent_id'],
				'object_id' => (int) $row['object_id'],
				'quantity'  => (int) $row['quantity'],
			];
		}
		return $result;
	}

	/**
	 * 撈父訂單內的所有商品行（含變體需求量）
	 *
	 * 回傳格式：
	 * [
	 *   parent_id => [
	 *     variation_id => required_quantity,
	 *     ...
	 *   ],
	 *   ...
	 * ]
	 *
	 * @param int[] $parent_ids
	 * @param int[] $variation_ids
	 * @return array
	 */
	private function get_parent_order_requirements( array $parent_ids, array $variation_ids ): array {
		global $wpdb;

		if ( empty( $parent_ids ) || empty( $variation_ids ) ) {
			return [];
		}

		$p_placeholders = implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) );
		$v_placeholders = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT oi.parent_id AS parent_id,
			        oi.object_id AS variation_id,
			        SUM(oi.quantity) AS required_qty
			 FROM {$wpdb->prefix}fct_order_items AS oi
			 WHERE oi.parent_id IN ($p_placeholders)
			   AND oi.object_id IN ($v_placeholders)
			   AND oi.type      = 'original'
			   AND oi.status   NOT IN ('cancelled', 'refunded')
			 GROUP BY oi.parent_id, oi.object_id",
			...array_merge( $parent_ids, $variation_ids )
		), ARRAY_A );

		$result = [];
		foreach ( $rows as $row ) {
			$pid = (int) $row['parent_id'];
			$vid = (int) $row['variation_id'];
			if ( ! isset( $result[ $pid ] ) ) {
				$result[ $pid ] = [];
			}
			$result[ $pid ][ $vid ] = (int) $row['required_qty'];
		}
		return $result;
	}

	// ════════════════════════════════════════════════════════════════
	// 私有方法：錯標偵測與修復計畫產生
	// ════════════════════════════════════════════════════════════════

	/**
	 * 偵測錯標子訂單並產生修復計畫
	 *
	 * 回傳格式：
	 * [
	 *   [
	 *     'child_id'       => int,
	 *     'parent_id'      => int,
	 *     'old_object_id'  => int,
	 *     'new_object_id'  => int,
	 *     'quantity'       => int,
	 *     'reason'         => string,
	 *   ],
	 *   ...
	 * ]
	 *
	 * @param int   $post_id
	 * @param int[] $variation_ids
	 * @return array
	 */
	private function detect_mislabeled_child_orders( int $post_id, array $variation_ids ): array {
		// 撈所有 split 子訂單
		$child_orders = $this->get_split_child_orders( $post_id, $variation_ids );

		if ( empty( $child_orders ) ) {
			WP_CLI::log( 'No active split child orders found.' );
			return [];
		}

		// 收集所有涉及的父訂單 ID
		$parent_ids = array_unique( array_column( $child_orders, 'parent_id' ) );

		// 撈父訂單需求量
		$parent_requirements = $this->get_parent_order_requirements( $parent_ids, $variation_ids );

		$repair_plan = [];

		// 按父訂單逐一處理
		foreach ( $parent_ids as $parent_id ) {
			$required_by_variant = $parent_requirements[ $parent_id ] ?? [];
			if ( empty( $required_by_variant ) ) {
				WP_CLI::warning( sprintf( 'Parent order %d has no original items matching the variation list, skipping.', $parent_id ) );
				continue;
			}

			// 篩出屬於此父訂單的子訂單
			$children_of_parent = array_filter( $child_orders, function( $c ) use ( $parent_id ) {
				return $c['parent_id'] === $parent_id;
			} );

			// 統計每個變體目前已分配量（所有子訂單，含可能錯標的）
			$allocated_by_variant = [];
			foreach ( $variation_ids as $vid ) {
				$allocated_by_variant[ $vid ] = 0;
			}
			foreach ( $children_of_parent as $child ) {
				$vid = $child['object_id'];
				if ( isset( $allocated_by_variant[ $vid ] ) ) {
					$allocated_by_variant[ $vid ] += $child['quantity'];
				}
			}

			// 計算超額分配量
			$over_allocated_by_variant = [];
			foreach ( $required_by_variant as $vid => $required ) {
				$allocated = $allocated_by_variant[ $vid ] ?? 0;
				$over      = max( 0, $allocated - $required );
				if ( $over > 0 ) {
					$over_allocated_by_variant[ $vid ] = $over;
				}
			}

			// 同時計算每個變體的實際缺額（只算「正確標到該變體」且非超額的部分）
			// 此時先把超額子訂單找出來
			$mislabeled_candidates = [];
			foreach ( $children_of_parent as $child ) {
				$vid = $child['object_id'];
				if ( isset( $over_allocated_by_variant[ $vid ] ) && $over_allocated_by_variant[ $vid ] > 0 ) {
					$mislabeled_candidates[] = $child;
					// 扣掉這筆的超額（避免同一變體有多筆子訂單都被標為候選）
					$over_allocated_by_variant[ $vid ] -= $child['quantity'];
					if ( $over_allocated_by_variant[ $vid ] <= 0 ) {
						unset( $over_allocated_by_variant[ $vid ] );
					}
				}
			}

			if ( empty( $mislabeled_candidates ) ) {
				continue;
			}

			// 計算缺額（shortage）= required - 已正確分配量
			// 「正確分配」= 非候選錯標的子訂單
			$mislabeled_child_ids = array_column( $mislabeled_candidates, 'child_id' );
			$shortage             = [];
			foreach ( $required_by_variant as $vid => $required ) {
				$correctly_allocated = 0;
				foreach ( $children_of_parent as $child ) {
					if ( $child['object_id'] === $vid && ! in_array( $child['child_id'], $mislabeled_child_ids, true ) ) {
						$correctly_allocated += $child['quantity'];
					}
				}
				$s = $required - $correctly_allocated;
				if ( $s > 0 ) {
					$shortage[ $vid ] = $s;
				}
			}

			// 對每筆錯標子訂單重新分配：缺額最大優先，同缺額時 variation_id 升序
			foreach ( $mislabeled_candidates as $candidate ) {
				if ( empty( $shortage ) ) {
					WP_CLI::warning( sprintf(
						'Parent order %d: all shortages filled but child_id=%d still unallocated (qty=%d). Manual intervention required.',
						$parent_id,
						$candidate['child_id'],
						$candidate['quantity']
					) );
					continue;
				}

				// 找缺額最大的變體
				arsort( $shortage );
				// 同缺額時按 variation_id 升序
				$max_shortage = max( $shortage );
				$candidates_for_target = [];
				foreach ( $shortage as $vid => $s ) {
					if ( $s === $max_shortage ) {
						$candidates_for_target[] = $vid;
					}
				}
				sort( $candidates_for_target );
				$target_vid = $candidates_for_target[0];

				$repair_plan[] = [
					'child_id'      => $candidate['child_id'],
					'parent_id'     => $parent_id,
					'old_object_id' => $candidate['object_id'],
					'new_object_id' => $target_vid,
					'quantity'      => $candidate['quantity'],
					'reason'        => sprintf(
						'over-allocated on variation %d; shortage on variation %d = %d',
						$candidate['object_id'],
						$target_vid,
						$shortage[ $target_vid ]
					),
				];

				// 更新缺額
				$shortage[ $target_vid ] -= $candidate['quantity'];
				if ( $shortage[ $target_vid ] <= 0 ) {
					unset( $shortage[ $target_vid ] );
				}
			}
		}

		return $repair_plan;
	}

	/**
	 * 計算修復後 post_meta._buygo_allocated 的新數值
	 *
	 * @param int   $post_id
	 * @param int[] $variation_ids
	 * @param array $repair_plan     修復計畫（尚未實際寫入 DB）
	 * @return int
	 */
	private function calculate_new_allocated( int $post_id, array $variation_ids, array $repair_plan ): int {
		global $wpdb;

		if ( empty( $variation_ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );

		// 撈現有所有 split 子訂單的總量
		$existing_total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(quantity), 0)
			 FROM {$wpdb->prefix}fct_order_items
			 WHERE type     = 'split'
			   AND status  NOT IN ('cancelled', 'refunded')
			   AND object_id IN ($placeholders)",
			...$variation_ids
		) );

		// 修復計畫不改變總數量，只改 object_id，所以 allocated 不變
		// 但仍以 DB 查詢為準（確保一致性）
		return $existing_total;
	}

	// ════════════════════════════════════════════════════════════════
	// 私有方法：輸出
	// ════════════════════════════════════════════════════════════════

	/**
	 * 印子訂單修復計畫表格
	 *
	 * @param array $repair_plan
	 */
	private function print_child_repair_plan( array $repair_plan ): void {
		WP_CLI::log( '' );
		WP_CLI::log( '=== Table 1: Child Order Repair Plan ===' );

		if ( empty( $repair_plan ) ) {
			WP_CLI::log( '(no child orders need relabeling)' );
			return;
		}

		\WP_CLI\Utils\format_items(
			'table',
			$repair_plan,
			[ 'child_id', 'parent_id', 'old_object_id', 'new_object_id', 'quantity', 'reason' ]
		);
	}

	/**
	 * 印 variation meta 修復計畫表格
	 *
	 * @param int[]    $missing_variation_ids
	 * @param int|null $purchased_d
	 */
	private function print_meta_repair_plan( array $missing_variation_ids, ?int $purchased_d ): void {
		WP_CLI::log( '' );
		WP_CLI::log( '=== Table 2: Variation Meta Repair Plan ===' );

		if ( empty( $missing_variation_ids ) ) {
			WP_CLI::log( '(no variation metas need inserting)' );
			return;
		}

		$rows = [];
		foreach ( $missing_variation_ids as $vid ) {
			$rows[] = [
				'variation_id' => $vid,
				'meta_key'     => '_buygo_purchased',
				'old_value'    => '(none)',
				'new_value'    => $purchased_d !== null ? (string) $purchased_d : '(pending --purchased-d)',
			];
		}

		\WP_CLI\Utils\format_items(
			'table',
			$rows,
			[ 'variation_id', 'meta_key', 'old_value', 'new_value' ]
		);
	}

	// ════════════════════════════════════════════════════════════════
	// 私有方法：實際執行（COMMIT 模式）
	// ════════════════════════════════════════════════════════════════

	/**
	 * 在 transaction 內執行修復
	 *
	 * @param int      $post_id
	 * @param array    $child_repair_plan
	 * @param int[]    $missing_meta_variation_ids
	 * @param int|null $purchased_d
	 * @param int      $new_allocated
	 */
	private function execute_repair(
		int $post_id,
		array $child_repair_plan,
		array $missing_meta_variation_ids,
		?int $purchased_d,
		int $new_allocated
	): void {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// ── 1. 更新錯標子訂單 object_id ──────────────────────────
			foreach ( $child_repair_plan as $plan ) {
				$result = $wpdb->update(
					"{$wpdb->prefix}fct_order_items",
					[ 'object_id' => $plan['new_object_id'] ],
					[ 'id' => $plan['child_id'] ],
					[ '%d' ],
					[ '%d' ]
				);

				if ( false === $result ) {
					throw new \RuntimeException( sprintf(
						'Failed to update child_id=%d: %s',
						$plan['child_id'],
						$wpdb->last_error
					) );
				}

				WP_CLI::log( sprintf(
					'Updated child_id=%d: object_id %d -> %d',
					$plan['child_id'],
					$plan['old_object_id'],
					$plan['new_object_id']
				) );
			}

			// ── 2. 插入缺失的 variation meta ─────────────────────────
			foreach ( $missing_meta_variation_ids as $vid ) {
				$result = $wpdb->insert(
					"{$wpdb->prefix}fct_meta",
					[
						'object_type' => 'variation',
						'object_id'   => $vid,
						'meta_key'    => '_buygo_purchased',
						'meta_value'  => (string) $purchased_d,
					],
					[ '%s', '%d', '%s', '%s' ]
				);

				if ( false === $result ) {
					throw new \RuntimeException( sprintf(
						'Failed to insert _buygo_purchased meta for variation_id=%d: %s',
						$vid,
						$wpdb->last_error
					) );
				}

				WP_CLI::log( sprintf(
					'Inserted _buygo_purchased=%d for variation_id=%d',
					$purchased_d,
					$vid
				) );
			}

			// ── 3. 更新 post_meta._buygo_allocated ───────────────────
			update_post_meta( $post_id, '_buygo_allocated', $new_allocated );
			WP_CLI::log( sprintf( 'Updated post_meta._buygo_allocated for post_id=%d to %d', $post_id, $new_allocated ) );

			// ── 4. Commit ─────────────────────────────────────────────
			$wpdb->query( 'COMMIT' );

			// ── 5. 寫 debug log ───────────────────────────────────────
			$this->write_debug_log( $post_id, $child_repair_plan, $missing_meta_variation_ids, $purchased_d, $new_allocated );

			WP_CLI::success( sprintf(
				'Repair completed: %d child order(s) relabeled, %d variation meta(s) inserted, _buygo_allocated set to %d.',
				count( $child_repair_plan ),
				count( $missing_meta_variation_ids ),
				$new_allocated
			) );

		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			WP_CLI::error( 'ROLLBACK: ' . $e->getMessage() );
		}
	}

	/**
	 * 寫修復記錄到 DebugService（若可用）或 error_log
	 *
	 * @param int      $post_id
	 * @param array    $child_repair_plan
	 * @param int[]    $missing_meta_variation_ids
	 * @param int|null $purchased_d
	 * @param int      $new_allocated
	 */
	private function write_debug_log(
		int $post_id,
		array $child_repair_plan,
		array $missing_meta_variation_ids,
		?int $purchased_d,
		int $new_allocated
	): void {
		$summary = sprintf(
			'[fix-cross-variant-child-orders] post_id=%d | child_orders_fixed=%d | metas_inserted=%d | _buygo_allocated=%d | purchased_d=%s',
			$post_id,
			count( $child_repair_plan ),
			count( $missing_meta_variation_ids ),
			$new_allocated,
			$purchased_d !== null ? $purchased_d : 'n/a'
		);

		// 若 DebugService 存在則使用，否則退回 error_log
		if ( class_exists( '\BuyGoPlus\Services\DebugService' ) ) {
			\BuyGoPlus\Services\DebugService::log( $summary );
		} else {
			error_log( $summary );
		}
	}
}

WP_CLI::add_command(
	'buygo fix-cross-variant-child-orders',
	'BuyGo_Fix_Cross_Variant_Child_Orders_Command'
);
