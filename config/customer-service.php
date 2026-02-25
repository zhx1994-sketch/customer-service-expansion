<?php

/**
 * 客服系统配置文件
 * 
 * 包含客服系统的所有配置选项：
 * - 基础应用设置
 * - WebSocket实时通讯配置
 * - 对话管理配置
 * - 通知系统配置
 * - 前端功能配置
 * - 权限管理配置
 * 
 * @package CustomerServiceExpansion
 * @version 1.0.0
 */

return [
    /*
     * 客服应用基础配置
     */
    'app_name' => env('CUSTOMER_SERVICE_APP_NAME', '在线客服'),
    
    /*
     * WebSocket实时通讯配置
     * 
     * 用于配置WebSocket服务器相关参数：
     * - enabled: 是否启用WebSocket
     * - host: WebSocket服务器地址
     * - port: WebSocket服务器端口
     * - ssl: 是否使用SSL加密
     * - path: WebSocket路径
     * - pusher_*: Pusher相关认证配置
     */
    'websocket' => [
        'enabled' => env('CUSTOMER_SERVICE_WEBSOCKET_ENABLED', true),
        'host' => env('CUSTOMER_SERVICE_WEBSOCKET_HOST', '127.0.0.1'),
        'port' => env('CUSTOMER_SERVICE_WEBSOCKET_PORT', 6001),
        'ssl' => env('CUSTOMER_SERVICE_WEBSOCKET_SSL', false),
        'path' => env('CUSTOMER_SERVICE_WEBSOCKET_PATH', 'app/customer-service'),
        'pusher_key' => env('CUSTOMER_SERVICE_PUSHER_KEY', 'cs_123456789'),
        'pusher_secret' => env('CUSTOMER_SERVICE_PUSHER_SECRET', 'cs_987654321'),
        'pusher_app_id' => env('CUSTOMER_SERVICE_PUSHER_APP_ID', 'customer-service'),
    ],
    
    /*
     * 对话管理配置
     * 
     * 用于配置对话相关参数：
     * - max_conversations_per_staff: 每个客服最大同时对话数
     * - auto_assign_timeout: 自动分配超时时间（秒）
     * - message_retention_days: 消息保留天数
     * - max_message_length: 消息最大长度限制
     */
    'conversation' => [
        'max_conversations_per_staff' => env('CUSTOMER_SERVICE_MAX_CONVERSATIONS', 10),
        'auto_assign_timeout' => env('CUSTOMER_SERVICE_AUTO_ASSIGN_TIMEOUT', 30), // 秒
        'message_retention_days' => env('CUSTOMER_SERVICE_MESSAGE_RETENTION_DAYS', 30),
        'max_message_length' => env('CUSTOMER_SERVICE_MAX_MESSAGE_LENGTH', 1000),
    ],
    
    /*
     * 通知系统配置
     * 
     * 配置通知功能相关参数：
     * - email_enabled: 是否启用邮件通知
     * - notification_emails: 接收通知的邮箱列表
     * - browser_notification_enabled: 是否启用浏览器通知
     * - sound_enabled: 是否启用提示音
     */
    'notification' => [
        'email_enabled' => env('CUSTOMER_SERVICE_EMAIL_NOTIFICATION', false),
        'notification_emails' => explode(',', env('CUSTOMER_SERVICE_NOTIFICATION_EMAILS', '')),
        'browser_notification_enabled' => env('CUSTOMER_SERVICE_BROWSER_NOTIFICATION', true),
        'sound_enabled' => env('CUSTOMER_SERVICE_SOUND_ENABLED', true),
    ],
    
    /*
     * 用户端功能配置
     * 
     * 配置前端用户界面的功能开关：
     * - enable_file_upload: 是否允许文件上传
     * - max_file_size: 文件大小限制（字节）
     * - allowed_file_types: 允许的文件类型
     * - enable_emoji: 是否启用表情符号
     * - enable_voice_message: 是否启用语音消息
     */
    'frontend' => [
        'enable_file_upload' => env('CUSTOMER_SERVICE_ENABLE_FILE_UPLOAD', true),
        'max_file_size' => env('CUSTOMER_SERVICE_MAX_FILE_SIZE', 5242880), // 5MB
        'allowed_file_types' => explode(',', env('CUSTOMER_SERVICE_ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx')),
        'enable_emoji' => env('CUSTOMER_SERVICE_ENABLE_EMOJI', true),
        'enable_voice_message' => env('CUSTOMER_SERVICE_ENABLE_VOICE_MESSAGE', false),
    ],
    
    /*
     * 权限管理配置
     * 
     * 定义不同角色的权限范围：
     * - admin: 管理员权限（全部权限）
     * - staff: 客服权限（基础操作权限）
     * - manager: 管理者权限（包含员工管理）
     */
    'permissions' => [
        'admin' => ['customer-service.*'],
        'staff' => [
            'customer-service.conversations.view',
            'customer-service.messages.send',
            'customer-service.conversations.assign'
        ],
        'manager' => [
            'customer-service.*',
            'customer-service.staff.manage'
        ]
    ]
];