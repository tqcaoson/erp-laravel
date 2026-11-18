<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kiểm tra user có quyền chức năng hay không.
 * Dùng trong route: ->middleware('permission:sales.order.create')
 * Có thể truyền nhiều quyền, user chỉ cần có 1 trong số đó.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Chưa đăng nhập.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'Bạn không có quyền thực hiện thao tác này.');
    }
}
