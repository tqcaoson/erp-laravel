<?php

use App\Core\Http\Middleware\CheckPermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Providers\ModuleServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        ModuleServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Inertia chạy trong nhóm web để mọi trang đều nhận được shared props.
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Middleware phân quyền theo permission dạng "module.action", dùng: permission:sales.order.create
        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API luôn trả JSON, kể cả khi lỗi xác thực / không tìm thấy route.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $e instanceof \Illuminate\Auth\AuthenticationException => 401,
                $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException,
                $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => 404,
                $e instanceof \Illuminate\Validation\ValidationException => 422,
                default => $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                    ? $e->getStatusCode()
                    : 500,
            };

            $payload = ['message' => $e->getMessage() ?: 'Lỗi hệ thống.'];

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $payload['errors'] = $e->errors();
            }

            return response()->json($payload, $status);
        });
    })->create();
