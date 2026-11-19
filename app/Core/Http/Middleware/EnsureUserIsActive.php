<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đá user đã bị vô hiệu (is_active = false) ra khỏi hệ thống ngay ở
 * request tiếp theo — kể cả khi họ đang đăng nhập. Gắn vào nhóm web
 * trong bootstrap/app.php nên chạy cho mọi request web đã đăng nhập.
 * Đọc lại trạng thái từ DB (fresh) thay vì tin bản user đang giữ trong
 * session/guard, vì admin có thể vừa vô hiệu tài khoản đó.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! (bool) $user->fresh()?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên.'], 403);
            }

            return redirect()->route('login')
                ->with('error', 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên.');
        }

        return $next($request);
    }
}
