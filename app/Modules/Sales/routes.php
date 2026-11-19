<?php

use App\Modules\Sales\Http\Controllers\CustomerApiController;
use App\Modules\Sales\Http\Controllers\CustomerController;
use App\Modules\Sales\Http\Controllers\DeliveryOrderController;
use App\Modules\Sales\Http\Controllers\LeadController;
use App\Modules\Sales\Http\Controllers\OpportunityController;
use App\Modules\Sales\Http\Controllers\PriceListController;
use App\Modules\Sales\Http\Controllers\QuotationApiController;
use App\Modules\Sales\Http\Controllers\QuotationController;
use App\Modules\Sales\Http\Controllers\SalesOrderApiController;
use App\Modules\Sales\Http\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

// Web (Inertia) — luôn có 'web' + 'auth', phân quyền theo RolePermissionSeeder.
Route::middleware(['web', 'auth'])->prefix('sales')->name('sales.')->group(function () {
    // Khách hàng
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:sales.customer.view')->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->middleware('permission:sales.customer.manage')->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:sales.customer.manage')->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:sales.customer.view')->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('permission:sales.customer.manage')->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:sales.customer.manage')->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:sales.customer.manage')->name('customers.destroy');

    // Đầu mối
    Route::get('/leads', [LeadController::class, 'index'])->middleware('permission:sales.lead.view')->name('leads.index');
    Route::get('/leads/create', [LeadController::class, 'create'])->middleware('permission:sales.lead.manage')->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->middleware('permission:sales.lead.manage')->name('leads.store');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->middleware('permission:sales.lead.manage')->name('leads.update');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->middleware('permission:sales.lead.manage')->name('leads.destroy');

    // Cơ hội bán hàng (kanban)
    Route::get('/opportunities', [OpportunityController::class, 'index'])->middleware('permission:sales.opportunity.view')->name('opportunities.index');
    Route::post('/opportunities', [OpportunityController::class, 'store'])->middleware('permission:sales.opportunity.manage')->name('opportunities.store');
    Route::put('/opportunities/{opportunity}', [OpportunityController::class, 'update'])->middleware('permission:sales.opportunity.manage')->name('opportunities.update');
    Route::patch('/opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage'])->middleware('permission:sales.opportunity.manage')->name('opportunities.stage');
    Route::delete('/opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->middleware('permission:sales.opportunity.manage')->name('opportunities.destroy');

    // Báo giá
    Route::get('/quotations', [QuotationController::class, 'index'])->middleware('permission:sales.quotation.view')->name('quotations.index');
    Route::get('/quotations/create', [QuotationController::class, 'create'])->middleware('permission:sales.quotation.create')->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->middleware('permission:sales.quotation.create')->name('quotations.store');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->middleware('permission:sales.quotation.view')->name('quotations.show');
    Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->middleware('permission:sales.quotation.create')->name('quotations.edit');
    Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->middleware('permission:sales.quotation.create')->name('quotations.update');
    Route::post('/quotations/{quotation}/status', [QuotationController::class, 'setStatus'])->middleware('permission:sales.quotation.create')->name('quotations.status');
    Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->middleware('permission:sales.order.create')->name('quotations.convert');

    // Tồn khả dụng (JSON) phục vụ form tạo đơn khi đổi kho.
    Route::get('/stock-availability', [SalesOrderController::class, 'stockAvailability'])->middleware('permission:sales.order.create')->name('stock-availability');

    // Đơn bán hàng
    Route::get('/orders', [SalesOrderController::class, 'index'])->middleware('permission:sales.order.view')->name('orders.index');
    Route::get('/orders/create', [SalesOrderController::class, 'create'])->middleware('permission:sales.order.create')->name('orders.create');
    Route::post('/orders', [SalesOrderController::class, 'store'])->middleware('permission:sales.order.create')->name('orders.store');
    Route::get('/orders/{order}', [SalesOrderController::class, 'show'])->middleware('permission:sales.order.view')->name('orders.show');
    Route::get('/orders/{order}/edit', [SalesOrderController::class, 'edit'])->middleware('permission:sales.order.create')->name('orders.edit');
    Route::put('/orders/{order}', [SalesOrderController::class, 'update'])->middleware('permission:sales.order.create')->name('orders.update');
    Route::post('/orders/{order}/confirm', [SalesOrderController::class, 'confirm'])->middleware('permission:sales.order.confirm')->name('orders.confirm');
    Route::post('/orders/{order}/approve', [SalesOrderController::class, 'approve'])->middleware('permission:sales.order.approve')->name('orders.approve');
    Route::post('/orders/{order}/cancel', [SalesOrderController::class, 'cancel'])->middleware('permission:sales.order.confirm')->name('orders.cancel');
    Route::post('/orders/{order}/deliver-remaining', [SalesOrderController::class, 'deliverRemaining'])->middleware('permission:sales.delivery.validate')->name('orders.deliver-remaining');
    Route::post('/orders/{order}/create-invoice', [SalesOrderController::class, 'createInvoice'])->middleware('permission:accounting.invoice.create')->name('orders.create-invoice');

    // Phiếu giao hàng
    Route::get('/deliveries', [DeliveryOrderController::class, 'index'])->middleware('permission:sales.delivery.view')->name('deliveries.index');
    Route::get('/deliveries/{deliveryOrder}', [DeliveryOrderController::class, 'show'])->middleware('permission:sales.delivery.view')->name('deliveries.show');
    Route::post('/deliveries/{deliveryOrder}/validate', [DeliveryOrderController::class, 'validate'])->middleware('permission:sales.delivery.validate')->name('deliveries.validate');
    Route::post('/deliveries/{deliveryOrder}/cancel', [DeliveryOrderController::class, 'cancel'])->middleware('permission:sales.delivery.validate')->name('deliveries.cancel');

    // Bảng giá
    Route::get('/price-lists', [PriceListController::class, 'index'])->middleware('permission:sales.price.view')->name('price-lists.index');
    Route::get('/price-lists/create', [PriceListController::class, 'create'])->middleware('permission:sales.price.manage')->name('price-lists.create');
    Route::post('/price-lists', [PriceListController::class, 'store'])->middleware('permission:sales.price.manage')->name('price-lists.store');
    Route::delete('/price-lists/{priceList}', [PriceListController::class, 'destroy'])->middleware('permission:sales.price.manage')->name('price-lists.destroy');
});

