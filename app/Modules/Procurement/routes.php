<?php

use App\Modules\Procurement\Http\Controllers\GoodsReceiptApiController;
use App\Modules\Procurement\Http\Controllers\GoodsReceiptController;
use App\Modules\Procurement\Http\Controllers\PurchaseOrderApiController;
use App\Modules\Procurement\Http\Controllers\PurchaseOrderController;
use App\Modules\Procurement\Http\Controllers\PurchaseRequestController;
use App\Modules\Procurement\Http\Controllers\RfqController;
use App\Modules\Procurement\Http\Controllers\SupplierApiController;
use App\Modules\Procurement\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

// Trang web (Inertia) — luôn có 'web' + 'auth', phân quyền theo RolePermissionSeeder.
Route::middleware(['web', 'auth'])->prefix('procurement')->name('procurement.')->group(function () {
    // Nhà cung cấp (sidebar: /procurement/suppliers).
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:procurement.supplier.view')->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->middleware('permission:procurement.supplier.manage')->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:procurement.supplier.manage')->name('suppliers.store');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('permission:procurement.supplier.manage')->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:procurement.supplier.manage')->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:procurement.supplier.manage')->name('suppliers.destroy');

    // Yêu cầu mua hàng.
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->middleware('permission:procurement.pr.view')->name('purchase-requests.index');
    Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->middleware('permission:procurement.pr.create')->name('purchase-requests.create');
    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->middleware('permission:procurement.pr.create')->name('purchase-requests.store');
    Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->middleware('permission:procurement.pr.view')->name('purchase-requests.show');
    Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->middleware('permission:procurement.pr.approve')->name('purchase-requests.approve');
    Route::post('/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->middleware('permission:procurement.pr.approve')->name('purchase-requests.reject');
    Route::post('/purchase-requests/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])->middleware('permission:procurement.pr.create')->name('purchase-requests.cancel');

    // Đơn mua hàng (sidebar: /procurement/purchase-orders).
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:procurement.po.view')->name('purchase-orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->middleware('permission:procurement.po.create')->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:procurement.po.create')->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:procurement.po.view')->name('purchase-orders.show');
    Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->middleware('permission:procurement.po.create')->name('purchase-orders.submit');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:procurement.po.approve')->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:procurement.po.create')->name('purchase-orders.cancel');
    Route::post('/purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->middleware('permission:procurement.po.approve')->name('purchase-orders.close');

    // Phiếu nhận hàng.
    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->middleware('permission:procurement.gr.view')->name('goods-receipts.index');
    Route::get('/goods-receipts/create', [GoodsReceiptController::class, 'create'])->middleware('permission:procurement.gr.create')->name('goods-receipts.create');
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])->middleware('permission:procurement.gr.create')->name('goods-receipts.store');
    Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->middleware('permission:procurement.gr.view')->name('goods-receipts.show');
    Route::post('/goods-receipts/{goodsReceipt}/validate', [GoodsReceiptController::class, 'validateReceipt'])->middleware('permission:procurement.gr.create')->name('goods-receipts.validate');
    Route::post('/goods-receipts/{goodsReceipt}/cancel', [GoodsReceiptController::class, 'cancel'])->middleware('permission:procurement.gr.create')->name('goods-receipts.cancel');

    // Yêu cầu báo giá (RFQ).
    Route::get('/rfqs', [RfqController::class, 'index'])->middleware('permission:procurement.rfq.view')->name('rfqs.index');
    Route::get('/rfqs/create', [RfqController::class, 'create'])->middleware('permission:procurement.rfq.manage')->name('rfqs.create');
    Route::post('/rfqs', [RfqController::class, 'store'])->middleware('permission:procurement.rfq.manage')->name('rfqs.store');
    Route::get('/rfqs/{rfq}', [RfqController::class, 'show'])->middleware('permission:procurement.rfq.view')->name('rfqs.show');
    Route::post('/rfqs/{rfq}/status', [RfqController::class, 'changeStatus'])->middleware('permission:procurement.rfq.manage')->name('rfqs.status');
    Route::delete('/rfqs/{rfq}', [RfqController::class, 'destroy'])->middleware('permission:procurement.rfq.manage')->name('rfqs.destroy');
});

// API — prefix api/v1, middleware 'api' + 'auth:sanctum'.
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/procurement')->name('api.procurement.')->group(function () {
    Route::get('/suppliers', [SupplierApiController::class, 'index'])->middleware('permission:procurement.supplier.view')->name('suppliers.index');
    Route::post('/suppliers', [SupplierApiController::class, 'store'])->middleware('permission:procurement.supplier.manage')->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierApiController::class, 'show'])->middleware('permission:procurement.supplier.view')->name('suppliers.show');
    Route::put('/suppliers/{supplier}', [SupplierApiController::class, 'update'])->middleware('permission:procurement.supplier.manage')->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierApiController::class, 'destroy'])->middleware('permission:procurement.supplier.manage')->name('suppliers.destroy');

    Route::get('/purchase-orders', [PurchaseOrderApiController::class, 'index'])->middleware('permission:procurement.po.view')->name('purchase-orders.index');
    Route::post('/purchase-orders', [PurchaseOrderApiController::class, 'store'])->middleware('permission:procurement.po.create')->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderApiController::class, 'show'])->middleware('permission:procurement.po.view')->name('purchase-orders.show');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderApiController::class, 'approve'])->middleware('permission:procurement.po.approve')->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderApiController::class, 'cancel'])->middleware('permission:procurement.po.create')->name('purchase-orders.cancel');

    Route::get('/goods-receipts', [GoodsReceiptApiController::class, 'index'])->middleware('permission:procurement.gr.view')->name('goods-receipts.index');
    Route::post('/goods-receipts', [GoodsReceiptApiController::class, 'store'])->middleware('permission:procurement.gr.create')->name('goods-receipts.store');
    Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptApiController::class, 'show'])->middleware('permission:procurement.gr.view')->name('goods-receipts.show');
    Route::post('/goods-receipts/{goodsReceipt}/validate', [GoodsReceiptApiController::class, 'validateReceipt'])->middleware('permission:procurement.gr.create')->name('goods-receipts.validate');
});
