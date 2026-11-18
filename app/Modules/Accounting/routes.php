<?php

use App\Modules\Accounting\Http\Controllers\ChartOfAccountController;
use App\Modules\Accounting\Http\Controllers\FiscalPeriodController;
use App\Modules\Accounting\Http\Controllers\InvoiceApiController;
use App\Modules\Accounting\Http\Controllers\InvoiceController;
use App\Modules\Accounting\Http\Controllers\JournalEntryApiController;
use App\Modules\Accounting\Http\Controllers\JournalEntryController;
use App\Modules\Accounting\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/**
 * Route module Kế toán (web Inertia + API v1).
 * File tự khai middleware theo hợp đồng mục 6 — ModuleServiceProvider
 * không áp middleware chung.
 */

// Web (Inertia) — luôn có 'web' + 'auth'.
Route::middleware(['web', 'auth'])->prefix('accounting')->name('accounting.')->group(function () {
    // Hệ thống tài khoản.
    Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])
        ->middleware('permission:accounting.account.view')
        ->name('chart-of-accounts.index');
    Route::get('/chart-of-accounts/create', [ChartOfAccountController::class, 'create'])
        ->middleware('permission:accounting.account.manage')
        ->name('chart-of-accounts.create');
    Route::post('/chart-of-accounts', [ChartOfAccountController::class, 'store'])
        ->middleware('permission:accounting.account.manage')
        ->name('chart-of-accounts.store');

    // Bút toán.
    Route::get('/journal-entries', [JournalEntryController::class, 'index'])
        ->middleware('permission:accounting.entry.view')
        ->name('journal-entries.index');
    Route::get('/journal-entries/create', [JournalEntryController::class, 'create'])
        ->middleware('permission:accounting.entry.create')
        ->name('journal-entries.create');
    Route::post('/journal-entries', [JournalEntryController::class, 'store'])
        ->middleware('permission:accounting.entry.create')
        ->name('journal-entries.store');
    Route::get('/journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])
        ->middleware('permission:accounting.entry.view')
        ->name('journal-entries.show');
    Route::post('/journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
        ->middleware('permission:accounting.entry.post')
        ->name('journal-entries.post');
    Route::post('/journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])
        ->middleware('permission:accounting.entry.reverse')
        ->name('journal-entries.reverse');

    // Hóa đơn.
    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->middleware('permission:accounting.invoice.view')
        ->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])
        ->middleware('permission:accounting.invoice.create')
        ->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware('permission:accounting.invoice.create')
        ->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('permission:accounting.invoice.view')
        ->name('invoices.show');
    Route::post('/invoices/{invoice}/post', [InvoiceController::class, 'post'])
        ->middleware('permission:accounting.invoice.post')
        ->name('invoices.post');
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])
        ->middleware('permission:accounting.payment.create')
        ->name('invoices.pay');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
        ->middleware('permission:accounting.invoice.post')
        ->name('invoices.cancel');

    // Phiếu thu/chi.
    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('permission:accounting.payment.view')
        ->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])
        ->middleware('permission:accounting.payment.create')
        ->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('permission:accounting.payment.create')
        ->name('payments.store');
    Route::post('/payments/{payment}/post', [PaymentController::class, 'post'])
        ->middleware('permission:accounting.payment.create')
        ->name('payments.post');

    // Kỳ kế toán.
    Route::get('/fiscal-periods', [FiscalPeriodController::class, 'index'])
        ->middleware('permission:accounting.period.manage')
        ->name('fiscal-periods.index');
    Route::post('/fiscal-periods', [FiscalPeriodController::class, 'store'])
        ->middleware('permission:accounting.period.manage')
        ->name('fiscal-periods.store');
    Route::post('/fiscal-periods/{fiscalPeriod}/lock', [FiscalPeriodController::class, 'lock'])
        ->middleware('permission:accounting.period.manage')
        ->name('fiscal-periods.lock');
    Route::post('/fiscal-periods/{fiscalPeriod}/unlock', [FiscalPeriodController::class, 'unlock'])
        ->middleware('permission:accounting.period.manage')
        ->name('fiscal-periods.unlock');
});

// API v1 — middleware 'api' + 'auth:sanctum'.
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/accounting')->name('api.accounting.')->group(function () {
    Route::get('/journal-entries', [JournalEntryApiController::class, 'index'])
        ->middleware('permission:accounting.entry.view')
        ->name('journal-entries.index');
    Route::post('/journal-entries', [JournalEntryApiController::class, 'store'])
        ->middleware('permission:accounting.entry.create')
        ->name('journal-entries.store');
    Route::get('/journal-entries/{journalEntry}', [JournalEntryApiController::class, 'show'])
        ->middleware('permission:accounting.entry.view')
        ->name('journal-entries.show');
    Route::post('/journal-entries/{journalEntry}/post', [JournalEntryApiController::class, 'post'])
        ->middleware('permission:accounting.entry.post')
        ->name('journal-entries.post');
    Route::post('/journal-entries/{journalEntry}/reverse', [JournalEntryApiController::class, 'reverse'])
        ->middleware('permission:accounting.entry.reverse')
        ->name('journal-entries.reverse');

    Route::get('/invoices', [InvoiceApiController::class, 'index'])
        ->middleware('permission:accounting.invoice.view')
        ->name('invoices.index');
    Route::post('/invoices', [InvoiceApiController::class, 'store'])
        ->middleware('permission:accounting.invoice.create')
        ->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceApiController::class, 'show'])
        ->middleware('permission:accounting.invoice.view')
        ->name('invoices.show');
    Route::post('/invoices/{invoice}/post', [InvoiceApiController::class, 'post'])
        ->middleware('permission:accounting.invoice.post')
        ->name('invoices.post');
});
