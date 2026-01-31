<?php
/**
 * BuyGoPlus Core Facade
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Core;

use BuyGoPlus\Services\LineService;
use BuyGoPlus\Services\SettingsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGoPlus_Core
 *
 * Facade 類別，提供靜態方法存取核心服務
 * 類似舊外掛的 BuyGo_Core，但使用新外掛的命名空間
 */
class BuyGoPlus_Core {

	/**
	 * Line Service instance
	 *
	 * @var LineService|null
	 */
	private static $line_service = null;

	/**
	 * Settings Service instance
	 *
	 * @var SettingsService|null
	 */
	private static $settings_service = null;

	/**
	 * Get Line Service.
	 *
	 * @return LineService
	 */
	public static function line() {
		if ( null === self::$line_service ) {
			self::$line_service = new LineService();
		}
		return self::$line_service;
	}

	/**
	 * Get Settings Service.
	 *
	 * 注意：SettingsService 是靜態方法，但為了與舊外掛相容，
	 * 這裡返回一個包裝物件，提供 get() 和 set() 方法
	 *
	 * @return object 包含 get() 和 set() 方法的物件
	 */
	public static function settings() {
		return new class {
			/**
			 * Get setting value
			 *
			 * @param string $key
			 * @param mixed $default
			 * @return mixed
			 */
			public function get( $key, $default = null ) {
				return \BuyGoPlus\Services\SettingsService::get( $key, $default );
			}

			/**
			 * Set setting value
			 *
			 * @param string $key
			 * @param mixed $value
			 * @return bool
			 */
			public function set( $key, $value ) {
				return \BuyGoPlus\Services\SettingsService::set( $key, $value );
			}
		};
	}

	/**
	 * Check if Core is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return true;
	}
}
