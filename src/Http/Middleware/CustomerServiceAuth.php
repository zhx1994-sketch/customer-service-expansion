<?php

namespace PartTimeDevelopment\CustomerServiceExpansion\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 客服后台认证中间件
 *
 * 用途：
 * - 保护客服扩展的管理端接口，仅允许已登录的管理员访问
 * - 对 admin-api 下的接口返回 JSON 形式的 401 提示
 * - 对页面请求直接返回 401（可根据需要跳转到后台登录页）
 *
 * 使用方式：
 * - 在 ServiceProvider 中通过 aliasMiddleware 注册为 customer-service.auth
 * - 在路由中使用 middleware(['customer-service.auth'])
 */
class CustomerServiceAuth
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 校验管理员守卫是否已登录
        if (auth('admin')->check()) {
            return $next($request);
        }

        // 接口请求：返回 JSON 401
        // 说明：owl-admin 的接口统一位于 admin-api 前缀下
        if ($request->expectsJson() || $request->is('admin-api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 页面请求：直接返回 401
        // 如需跳转到后台登录页面，可改为：return redirect()->guest(admin_url('login'));
        abort(401, 'Unauthenticated.');
    }
}
