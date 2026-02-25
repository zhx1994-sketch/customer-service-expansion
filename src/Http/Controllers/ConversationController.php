<?php

/**
 * 对话管理控制器
 * 
 * 专门处理对话相关的操作，包括：
 * - 获取对话详细信息
 * - 获取对话消息历史
 * - 结束对话操作
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers;

use Slowlyo\OwlAdmin\Controllers\AdminController;
use PartTimeDevelopment\CustomerServiceExpansion\Models\Conversation;
use PartTimeDevelopment\CustomerServiceExpansion\Services\ConversationService;
use Illuminate\Http\Request;

class ConversationController extends AdminController
{
    /**
     * 获取对话详细信息
     * 
     * 返回指定对话的完整信息，包括：
     * - 对话基本信息（用户、状态、标题等）
     * - 相关的所有消息记录
     * - 最新消息信息
     * 
     * 查看对话详情时会自动将该对话中的所有用户消息标记为已读
     * 
     * @param int $id 对话ID
     * @return \Illuminate\Http\Response 对话详细信息或错误信息
     * 
     * @example
     * GET /admin-api/customer-service/conversations/123
     */
    public function detail($id)
    {
        $conversationService = new ConversationService();
        $conversation = $conversationService->getConversationDetail($id);

        if (!$conversation) {
            return $this->response()->fail('对话不存在');
        }

        return $this->response()->success($conversation);
    }

    /**
     * 获取指定对话的消息列表
     * 
     * 返回对话中的所有消息，按发送时间升序排列。
     * 每条消息包含：
     * - 消息内容
     * - 发送者信息（用户或客服）
     * - 发送时间
     * - 已读状态
     * 
     * @param int $id 对话ID
     * @return \Illuminate\Http\Response 消息列表数据
     * 
     * @example
     * GET /admin-api/customer-service/conversations/123/messages
     */
    public function messages($id)
    {
        $conversationService = new ConversationService();
        $messages = $conversationService->getConversationMessages($id);

        return $this->response()->success($messages);
    }

    /**
     * 结束指定对话
     * 
     * 将对话状态设置为"已结束"（status=2），
     * 并记录结束时间。结束后对话将不再接收新消息，
     * 用户需要创建新的对话才能继续咨询。
     * 
     * @param Request $request HTTP请求对象
     * @param int $id 对话ID
     * @return \Illuminate\Http\Response 操作结果
     * 
     * @example
     * POST /admin-api/customer-service/conversations/123/end
     */
    public function endConversation(Request $request, $id)
    {
        $conversationService = new ConversationService();
        
        if ($conversationService->endConversation($id)) {
            return $this->response()->successMessage('对话已结束');
        }
        
        return $this->response()->fail('对话不存在');
    }
}
