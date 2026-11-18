<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Nạp toàn bộ module của hệ thống ERP (modular monolith).
 *
 * Mỗi module nằm trong app/Modules/<TenModule>/ và có thể chứa:
 *  - routes.php    : route web + api của module (bắt buộc nếu module có route).
 *  - events.php    : trả về mảng [Event::class => [Listener::class, ...]].
 *  - bindings.php  : trả về mảng [Interface::class => Implementation::class].
 *
 * ModuleServiceProvider tự quét 3 file trên, nên thêm module mới chỉ cần
 * tạo thư mục + 3 file này, không cần sửa provider.
 */
class ModuleServiceProvider extends ServiceProvider
{
    /** @var list<string> Danh sách module theo thứ tự nạp route. */
    public const MODULES = [
        'Accounting',
        'Procurement',
        'Inventory',
        'Sales',
        'Manufacturing',
        'HR',
        'Reporting',
    ];

    public function register(): void
    {
        // Đăng ký binding interface -> implementation của từng module.
        foreach (self::MODULES as $module) {
            $bindingsFile = app_path("Modules/{$module}/bindings.php");

            if (! file_exists($bindingsFile)) {
                continue;
            }

            foreach (require $bindingsFile as $abstract => $concrete) {
                $this->app->bind($abstract, $concrete);
            }
        }
    }

    public function boot(): void
    {
        $this->registerModuleEvents();
        $this->registerModuleRoutes();
    }

    /**
     * Đăng ký listener cho các domain event xuyên module.
     */
    protected function registerModuleEvents(): void
    {
        foreach (self::MODULES as $module) {
            $eventsFile = app_path("Modules/{$module}/events.php");

            if (! file_exists($eventsFile)) {
                continue;
            }

            foreach (require $eventsFile as $event => $listeners) {
                foreach ((array) $listeners as $listener) {
                    Event::listen($event, $listener);
                }
            }
        }
    }

    /**
     * Nạp routes.php của từng module.
     *
     * File routes.php TỰ khai báo middleware cho từng nhóm:
     *  - Route web: Route::middleware(['web', 'auth'])->group(...)
     *  - Route API: Route::prefix('api/v1/...')->middleware(['api', 'auth:sanctum'])->group(...)
     *
     * Không áp middleware chung ở đây, vì route API mà dính middleware
     * 'web' sẽ bị chặn CSRF khi gọi bằng Sanctum token.
     */
    protected function registerModuleRoutes(): void
    {
        foreach (self::MODULES as $module) {
            $routesFile = app_path("Modules/{$module}/routes.php");

            if (! file_exists($routesFile)) {
                continue;
            }

            Route::group([], $routesFile);
        }
    }
}
