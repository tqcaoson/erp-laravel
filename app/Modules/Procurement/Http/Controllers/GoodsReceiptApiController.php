<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Requests\GoodsReceiptRequest;
use App\Modules\Procurement\Services\GoodsReceiptService;
use DomainException;
use Illuminate\Http\Request;

/** API phiếu nhận hàng: GET/POST /api/v1/procurement/goods-receipts ... */
class GoodsReceiptApiController extends Controller
{
    use ListsResources;

    public function __construct(
        protected GoodsReceiptService $goodsReceiptService,
    ) {}

    public function index(Request $request)
    {
        $query = GoodsReceipt::query()->with(['supplier', 'warehouse', 'purchaseOrder']);

        $this->applyFilters($query, $request, ['status']);
        $this->applySort($query, $request, ['code', 'receipt_date', 'created_at']);

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function show(GoodsReceipt $goodsReceipt)
    {
        return response()->json($goodsReceipt->load(['supplier', 'warehouse', 'purchaseOrder', 'lines.product']));
    }

    public function store(GoodsReceiptRequest $request)
    {
        $receipt = $this->goodsReceiptService->create($request->validated(), CurrentCompany::id());

        return response()->json($receipt->load('lines'), 201);
    }

    public function validateReceipt(GoodsReceipt $goodsReceipt)
    {
        try {
            $receipt = $this->goodsReceiptService->validate($goodsReceipt->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($receipt);
    }
}
