<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Opportunity;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Requests\StoreQuotationRequest;
use App\Modules\Sales\Requests\UpdateQuotationRequest;
use App\Modules\Sales\Services\QuotationService;
use App\Modules\Sales\Support\CatalogLookup;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý báo giá (Inertia). */
class QuotationController extends Controller
{
    use CatalogLookup;

    public function __construct(protected QuotationService $quotations) {}

    public function index(Request $request)
    {
        $query = Quotation::query()->with('customer:id,name');

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        return Inertia::render('Sales/Quotations/Index', [
            'quotations' => $query->latest()->paginate(15)->withQueryString(),
            'statuses' => Quotation::STATUS_LABELS,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Sales/Quotations/Create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'opportunities' => Opportunity::orderBy('name')->get(['id', 'name']),
            'products' => $this->productList(),
        ]);
    }

    public function store(StoreQuotationRequest $request)
    {
        $quotation = $this->quotations->create($request->validated());

        return redirect()->route('sales.quotations.show', $quotation)->with('success', "Đã tạo báo giá {$quotation->code}.");
    }

    /** Form sửa báo giá — chỉ báo giá nháp mới sửa được. */
    public function edit(Quotation $quotation)
    {
        if ($quotation->status !== 'draft') {
            return redirect()->route('sales.quotations.show', $quotation)
                ->with('error', 'Chỉ sửa được báo giá ở trạng thái nháp.');
        }

        $quotation->load('lines');

        return Inertia::render('Sales/Quotations/Edit', [
            'quotation' => $quotation,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'opportunities' => Opportunity::orderBy('name')->get(['id', 'name']),
            'products' => $this->productList(),
        ]);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation)
    {
        try {
            $quotation = $this->quotations->update($quotation, $request->validated());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.quotations.show', $quotation)->with('success', "Đã cập nhật báo giá {$quotation->code}.");
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['customer', 'opportunity', 'lines']);
        $products = $this->productMap($quotation->lines->pluck('product_id')->all());
        $quotation->lines->each(function ($line) use ($products) {
            $line->product_name = $products[$line->product_id]['name'] ?? ('Sản phẩm #'.$line->product_id);
            $line->product_sku = $products[$line->product_id]['sku'] ?? null;
        });

        return Inertia::render('Sales/Quotations/Show', [
            'quotation' => $quotation,
            'warehouses' => $this->warehouseList(),
        ]);
    }

    /** Đổi trạng thái báo giá (gửi / chấp nhận / từ chối / hết hạn). */
    public function setStatus(Request $request, Quotation $quotation)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', Quotation::STATUSES)],
        ]);

        try {
            $this->quotations->setStatus($quotation, $validated['status']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật trạng thái báo giá.');
    }

    /** Chuyển báo giá đã chấp nhận thành đơn bán. */
    public function convert(Request $request, Quotation $quotation)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ]);

        try {
            $order = $this->quotations->convertToSalesOrder($quotation, (int) $validated['warehouse_id']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.orders.show', $order)
            ->with('success', "Đã tạo đơn bán {$order->code} từ báo giá.");
    }
}
