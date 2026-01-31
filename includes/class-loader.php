<?php
namespace BuyGoPlus\Includes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 註冊所有 WordPress Hooks
 *
 * 統一管理所有 actions 和 filters 的註冊
 * 遵循 WordPress Plugin Boilerplate 標準
 *
 * @package    BuyGoPlus
 * @subpackage BuyGoPlus/includes
 */
class Loader {
    /**
     * Actions 註冊陣列
     *
     * @var array
     */
    protected $actions;

    /**
     * Filters 註冊陣列
     *
     * @var array
     */
    protected $filters;

    /**
     * 初始化 Loader
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * 新增 action hook
     *
     * @param string $hook          Hook 名稱
     * @param object $component     實例物件
     * @param string $callback      回呼方法
     * @param int    $priority      優先順序（預設 10）
     * @param int    $accepted_args 接受參數數量（預設 1）
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * 新增 filter hook
     *
     * @param string $hook          Hook 名稱
     * @param object $component     實例物件
     * @param string $callback      回呼方法
     * @param int    $priority      優先順序（預設 10）
     * @param int    $accepted_args 接受參數數量（預設 1）
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * 新增 hook 到陣列
     *
     * @param array  $hooks         現有 hooks 陣列
     * @param string $hook          Hook 名稱
     * @param object $component     實例物件
     * @param string $callback      回呼方法
     * @param int    $priority      優先順序
     * @param int    $accepted_args 接受參數數量
     * @return array                更新後的 hooks 陣列
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        );
        return $hooks;
    }

    /**
     * 執行所有已註冊的 hooks
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
