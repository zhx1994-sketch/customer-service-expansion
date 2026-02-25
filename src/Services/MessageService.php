<?php

/**
 * 消息服务类
 * 
 * 封装消息相关的业务逻辑，包括：
 * - 消息创建和发送
 * - 已读状态管理
 * - 消息查询
 * - WebSocket事件触发
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion\Services
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion\Services;

use PartTimeDevelopment\CustomerServiceExpansion\Models\Message;
use PartTimeDevelopment\CustomerServiceExpansion\Models\Conversation;
use PartTimeDevelopment\CustomerServiceExpansion\Events\NewMessage;

class MessageService
{
    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * 发送新消息
     * 
     * 处理消息发送的完整流程：
     * - 创建消息记录
     * - 更新对话的最后消息时间
     * - 触发WebSocket广播事件
     * 
     * @param array $data 消息数据，包含：
     *   - conversation_id: 对话ID
     *   - sender_type: 发送者类型（0用户 1客服）
     *   - sender_id: 发送者ID
     *   - content: 消息内容
     *   - content_type: 消息类型（默认为text）
     * 
     * @return Message 创建的消息对象
     */
    public function sendMessage(array $data): Message
    {
        // 创建消息记录
        $message = Message::create([
            'conversation_id' => $data['conversation_id'],
            'sender_type' => $data['sender_type'],
            'sender_id' => $data['sender_id'],
            'content' => $data['content'],
            'content_type' => $data['content_type'] ?? 'text'  // 默认为文本消息
        ]);

        // 更新对话的最后消息时间，用于排序
        Conversation::where('id', $data['conversation_id'])
            ->update(['last_message_time' => now()]);

        // 广播新消息事件，用于WebSocket实时推送
        event(new NewMessage($message, $data['conversation_id']));

        return $message;
    }

    /**
     * 标记指定消息为已读状态
     * 
     * 将单条消息标记为已读，并记录阅读时间。
     * 只有在消息未读时才会执行更新。
     * 
     * @param int $messageId 消息ID
     * @return bool 操作是否成功
     */
    public function markMessageAsRead(int $messageId): bool
    {
        $message = Message::find($messageId);
        if (!$message) {
            return false;
        }

        $message->markAsRead();
        return true;
    }

    /**
     * 获取消息详细信息
     * 
     * 根据消息ID获取消息的完整信息，
     * 包含关联的对话和发送者信息。
     * 
     * @param int $id 消息ID
     * @return Message|null 消息对象，如果不存在则返回null
     */
    public function getMessage(int $id): ?Message
    {
        return Message::with(['conversation', 'sender'])->find($id);
    }
}
