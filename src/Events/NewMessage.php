<?php

/**
 * 新消息事件
 * 
 * 当有新消息发送时触发此事件，用于：
 * - WebSocket实时推送
 * - 通知相关用户和客服
 * - 实现实时通讯功能
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion\Events
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PartTimeDevelopment\CustomerServiceExpansion\Models\Message;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 消息对象
     * 
     * @var Message
     */
    public $message;

    /**
     * 对话ID
     * 
     * @var int
     */
    public $conversationId;

    /**
     * 构造函数
     * 
     * @param Message $message 消息对象
     * @param int $conversationId 对话ID
     */
    public function __construct($message, $conversationId)
    {
        $this->message = $message;
        $this->conversationId = $conversationId;
    }

    /**
     * 定义广播频道
     * 
     * 事件将广播到以下私有频道：
     * - conversation.{id}: 特定对话的参与者
     * - user.{id}: 消息发送者
     * - staff.{id}: 客服人员（如果是客服发送）
     * 
     * @return array 广播频道数组
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),  // 对话频道
            new PrivateChannel('user.' . $this->message->sender_id),       // 用户频道
            new PrivateChannel('staff.' . $this->message->sender_id)       // 客服频道
        ];
    }

    /**
     * 定义广播事件名称
     * 
     * 前端客户端可以通过监听 'new.message' 事件来接收消息
     * 
     * @return string 广播事件名称
     */
    public function broadcastAs()
    {
        return 'new.message';
    }

    /**
     * 定义广播数据格式
     * 
     * 返回给客户端的数据结构，包含：
     * - message: 完整消息信息（预加载发送者数据）
     * - conversation_id: 对话ID
     * - timestamp: 时间戳
     * 
     * @return array 广播数据
     */
    public function broadcastWith()
    {
        return [
            'message' => $this->message->load(['sender']),  // 预加载发送者信息
            'conversation_id' => $this->conversationId,
            'timestamp' => now()->toISOString()
        ];
    }
}