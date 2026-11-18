<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Requests\PurchaseRequestRequest;
use App\Modules\Procurement\Services\PurchaseRequestService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web yêu cầu mua hàng (Inertia). */
class PurchaseRequestController extends Controller
{
    use ListsResources;

    public function __construct(
        protected PurchaseRequestService $purchaseRequestService,
    ) {}

    public function index(Request $request)
    {
        $query = PurchaseRequest::query()->with(['requester'])->withCount('lines');

        $this->applyFilters($query, $request, ['status']);
        $this->applySort($query, $request, ['code', 'total_amount', 'created_at']);

        return Inertia::render('Procurement/PurchaseRequests/Index', [
            'requests' => $query->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only(['sort', 'filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Procurement/PurchaseRequests/Create', [
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'average_cost']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(PurchaseRequestRequest $request)
    {
        $purchaseRequest = $this->purchaseRequestService->create($request->validated(), CurrentCompany::id());

        return redirect()->route('procurement.purchase-requests.show', $purchaseRequest)
            ->with('success', 'Đã tạo yêu cầu mua '.$purchaseRequest->code.'.');
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        return Inertia::render('Procurement/PurchaseRequests/Show', [
            'purchaseRequest' => $purchaseRequest->load(['requester', 'lines.product', 'lines.warehouse']),
        ]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        try {
            $this->purchaseRequestService->approve($purchaseRequest->id, $request->input('note'));
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã duyệt yêu cầu mua hàng.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        try {
            $this->purchaseRequestService->reject($purchaseRequest->id, $request->input('note'));
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã từ chối yêu cầu mua hàng.');
    }

    public function cancel(PurchaseRequest $purchaseRequest)
    {
        try {
            $this->purchaseRequestService->cancel($purchaseRequest->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Đã hủy yêu cầu mua hàng.');
    }
}
