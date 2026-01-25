<?php

namespace FluentcartPayuni;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function init(): void
    {
        // TODO: 在這裡註冊 hooks、載入依賴、初始化 service
        // 例：add_action('init', [$this, 'on_init']);
    }

    public function on_init(): void
    {
        // TODO: WordPress init 階段要做的事
    }
}

