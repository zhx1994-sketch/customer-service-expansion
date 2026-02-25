<?php

/**
 * 对话服务类
 * 
 * 封装对话相关的业务逻辑，包括：
 * - 对话查询和筛选
 * - 对话状态管理
 * - 统计信息计算
 * - 已读状态处理
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion\Services
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion\Services;

use PartTimeDevelopment\CustomerServiceExpansion\Models\Conversation;
use PartTimeDevelopment\CustomerServiceExpansion\Models\Message;
use Illuminate\Pagination\LengthAwarePaginator;

class ConversationService
{
    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * 获取对话列表
     * 
     * 提供灵活的对话查询功能，支持：
     * - 按最新消息时间倒序排列
     * - 关键词搜索（标题和用户昵称）
     * - 按状态筛选
     * - 自动添加统计信息（未读数、消息数等）
     * 
     * @param array $filters 筛选参数：
     *   - keyword: 搜索关键词
     *   - status: 对话状态筛选
     * 
     * @return \Illuminate\Pagination\LengthAwarePaginator 分页的对话列表
     */
    public function getConversations(array $filters = []): LengthAwarePaginator
    {
        // 基础查询：预加载关联数据，按最新消息排序
        $query = Conversation::with(['user', 'lastMessage'])
            ->orderBy('last_message_time', 'desc'); // 最新消息在最上面

        // 关键词搜索：搜索对话标题和用户昵称
        if (!empty($filters['keyword'])) {
            $query->where(function($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['keyword'] . '%')  // 搜索标题
                  ->orWhereHas('user', function($userQ) use ($filters) {          // 搜索用户昵称
                      $userQ->where('nickname', 'like', '%' . $filters['keyword'] . '%');
                  });
            });
        }

        // 状态筛选
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        // 分页查询
        $conversations = $query->paginate(20);
        
        // 为每个对话添加统计信息
        foreach ($conversations->items() as $conversation) {
            $conversation->unread_count = $conversation->unread_count;        // 未读消息数
            $conversation->message_count = $conversation->message_count;      // 总消息数
            $conversation->last_message_content = $conversation->last_message_content; // 最新消息预览
        }
        
        return $conversations;
    }

    /**
     * 获取对话详细信息
     * 
     * 获取指定对话的完整信息，包括：
     * - 对话基本信息和关联数据
     * - 所有消息记录
     * - 最新消息信息
     * 
     * 获取对话详情时会自动将该对话中所有用户发送的未读消息标记为已读
     * 
     * @param int $id 对话ID
     * @return Conversation|null 对话对象，如果不存在则返回null
     */
    public function getConversationDetail(int $id): ?Conversation
    {
        $conversation = Conversation::with(['user', 'messages', 'lastMessage'])
            ->find($id);

        if ($conversation) {
            // 自动标记对话中的所有用户消息为已读
            $conversation->markAllMessagesAsRead();
        }

        return $conversation;
    }

    /**
     * 获取指定对话的消息列表
     * 
     * 返回对话中的所有消息，按发送时间升序排列。
     * 包含发送者的详细信息。
     * 
     * @param int $id 对话ID
     * @return array 包含消息列表的数组
     */
    public function getConversationMessages(int $id): array
    {
        $messages = Message::where('conversation_id', $id)
            ->with(['sender'])  // 预加载发送者信息
            ->orderBy('created_at', 'asc')  // 按时间正序排列
            ->get();

        return ['messages' => $messages];
    }

    /**
     * 标记指定对话为已读状态
     * 
     * 将对话中所有用户发送的未读消息标记为已读，
     * 并记录阅读时间。
     * 
     * @param int $id 对话ID
     * @return bool 操作是否成功
     */
    public function markAsRead(int $id): bool
    {
        $conversation = Conversation::find($id);
        if (!$conversation) {
            return false;
        }

        $conversation->markAllMessagesAsRead();
        return true;
    }

    /**
     * 结束指定对话
     * 
     * 将对话状态设置为"已结束"并记录结束时间。
     * 结束后的对话不再接收新消息。
     * 
     * @param int $id 对话ID
     * @return bool 操作是否成功
     */
    public function endConversation(int $id): bool
    {
        $conversation = Conversation::find($id);
        if (!$conversation) {
            return false;
        }

        $conversation->update([
            'status' => 2,        // 设为已结束状态
            'end_time' => now()    // 记录结束时间
        ]);

        return true;
    }

    /**
     * 获取客服系统统计信息
     * 
     * 根据统计类型返回相应的数据，支持：
     * - total: 总对话数
     * - read: 已读对话数
     * - unread: 未读对话数
     * - unread-messages: 未读消息数
     * - today: 今日消息数
     * 
     * @param string $type 统计类型，默认为'total'
     * @return array 统计结果数据
     */
    public function getStats(string $type = 'total'): array
    {
        switch ($type) {
            case 'total':
                return ['total_conversations' => Conversation::count()];
                
            case 'read':
                // 计算已读对话数：没有未读用户消息的对话
                $readConversationIds = Message::where('sender_type', 0) // 用户消息
                    ->where('is_read', 0)
                    ->distinct('conversation_id')
                    ->pluck('conversation_id');
                    
                return [
                    'read_conversations' => Conversation::whereNotIn('id', $readConversationIds)->count()
                ];
                
            case 'unread':
                // 计算未读对话数：有未读用户消息的对话
                $unreadConversationIds = Message::where('sender_type', 0) // 用户消息
                    ->where('is_read', 0)
                    ->distinct('conversation_id')
                    ->pluck('conversation_id');
                    
                return [
                    'unread_conversations' => Conversation::whereIn('id', $unreadConversationIds)->count()
                ];
                
            case 'unread-messages':
                // 统计所有未读的用户消息数量
                return [
                    'unread_messages' => Message::where('sender_type', 0) // 用户消息
                        ->where('is_read', 0)
                        ->count()
                ];
                
            case 'today':
                // 统计今日发送的所有消息数量
                return ['today_messages' => Message::whereDate('created_at', today())->count()];
                
            default:
                return ['total_conversations' => Conversation::count()];
        }
    }
}
