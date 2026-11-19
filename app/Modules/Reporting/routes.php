<?php

use App\Modules\Reporting\Http\Controllers\DashboardController;
use App\Modules\Reporting\Http\Controllers\ReportApiController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Dashboard chính — route quan trọng nhất: trang login redirect về đây
// và sidebar trỏ tới đây. Chỉ cần đăng nhập (không bắt buộc quyền
// reporting.dashboard.view để tránh khóa user mới chưa có vai trò;
// KPI bên trong được lọc theo công ty VÀ theo quyền của user trong
// DashboardController, nên role nào cũng mở được trang nhưng mỗi role
// chỉ thấy số liệu thuộc module của mình).
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Trang báo cáo (Inertia).
Route::middleware(['web', 'auth'])->prefix('reports')->name('reports.')->group(function () {
    Route::middleware('permission:reporting.report.view')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/revenue-by-customer', [ReportController::class, 'revenueByCustomer'])->name('revenue-by-customer');
        Route::get('/aging', [ReportController::class, 'aging'])->name('aging');
        Route::get('/stock-movement', [ReportController::class, 'stockMovement'])->name('stock-movement');
    });
});

// API báo cáo.
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/reports')->name('api.reports.')->group(function () {
    Route::middleware('permission:reporting.report.view')->group(function () {
        Route::get('/revenue-by-customer', [ReportApiController::class, 'revenueByCustomer'])->name('revenue-by-customer');
        Route::get('/aging', [ReportApiController::class, 'aging'])->name('aging');
        Route::get('/stock-movement', [ReportApiController::class, 'stockMovement'])->name('stock-movement');
    });
});
