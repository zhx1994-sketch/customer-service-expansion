<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在线客服</title>
    <meta name="pusher-key" content="{{ config('customer-service.websocket.pusher_key') }}">
    <meta name="websocket-host" content="{{ config('customer-service.websocket.host') }}">
    <meta name="websocket-port" content="{{ config('customer-service.websocket.port') }}">
    <meta name="websocket-ssl" content="{{ config('customer-service.websocket.ssl') ? 'true' : 'false' }}">
    @auth('admin')
        <meta name="admin-id" content="{{ auth('admin')->id }}">
    @endauth
    @auth
        <meta name="user-id" content="{{ auth()->id }}">
    @endauth
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div id="app">
        <h1>客服系统</h1>
        <p>客服系统正在加载中...</p>
    </div>

    <!-- WebSocket 客户端 -->
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script src="{{ asset('vendor/customer-service-expansion/js/customer-service-websocket.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/customer-service-expansion/css/workbench.css') }}">
</body>
</html>