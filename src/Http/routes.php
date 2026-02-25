<?php

use Illuminate\Support\Facades\Route;
use PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers\CustomerServiceExpansionController;
use PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers\ConversationController;
use PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers\MessageController;

// 后台管理路由


Route::prefix('admin/customer-service')->middleware(['admin.auth'])->group(function () {
    // 根路径，默认进入工作台
    Route::get('/', [CustomerServiceExpansionController::class, 'workbench']);
    // 客服工作台
    Route::get('/workbench', [CustomerServiceExpansionController::class, 'workbench']);
    
    // 对话管理
    Route::get('/conversations', [CustomerServiceExpansionController::class, 'conversations']);
    Route::get('/conversations/{id}', [ConversationController::class, 'detail']);
    Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{id}/mark-read', [CustomerServiceExpansionController::class, 'markConversationRead']);
    Route::post('/conversations/{id}/end', [ConversationController::class, 'endConversation']);
    
    // 消息管理
    Route::post('/messages', [CustomerServiceExpansionController::class, 'sendMessage']);
    
    // 统计信息
    Route::get('/stats/total', [CustomerServiceExpansionController::class, 'getStats']);
    Route::get('/stats/read', [CustomerServiceExpansionController::class, 'getStats']);
    Route::get('/stats/unread', [CustomerServiceExpansionController::class, 'getStats']);
    Route::get('/stats/unread-messages', [CustomerServiceExpansionController::class, 'getStats']);
    Route::get('/stats/today', [CustomerServiceExpansionController::class, 'getStats']);
    
    // WebSocket认证
    Route::post('/broadcasting/auth', function () {
        $ws = config('customer-service.websocket');
        $options = [
            'host'   => $ws['host'] ?? '127.0.0.1',
            'port'   => $ws['port'] ?? 6001,
            'scheme' => !empty($ws['ssl']) ? 'https' : 'http',
            'useTLS' => !empty($ws['ssl']),
        ];

        $pusher = new \Pusher\Pusher(
            $ws['pusher_key']   ?? '',
            $ws['pusher_secret']?? '',
            $ws['pusher_app_id']?? '',
            $options
        );

        $channel  = request('channel_name');
        $socketId = request('socket_id');
        $user     = auth('admin')->user() ?: auth()->user();

        if (is_string($channel) && str_starts_with($channel, 'presence-')) {
            $userId   = $user ? (string)$user->getAuthIdentifier() : 'guest';
            $userInfo = $user ? ['name' => $user->name ?? 'admin'] : [];
            return $pusher->presence_auth($channel, $socketId, $userId, $userInfo);
        }

        return $pusher->socket_auth($channel, $socketId);
    });

    // 生成用户端页面
    Route::post('/generate-frontend', function () {
        $language = request('language', 'js');
        $base = public_path('vendor/customer-service-expansion');
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }
        if ($language === 'vue') {
            $dir = $base . DIRECTORY_SEPARATOR . 'vue';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $content = <<<VUE
<template>
  <div class="customer-service">
    <h3>Customer Service</h3>
  </div>
</template>
<script>
export default {
  name: 'CustomerService',
  mounted() {
    const key = document.querySelector('meta[name="pusher-key"]')?.content || '';
    const host = document.querySelector('meta[name="websocket-host"]')?.content || '127.0.0.1';
    const port = parseInt(document.querySelector('meta[name="websocket-port"]')?.content || '6001', 10);
    const useTLS = (document.querySelector('meta[name="websocket-ssl"]')?.content === 'true');
    if (window.Pusher) {
      this.pusher = new window.Pusher(key, { wsHost: host, wsPort: port, forceTLS: useTLS, enabledTransports: ['ws','wss'] });
    }
  }
}
</script>
<style scoped>
.customer-service { padding: 12px; }
</style>
VUE;
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'CustomerService.vue', $content);
        } else {
            $dir = $base . DIRECTORY_SEPARATOR . 'js';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $content = <<<JS
(function(){
  var key = document.querySelector('meta[name="pusher-key"]')?.getAttribute('content') || '';
  var host = document.querySelector('meta[name="websocket-host"]')?.getAttribute('content') || '127.0.0.1';
  var port = parseInt(document.querySelector('meta[name="websocket-port"]')?.getAttribute('content') || '6001', 10);
  var useTLS = (document.querySelector('meta[name="websocket-ssl"]')?.getAttribute('content') === 'true');
  if (window.Pusher) {
    var pusher = new window.Pusher(key, { wsHost: host, wsPort: port, forceTLS: useTLS, enabledTransports: ['ws','wss'] });
  }
  console.log('customer-service frontend (js) ready');
})();
JS;
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'customer-service-frontend.js', $content);
        }
        return response()->json(['status' => 0, 'message' => '生成成功', 'language' => $language]);
    });

    // 获取/保存用户端页面内容（用于设置页 Tab 编辑）
    Route::match(['get', 'post'], '/frontend', function () {
        $language = request('language', 'js');
        $base = public_path('vendor/customer-service-expansion');
        if ($language === 'vue') {
            $file = $base . DIRECTORY_SEPARATOR . 'vue' . DIRECTORY_SEPARATOR . 'CustomerService.vue';
            $default = "<template>\n  <div class=\"customer-service\">\n    <h3>Customer Service</h3>\n  </div>\n</template>\n<script>\nexport default { name: 'CustomerService' }\n</script>\n<style scoped>\n.customer-service { padding: 12px; }\n</style>\n";
        } else {
            $file = $base . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'customer-service-frontend.js';
            $default = "(function(){ console.log('customer-service frontend (js)'); })();\n";
        }

        if (request()->isMethod('get')) {
            if (is_file($file)) {
                $content = file_get_contents($file);
            } else {
                // 若文件不存在，先创建目录与默认文件，便于编辑
                @mkdir(dirname($file), 0755, true);
                file_put_contents($file, $default);
                $content = $default;
            }
            return response()->json(['status' => 0, 'content' => $content, 'path' => $file, 'language' => $language]);
        }

        // 保存
        $content = request('content', '');
        @mkdir(dirname($file), 0755, true);
        file_put_contents($file, (string)$content);
        return response()->json(['status' => 0, 'message' => '保存成功', 'path' => $file, 'language' => $language]);
    });
});

// 用户端API路由
Route::prefix('api/customer-service')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/conversations', [\PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers\UserApiController::class, 'createConversation']);
    Route::post('/messages', [\PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers\UserApiController::class, 'sendMessage']);
    Route::get('/conversations/{id}/messages', [\PartTimeDevelopment\CustomerServiceExpansion\Http\Controllers\UserApiController::class, 'getMessages']);
});

// 用户端页面路由
Route::get('/customer-service', function () {
    return view('customer-service-expansion::index');
});
