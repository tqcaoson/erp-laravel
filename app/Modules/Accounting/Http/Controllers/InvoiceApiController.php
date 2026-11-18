<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\StoreInvoiceRequest;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\InvoiceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API v1: hóa đơn bán/mua. */
class InvoiceApiController
{
    use HandlesApiListing;

    public function __construct(protected InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->applyApiListing(
            $request,
            Invoice::query()->with('lines'),
            ['created_at', 'invoice_date', 'code', 'total_amount'],
        );

        return response()->json($query->paginate($this->apiPerPage($request)));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load(['lines', 'payments']));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->validated());

        return response()->json($invoice->load('lines'), 201);
    }

    public function post(Invoice $invoice): JsonResponse
    {
        try {
            $this->invoices->post($invoice);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($invoice->fresh()->load('lines'));
    }
}
