<?php

use App\Modules\Inventory\Http\Controllers\ProductApiController;
use App\Modules\Inventory\Http\Controllers\ProductController;
use App\Modules\Inventory\Http\Controllers\StockApiController;
use App\Modules\Inventory\Http\Controllers\StockController;
use App\Modules\Inventory\Http\Controllers\StockCountController;
use App\Modules\Inventory\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// Trang web (Inertia) — luôn có 'web' + 'auth', phân quyền theo RolePermissionSeeder.
Route::middleware(['web', 'auth'])->prefix('inventory')->name('inventory.')->group(function () {
    // Sản phẩm (sidebar: /inventory/products).
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:inventory.product.view')->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('permission:inventory.product.manage')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:inventory.product.manage')->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:inventory.product.manage')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:inventory.product.manage')->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:inventory.product.manage')->name('products.destroy');

    // Kho hàng.
    Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('permission:inventory.warehouse.view')->name('warehouses.index');
    Route::get('/warehouses/create', [WarehouseController::class, 'create'])->middleware('permission:inventory.warehouse.manage')->name('warehouses.create');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:inventory.warehouse.manage')->name('warehouses.store');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->middleware('permission:inventory.warehouse.manage')->name('warehouses.edit');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:inventory.warehouse.manage')->name('warehouses.update');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:inventory.warehouse.manage')->name('warehouses.destroy');

    // Tồn kho (sidebar: /inventory/stock) + phiếu nhập/xuất/chuyển + sổ kho.
    Route::get('/stock', [StockController::class, 'index'])->middleware('permission:inventory.stock.view')->name('stock.index');
    Route::get('/stock/move', [StockController::class, 'moveForm'])->middleware('permission:inventory.stock.move')->name('stock.move-form');
    Route::post('/stock/move', [StockController::class, 'move'])->middleware('permission:inventory.stock.move')->name('stock.move');
    Route::get('/stock-moves', [StockController::class, 'moves'])->middleware('permission:inventory.stock.view')->name('stock.moves');

    // Kiểm kê kho.
    Route::get('/stock-counts', [StockCountController::class, 'index'])->middleware('permission:inventory.stock.count')->name('stock-counts.index');
    Route::get('/stock-counts/create', [StockCountController::class, 'create'])->middleware('permission:inventory.stock.count')->name('stock-counts.create');
    Route::post('/stock-counts', [StockCountController::class, 'store'])->middleware('permission:inventory.stock.count')->name('stock-counts.store');
    Route::get('/stock-counts/{stockCount}', [StockCountController::class, 'show'])->middleware('permission:inventory.stock.count')->name('stock-counts.show');
    Route::put('/stock-counts/{stockCount}/count', [StockCountController::class, 'updateCounted'])->middleware('permission:inventory.stock.count')->name('stock-counts.update-counted');
    Route::post('/stock-counts/{stockCount}/complete', [StockCountController::class, 'complete'])->middleware('permission:inventory.stock.count')->name('stock-counts.complete');
    Route::post('/stock-counts/{stockCount}/cancel', [StockCountController::class, 'cancel'])->middleware('permission:inventory.stock.count')->name('stock-counts.cancel');
});

// API — prefix api/v1, middleware 'api' + 'auth:sanctum'.
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/inventory')->name('api.inventory.')->group(function () {
    Route::get('/stock', [StockApiController::class, 'index'])->middleware('permission:inventory.stock.view')->name('stock.index');
    Route::get('/stock-moves', [StockApiController::class, 'moves'])->middleware('permission:inventory.stock.view')->name('stock-moves.index');
    Route::post('/stock-moves', [StockApiController::class, 'storeMove'])->middleware('permission:inventory.stock.move')->name('stock-moves.store');

    Route::get('/products', [ProductApiController::class, 'index'])->middleware('permission:inventory.product.view')->name('products.index');
    Route::post('/products', [ProductApiController::class, 'store'])->middleware('permission:inventory.product.manage')->name('products.store');
    Route::get('/products/{product}', [ProductApiController::class, 'show'])->middleware('permission:inventory.product.view')->name('products.show');
    Route::put('/products/{product}', [ProductApiController::class, 'update'])->middleware('permission:inventory.product.manage')->name('products.update');
    Route::delete('/products/{product}', [ProductApiController::class, 'destroy'])->middleware('permission:inventory.product.manage')->name('products.destroy');
});