// API — prefix api/v1, middleware 'api' + 'auth:sanctum'.
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/sales')->name('api.sales.')->group(function () {
    Route::get('/orders', [SalesOrderApiController::class, 'index'])->middleware('permission:sales.order.view');
    Route::post('/orders', [SalesOrderApiController::class, 'store'])->middleware('permission:sales.order.create');
    Route::get('/orders/{order}', [SalesOrderApiController::class, 'show'])->middleware('permission:sales.order.view');
    Route::post('/orders/{order}/confirm', [SalesOrderApiController::class, 'confirm'])->middleware('permission:sales.order.confirm');
    Route::post('/orders/{order}/approve', [SalesOrderApiController::class, 'approve'])->middleware('permission:sales.order.approve');
    Route::post('/orders/{order}/cancel', [SalesOrderApiController::class, 'cancel'])->middleware('permission:sales.order.confirm');

    // Tồn khả dụng phục vụ form tạo đơn (đổi kho thì tải lại).
    Route::get('/stock-availability', [SalesOrderApiController::class, 'stockAvailability'])->middleware('permission:sales.order.create');

    Route::get('/customers', [CustomerApiController::class, 'index'])->middleware('permission:sales.customer.view');
    Route::post('/customers', [CustomerApiController::class, 'store'])->middleware('permission:sales.customer.manage');
    Route::get('/customers/{customer}', [CustomerApiController::class, 'show'])->middleware('permission:sales.customer.view');

    Route::get('/quotations', [QuotationApiController::class, 'index'])->middleware('permission:sales.quotation.view');
    Route::post('/quotations', [QuotationApiController::class, 'store'])->middleware('permission:sales.quotation.create');
    Route::get('/quotations/{quotation}', [QuotationApiController::class, 'show'])->middleware('permission:sales.quotation.view');
    Route::post('/quotations/{quotation}/convert', [QuotationApiController::class, 'convert'])->middleware('permission:sales.order.create');
});
