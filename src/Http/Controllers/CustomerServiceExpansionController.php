<?php

/**
 * 客服系统主要控制器
 * 
 * 负责处理客服工作台的主要功能，包括：
 * - 客服工作台界面
 * - 对话列表管理
 * - 消息发送
 * - 统计信息获取
 * 
 * @package PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers
 * @version 1.0.0
 */

namespace PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers;

use Slowlyo\OwlAdmin\Controllers\AdminController;
use PartTimeDevelopment\CustomerServiceExpansion\Models\Conversation;
use PartTimeDevelopment\CustomerServiceExpansion\Models\Message;
use PartTimeDevelopment\CustomerServiceExpansion\Services\ConversationService;
use PartTimeDevelopment\CustomerServiceExpansion\Services\MessageService;
use Illuminate\Http\Request;

class CustomerServiceExpansionController extends AdminController
{
    /**
     * 客服工作台页面
     * 
     * 显示客服工作的主要界面，包括：
     * - 顶部统计信息面板（总对话数、已读数、未读数等）
     * - 左侧对话列表（按最新消息排序）
     * - 右侧聊天区域（显示选中对话的消息）
     * 
     * @return \Illuminate\Http\Response JSON格式的页面数据
     */
    public function workbench()
    {
        $stats = amis()->Grid()->columns([
            amis()->Card()->className('text-center')->body(
                amis()->Service()->api('/admin/customer-service/stats/total')->body(
                    amis()->Tpl()->tpl('<div style="font-size:12px;color:#999">总对话数</div><div style="font-size:28px;font-weight:600;margin-top:6px">${total_conversations|default:0}</div>')
                )
            ),
            amis()->Card()->className('text-center')->body(
                amis()->Service()->api('/admin/customer-service/stats/read')->body(
                    amis()->Tpl()->tpl('<div style="font-size:12px;color:#999">已读对话</div><div style="font-size:28px;font-weight:600;margin-top:6px">${read_conversations|default:0}</div>')
                )
            ),
            amis()->Card()->className('text-center')->body(
                amis()->Service()->api('/admin/customer-service/stats/unread')->body(
                    amis()->Tpl()->tpl('<div style="font-size:12px;color:#999">未读对话</div><div style="font-size:28px;font-weight:600;margin-top:6px">${unread_conversations|default:0}</div>')
                )
            ),
            amis()->Card()->className('text-center')->body(
                amis()->Service()->api('/admin/customer-service/stats/unread-messages')->body(
                    amis()->Tpl()->tpl('<div style="font-size:12px;color:#999">未读消息</div><div style="font-size:28px;font-weight:600;margin-top:6px">${unread_messages|default:0}</div>')
                )
            ),
            amis()->Card()->className('text-center')->body(
                amis()->Service()->api('/admin/customer-service/stats/today')->body(
                    amis()->Tpl()->tpl('<div style="font-size:12px;color:#999">今日消息</div><div style="font-size:28px;font-weight:600;margin-top:6px">${today_messages|default:0}</div>')
                )
            ),
        ]);

        $list = $this->baseCRUD()
            ->api('/admin/customer-service/conversations')
            ->keepItemSelectionOnPageChange(true)
            ->headerToolbar([
                amis()->ReloadAction(),
                'filter-toggler',
            ])
            ->filter(amis()->Form()->wrapWithPanel(false)->body([
                amis()->TextControl('keyword', '关键词'),
                amis()->SelectControl('status', '状态')->options([
                    ['label' => '进行中', 'value' => 1],
                    ['label' => '已结束', 'value' => 2],
                ])->clearable(true),
            ]))
            ->columns([
                amis()->TableColumn('id', 'ID')->width(60),
                amis()->TableColumn('title', '标题')->toggable(true)->quickEdit(false),
                amis()->TableColumn('user.nickname', '用户')->width(120)->type('tpl')->tpl('${user.nickname|default:"-"}'),
                amis()->TableColumn('last_message_content', '最新消息')->type('tpl')->tpl('<span title="${last_message_content}">${last_message_content|truncate:24}</span>'),
                amis()->TableColumn('unread_count', '未读')->width(80)->type('tpl')->tpl('<span class="badge badge-danger">${unread_count|default:0}</span>'),
                amis()->TableColumn('last_message_time', '最新时间')->type('datetime')->format('YYYY-MM-DD HH:mm')->width(150),
                amis()->TableColumn('', '')->width(120)->type('operation')->buttons([
                    amis()->Action()->label('查看')->level('primary')->actionType('drawer')->drawer(
                        amis()->Drawer()->title('对话消息')->size('lg')->body(
                            amis()->Service()->api('/admin/customer-service/conversations/${id}/messages')->body(
                                [
                                    'type'   => 'table',
                                    'source' => '${messages}',
                                    'columns'=> [
                                        ['name' => 'created_at', 'label' => '时间', 'type' => 'tpl', 'tpl' => '${created_at|date:YYYY-MM-DD HH:mm}'],
                                        ['name' => 'sender_type', 'label' => '角色', 'type' => 'tpl', 'tpl' => '${sender_type == 0 ? "用户" : "客服"}'],
                                        ['name' => 'content', 'label' => '内容', 'type' => 'tpl', 'tpl' => '${content}'],
                                    ]
                                ]
                            )
                        )
                    ),
                ]),
            ]);

        $page = $this->basePage()
            ->title('客服工作台')
            ->className('customer-service-workbench')
            ->body([
                $stats,
                amis()->Grid()->columns([
                    amis()->Panel()->title('对话列表')->body([$list])->className('mr-2')->set('md', 4),
                    amis()->Panel()->title('请选择左侧对话进行查看')->body(
                        amis()->Alert()->level('info')->body('从左侧列表点击“查看”打开消息抽屉')
                    )->set('md', 8),
                ]),
            ]);

        return $this->response()->success($page);
    }

