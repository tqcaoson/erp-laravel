<?php

use App\Core\Http\Controllers\AuthController;
use App\Core\Http\Controllers\CompanyController;
use App\Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Trang gốc: đã đăng nhập thì vào dashboard, chưa thì vào trang đăng nhập.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Xác thực (session). Rate limit chống dò mật khẩu.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Đổi công ty đang làm việc ở topbar.
    Route::post('/company/switch', [CompanyController::class, 'switch'])->name('company.switch');

    // Quản lý tài khoản người dùng (admin).
    Route::middleware('permission:core.user.view')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    });
    Route::middleware('permission:core.user.manage')->group(function () {
        Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/admin/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('admin.users.toggle-active');
    });
});

// Route /dashboard và toàn bộ route module nằm trong routes.php của từng
// module (app/Modules/*/routes.php), được nạp bởi ModuleServiceProvider.
