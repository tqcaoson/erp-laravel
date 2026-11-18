<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Requests\StoreQuotationRequest;
use App\Modules\Sales\Services\QuotationService;
use DomainException;
use Illuminate\Http\Request;

/** REST API báo giá: danh sách, tạo, xem, chuyển thành đơn bán. */
class QuotationApiController extends Controller
{
    public function __construct(protected QuotationService $quotations) {}

    public function index(Request $request)
    {
        $query = Quotation::query()->with('customer:id,name');

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        if ($request->filled('filter.customer_id')) {
            $query->where('customer_id', $request->input('filter.customer_id'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    public function store(StoreQuotationRequest $request)
    {
        $quotation = $this->quotations->create($request->validated());

        return response()->json($quotation->load(['customer:id,name', 'lines']), 201);
    }

    public function show(Quotation $quotation)
    {
        return response()->json($quotation->load(['customer', 'lines']));
    }

    public function convert(Request $request, Quotation $quotation)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ]);

        try {
            $order = $this->quotations->convertToSalesOrder($quotation, (int) $validated['warehouse_id']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($order->load('lines'), 201);
    }
}