    /**
     * 获取对话列表API接口
     * 
     * 返回对话列表，支持以下功能：
     * - 按最新消息时间倒序排列
     * - 支持关键词搜索（搜索用户昵称或对话标题）
     * - 支持按状态筛选
     * - 包含未读消息数统计
     * - 显示最新消息内容预览
     * 
     * @param Request $request HTTP请求对象，包含搜索和筛选参数
     * @return \Illuminate\Http\Response JSON格式的对话列表数据
     * 
     * @example
     * GET /admin-api/customer-service/conversations?keyword=测试&status=1
     */
    public function conversations(Request $request)
    {
        $conversationService = new ConversationService();
        $conversations = $conversationService->getConversations($request->all());
        
        return $this->response()->success($conversations);
    }

    /**
     * 标记指定对话为已读状态
     * 
     * 该方法会将对话中所有用户发送的未读消息标记为已读，
     * 同时更新阅读时间为当前时间。这通常在客服点击查看对话时自动触发。
     * 
     * @param Request $request HTTP请求对象
     * @param int $id 对话ID
     * @return \Illuminate\Http\Response 操作结果
     * 
     * @example
     * POST /admin-api/customer-service/conversations/123/mark-read
     */
    public function markConversationRead(Request $request, $id)
    {
        $conversationService = new ConversationService();
        
        if ($conversationService->markAsRead($id)) {
            return $this->response()->successMessage('对话已标记为已读');
        }
        
        return $this->response()->fail('对话不存在');
    }

    /**
     * 发送消息API接口
     * 
     * 处理客服发送消息的请求，包括：
     * - 验证消息内容和对话ID的有效性
     * - 创建消息记录
     * - 更新对话的最后消息时间
     * - 触发WebSocket广播事件
     * 
     * @param Request $request HTTP请求对象，包含对话ID和消息内容
     * @return \Illuminate\Http\Response 创建的消息对象
     * 
     * @example
     * POST /admin-api/customer-service/messages
     * {
     *   "conversation_id": 123,
     *   "content": "您好，有什么可以帮助您的吗？"
     * }
     */
    public function sendMessage(Request $request)
    {
        // 验证请求数据
        $data = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'content' => 'required|string|max:1000'
        ]);

        $messageService = new MessageService();
        $message = $messageService->sendMessage([
            'conversation_id' => $data['conversation_id'],
            'sender_type' => 1, // 客服
            'sender_id' => admin()->id, // 当前登录的客服ID
            'content' => $data['content'],
            'content_type' => 'text'
        ]);

        return $this->response()->success($message);
    }

    /**
     * 获取客服系统统计信息
     * 
     * 根据不同的统计类型返回相应的数据：
     * - total: 总对话数
     * - read: 已读对话数
     * - unread: 未读对话数  
     * - unread-messages: 未读消息数
     * - today: 今日消息数
     * 
     * @param string $type 统计类型，默认为'total'
     * @return \Illuminate\Http\Response JSON格式的统计数据
     * 
     * @example
     * GET /admin-api/customer-service/stats/unread  // 获取未读对话数
     * GET /admin-api/customer-service/stats/today   // 获取今日消息数
     */
    public function getStats($type = 'total')
    {
        $conversationService = new ConversationService();
        $stats = $conversationService->getStats($type);

        return $this->response()->success($stats);
    }
}
