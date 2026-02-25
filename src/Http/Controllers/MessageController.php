<?php

namespace PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers;

use Slowlyo\OwlAdmin\Controllers\AdminController;
use PartTimeDevelopment\CustomerServiceExpansion\Services\MessageService;
use Illuminate\Http\Request;

class MessageController extends AdminController
{
    /**
     * 获取消息列表
     */
    public function index(Request $request)
    {
        $messageService = new MessageService();
        
        // 这里可以根据需要实现消息列表功能
        return $this->response()->success([]);
    }

    /**
     * 获取消息详情
     */
    public function show($id)
    {
        $messageService = new MessageService();
        $message = $messageService->getMessage($id);

        if (!$message) {
            return $this->response()->fail('消息不存在');
        }

        return $this->response()->success($message);
    }
}
