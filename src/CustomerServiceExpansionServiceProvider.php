<?php

/**
 * 客服扩展服务提供者
 * 
 * 负责扩展的初始化和配置，包括：
 * - 注册扩展菜单
 * - 加载路由和视图
 * - 注册中间件和配置
 * - 发布资源文件
 * - 提供扩展配置界面
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion;

use Slowlyo\OwlAdmin\Extend\ServiceProvider;

class CustomerServiceExpansionServiceProvider extends ServiceProvider
{
    /**
     * 扩展菜单配置
     * 
     * 定义在OwlAdmin后台显示的菜单结构，
     * 包含客服工作台、对话管理、消息记录等功能入口
     * 
     * @var array
     */
    protected $menu = [
        [
            'title' => '客服管理',
            'icon'  => 'fluent-mdl2:calligraphy',
            'url'   => '/admin/customer-service',
            'children' => [
                [
                    'title' => '客服工作台',
                    'url'   => '/admin/customer-service/workbench',
                    'icon'  => 'fluent-mdl2:calligraphy'
                ],
                [
                    'title' => '对话管理',
                    'url'   => '/admin/customer-service/conversations',
                    'icon'  => 'fluent-mdl2:chat'
                ],
                [
                    'title' => '消息记录',
                    'url'   => '/admin/customer-service/messages',
                    'icon'  => 'fluent-mdl2:message'
                ]
            ]
        ]
    ];

    /**
     * 注册扩展服务
     * 
     * 在Laravel服务容器中注册扩展相关的服务：
     * - 合并配置文件
     * - 注册中间件别名
     */
    public function register()
    {
        parent::register();

        // 注册扩展配置，与主应用配置合并
        $this->mergeConfigFrom(
            __DIR__.'/../config/customer-service.php', 'customer-service'
        );

        // 注册客服认证中间件别名
        $this->app['router']->aliasMiddleware('customer-service.auth', \PartTimeDevelopment\CustomerServiceExpansion\Http\Middleware\CustomerServiceAuth::class);
    }

    /**
     * 启动扩展
     * 
     * 在应用启动时执行以下操作：
     * - 加载扩展路由
     * - 注册视图命名空间
     * - 加载数据库迁移
     * - 发布静态资源文件
     */
    public function boot()
    {
        parent::boot();

        // 加载视图文件，并指定命名空间
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'customer-service-expansion');

        // 加载数据库迁移文件
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 发布资源文件到public目录（仅在命令行环境下）
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/customer-service.php' => config_path('customer-service.php'),
                __DIR__.'/../resources/assets' => public_path('vendor/customer-service-expansion'),
            ], 'customer-service-expansion');
        }
    }

    /**
     * 扩展配置表单
     * 
     * 定义在OwlAdmin管理界面中的扩展配置表单，
     * 包含基础设置、WebSocket设置、通知设置等配置项
     * 
     * @return \Slowlyo\OwlAdmin\Renderers\Form 配置表单对象
     */
    public function settingForm()
    {
        return $this->baseSettingForm()->body([
            \Slowlyo\OwlAdmin\Renderers\GroupControl::make()->name('basic_settings')->label('基础设置')
                ->body([
                    \Slowlyo\OwlAdmin\Renderers\TextControl::make()->name('app_name')->label('应用名称')
                        ->default('在线客服'),
                    \Slowlyo\OwlAdmin\Renderers\SwitchControl::make()->name('enable_websocket')->label('WebSocket')
                        ->default(true),
                    \Slowlyo\OwlAdmin\Renderers\TextControl::make()->name('max_conversations')->label('最大对话数')
                        ->type('input-number')
                        ->default(10),
                    \Slowlyo\OwlAdmin\Renderers\TextControl::make()->name('message_retention_days')->label('消息保留天数')
                        ->type('input-number')
                        ->default(30)
                ]),
            
            \Slowlyo\OwlAdmin\Renderers\GroupControl::make()->name('websocket_settings')->label('WebSocket设置')
                ->body([
                    \Slowlyo\OwlAdmin\Renderers\TextControl::make()->name('websocket_port')->label('WebSocket端口')
                        ->type('input-number')
                        ->default(6001),
                    \Slowlyo\OwlAdmin\Renderers\TextControl::make()->name('websocket_host')->label('WebSocket主机')
                        ->default('127.0.0.1'),
                    \Slowlyo\OwlAdmin\Renderers\SwitchControl::make()->name('enable_ssl')->label('启用SSL')
                        ->default(false)
                ]),

            \Slowlyo\OwlAdmin\Renderers\GroupControl::make()->name('notification_settings')->label('通知设置')
                ->body([
                    \Slowlyo\OwlAdmin\Renderers\SwitchControl::make()->name('enable_email_notification')->label('启用邮件通知')
                        ->default(false),
                    \Slowlyo\OwlAdmin\Renderers\TextControl::make()->name('notification_emails')->label('通知邮箱')
                        ->type('input-array')
                        ->items([
                            \Slowlyo\OwlAdmin\Renderers\TextControl::make()->name('email')->label('邮箱')->required()
                        ]),
                    \Slowlyo\OwlAdmin\Renderers\SwitchControl::make()->name('enable_browser_notification')->label('启用浏览器通知')
                        ->default(true)
                ]),

        ]);
    }
}
