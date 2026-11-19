<?php

use App\Modules\Manufacturing\Http\Controllers\BomApiController;
use App\Modules\Manufacturing\Http\Controllers\BomController;
use App\Modules\Manufacturing\Http\Controllers\ManufacturingOrderApiController;
use App\Modules\Manufacturing\Http\Controllers\ManufacturingOrderController;
use App\Modules\Manufacturing\Http\Controllers\RoutingController;
use App\Modules\Manufacturing\Http\Controllers\WorkCenterController;
use App\Modules\Manufacturing\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

// Web (Inertia) — luôn có 'web' + 'auth', phân quyền theo RolePermissionSeeder.
Route::middleware(['web', 'auth'])->prefix('manufacturing')->name('manufacturing.')->group(function () {
    // Lệnh sản xuất
    Route::get('/orders', [ManufacturingOrderController::class, 'index'])->middleware('permission:manufacturing.mo.view')->name('orders.index');
    Route::get('/orders/create', [ManufacturingOrderController::class, 'create'])->middleware('permission:manufacturing.mo.create')->name('orders.create');
    Route::post('/orders', [ManufacturingOrderController::class, 'store'])->middleware('permission:manufacturing.mo.create')->name('orders.store');
    Route::get('/orders/{order}', [ManufacturingOrderController::class, 'show'])->middleware('permission:manufacturing.mo.view')->name('orders.show');
    Route::post('/orders/{order}/confirm', [ManufacturingOrderController::class, 'confirm'])->middleware('permission:manufacturing.mo.create')->name('orders.confirm');
    Route::post('/orders/{order}/start', [ManufacturingOrderController::class, 'start'])->middleware('permission:manufacturing.mo.start')->name('orders.start');
    Route::post('/orders/{order}/complete', [ManufacturingOrderController::class, 'complete'])->middleware('permission:manufacturing.mo.complete')->name('orders.complete');
    Route::post('/orders/{order}/log-progress', [ManufacturingOrderController::class, 'logProgress'])->middleware('permission:manufacturing.mo.complete')->name('orders.log-progress');
    Route::post('/orders/{order}/cancel', [ManufacturingOrderController::class, 'cancel'])->middleware('permission:manufacturing.mo.create')->name('orders.cancel');

    // Định mức BOM
    Route::get('/boms', [BomController::class, 'index'])->middleware('permission:manufacturing.bom.view')->name('boms.index');
    Route::get('/boms/create', [BomController::class, 'create'])->middleware('permission:manufacturing.bom.manage')->name('boms.create');
    Route::post('/boms', [BomController::class, 'store'])->middleware('permission:manufacturing.bom.manage')->name('boms.store');
    Route::get('/boms/{bom}', [BomController::class, 'show'])->middleware('permission:manufacturing.bom.view')->name('boms.show');

    // Trung tâm sản xuất
    Route::get('/work-centers', [WorkCenterController::class, 'index'])->middleware('permission:manufacturing.workcenter.view')->name('work-centers.index');
    Route::get('/work-centers/create', [WorkCenterController::class, 'create'])->middleware('permission:manufacturing.workcenter.manage')->name('work-centers.create');
    Route::post('/work-centers', [WorkCenterController::class, 'store'])->middleware('permission:manufacturing.workcenter.manage')->name('work-centers.store');
    Route::get('/work-centers/{workCenter}/edit', [WorkCenterController::class, 'edit'])->middleware('permission:manufacturing.workcenter.manage')->name('work-centers.edit');
    Route::put('/work-centers/{workCenter}', [WorkCenterController::class, 'update'])->middleware('permission:manufacturing.workcenter.manage')->name('work-centers.update');

    // Quy trình sản xuất (routing)
    Route::get('/routings', [RoutingController::class, 'index'])->middleware('permission:manufacturing.routing.view')->name('routings.index');
    Route::get('/routings/create', [RoutingController::class, 'create'])->middleware('permission:manufacturing.routing.manage')->name('routings.create');
    Route::post('/routings', [RoutingController::class, 'store'])->middleware('permission:manufacturing.routing.manage')->name('routings.store');
    Route::get('/routings/{routing}', [RoutingController::class, 'show'])->middleware('permission:manufacturing.routing.view')->name('routings.show');

    // Công đoạn sản xuất
    Route::get('/work-orders', [WorkOrderController::class, 'index'])->middleware('permission:manufacturing.mo.view')->name('work-orders.index');
    Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->middleware('permission:manufacturing.workorder.manage')->name('work-orders.create');
    Route::post('/work-orders', [WorkOrderController::class, 'store'])->middleware('permission:manufacturing.workorder.manage')->name('work-orders.store');
    Route::get('/work-orders/{workOrder}/edit', [WorkOrderController::class, 'edit'])->middleware('permission:manufacturing.workorder.manage')->name('work-orders.edit');
    Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update'])->middleware('permission:manufacturing.workorder.manage')->name('work-orders.update');
    Route::delete('/work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->middleware('permission:manufacturing.workorder.manage')->name('work-orders.destroy');
    Route::patch('/work-orders/{workOrder}/status', [WorkOrderController::class, 'updateStatus'])->middleware('permission:manufacturing.mo.start')->name('work-orders.status');
});

// API — prefix api/v1, middleware 'api' + 'auth:sanctum'.
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/manufacturing')->name('api.manufacturing.')->group(function () {
    Route::get('/manufacturing-orders', [ManufacturingOrderApiController::class, 'index'])->middleware('permission:manufacturing.mo.view');
    Route::post('/manufacturing-orders', [ManufacturingOrderApiController::class, 'store'])->middleware('permission:manufacturing.mo.create');
    Route::get('/manufacturing-orders/{order}', [ManufacturingOrderApiController::class, 'show'])->middleware('permission:manufacturing.mo.view');
    Route::post('/manufacturing-orders/{order}/confirm', [ManufacturingOrderApiController::class, 'confirm'])->middleware('permission:manufacturing.mo.create');
    Route::post('/manufacturing-orders/{order}/start', [ManufacturingOrderApiController::class, 'start'])->middleware('permission:manufacturing.mo.start');
    Route::post('/manufacturing-orders/{order}/complete', [ManufacturingOrderApiController::class, 'complete'])->middleware('permission:manufacturing.mo.complete');

    Route::get('/boms', [BomApiController::class, 'index'])->middleware('permission:manufacturing.bom.view');
    Route::post('/boms', [BomApiController::class, 'store'])->middleware('permission:manufacturing.bom.manage');
    Route::get('/boms/{bom}', [BomApiController::class, 'show'])->middleware('permission:manufacturing.bom.view');
});
