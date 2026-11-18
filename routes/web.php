<?php

use App\Core\Http\Controllers\AuthController;
use App\Core\Http\Controllers\CompanyController;
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
});

// Route /dashboard và toàn bộ route module nằm trong routes.php của từng
// module (app/Modules/*/routes.php), được nạp bởi ModuleServiceProvider.
