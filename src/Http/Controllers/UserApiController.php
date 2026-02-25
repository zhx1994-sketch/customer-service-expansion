<?php

/**
 * 用户端API控制器
 * 
 * 处理用户端客服系统的相关操作，包括：
 * - 创建新的对话
 * - 发送消息
 * - 获取对话消息
 * - 获取用户的对话列表
 * 
 * 所有接口都需要用户认证，确保数据安全
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers;

use Illuminate\Routing\Controller;
use PartTimeDevelopment\CustomerServiceExpansion\Models\Conversation;
use PartTimeDevelopment\CustomerServiceExpansion\Models\Message;
use PartTimeDevelopment\CustomerServiceExpansion\Services\ConversationService;
use PartTimeDevelopment\CustomerServiceExpansion\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserApiController extends Controller
{
    /**
     * 创建新的客服对话
     * 
     * 用户可以通过此接口创建新的对话并发送第一条消息。
     * 系统会自动生成唯一的会话ID，并将对话状态设为"等待中"。
     * 
     * @param Request $request HTTP请求对象，包含：
     *   - title: 对话标题（必填，最大200字符）
     *   - content: 第一条消息内容（必填，最大1000字符）
     * 
     * @return \Illuminate\Http\JsonResponse 创建的对话信息
     * 
     * @example
     * POST /api/customer-service/conversations
     * {
     *   "title": "关于产品咨询",
     *   "content": "我想了解一下你们的客服产品"
     * }
     */
    public function createConversation(Request $request)
    {
        // 验证输入数据
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:1000'
        ]);

        // 创建新对话
        $conversation = Conversation::create([
            'session_id' => Str::uuid(), // 生成唯一会话ID
            'user_id' => auth()->id(), // 关联当前用户
            'status' => 0, // 等待中状态
            'title' => $data['title'],
            'last_message_time' => now() // 设置最后消息时间
        ]);

        // 发送第一条消息
        $messageService = new MessageService();
        $messageService->sendMessage([
            'conversation_id' => $conversation->id,
            'sender_type' => 0, // 用户发送
            'sender_id' => auth()->id(),
            'content' => $data['content'],
            'content_type' => 'text'
        ]);

        return response()->json([
            'status' => 0,
            'data' => $conversation,
            'message' => '对话创建成功'
        ]);
    }

    /**
     * 用户发送消息
     * 
     * 用户向指定对话发送消息，包括：
     * - 验证对话归属权限（只能向自己的对话发送消息）
     * - 自动重新激活已结束的对话
     * - 创建消息记录
     * - 触发WebSocket推送通知客服
     * 
     * @param Request $request HTTP请求对象，包含：
     *   - conversation_id: 对话ID（必填）
     *   - content: 消息内容（必填，最大1000字符）
     * 
     * @return \Illuminate\Http\JsonResponse 发送结果和消息对象
     * 
     * @example
     * POST /api/customer-service/messages
     * {
     *   "conversation_id": 123,
     *   "content": "请问你们的工作时间是什么时候？"
     * }
     */
    public function sendMessage(Request $request)
    {
        // 验证输入数据
        $data = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'content' => 'required|string|max:1000'
        ]);

        // 检查对话是否属于当前用户（安全验证）
        $conversation = Conversation::where('id', $data['conversation_id'])
            ->where('user_id', auth()->id())
            ->first();

        if (!$conversation) {
            return response()->json([
                'status' => 1,
                'message' => '对话不存在或无权限'
            ], 403);
        }

        // 如果对话已结束，自动重新激活
        if ($conversation->status === 2) {
            $conversation->update(['status' => 1]); // 设为进行中
        }

        // 发送消息
        $messageService = new MessageService();
        $message = $messageService->sendMessage([
            'conversation_id' => $data['conversation_id'],
            'sender_type' => 0, // 用户发送
            'sender_id' => auth()->id(),
            'content' => $data['content'],
            'content_type' => 'text'
        ]);

        return response()->json([
            'status' => 0,
            'data' => $message,
            'message' => '消息发送成功'
        ]);
    }

    /**
     * 获取指定对话的消息历史
     * 
     * 返回对话中的所有消息记录，按时间顺序排列。
     * 包含安全验证，确保用户只能查看自己的对话消息。
     * 
     * @param int $id 对话ID
     * @return \Illuminate\Http\JsonResponse 消息列表数据
     * 
     * @example
     * GET /api/customer-service/conversations/123/messages
     */
    public function getMessages($id)
    {
        // 验证对话归属权限
        $conversation = Conversation::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$conversation) {
            return response()->json([
                'status' => 1,
                'message' => '对话不存在或无权限'
            ], 403);
        }

        $conversationService = new ConversationService();
        $messages = $conversationService->getConversationMessages($id);

        return response()->json([
            'status' => 0,
            'data' => $messages
        ]);
    }

    /**
     * 获取当前用户的对话列表
     * 
     * 返回用户创建的所有对话，按最后消息时间倒序排列。
     * 每页显示20条记录，支持分页。
     * 包含每条对话的最新消息信息。
     * 
     * @param Request $request HTTP请求对象（支持分页参数）
     * @return \Illuminate\Http\JsonResponse 用户对话列表
     * 
     * @example
     * GET /api/customer-service/conversations?page=1
     */
    public function getConversations(Request $request)
    {
        $conversations = Conversation::where('user_id', auth()->id())
            ->with(['lastMessage']) // 预加载最新消息
            ->orderBy('last_message_time', 'desc') // 按最新消息排序
            ->paginate(20);

        return response()->json([
            'status' => 0,
            'data' => $conversations
        ]);
    }
}