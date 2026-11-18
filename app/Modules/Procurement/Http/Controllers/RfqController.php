<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Core\Services\DocumentNumberingService;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Models\Rfq;
use App\Modules\Procurement\Models\RfqLine;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Requests\RfqRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Trang web yêu cầu báo giá (RFQ) — CRUD cơ bản + chuyển trạng thái. */
class RfqController extends Controller
{
    use ListsResources;

    public function __construct(
        protected DocumentNumberingService $numbering,
    ) {}

    public function index(Request $request)
    {
        $query = Rfq::query()->with(['supplier'])->withCount('lines');

        $this->applyFilters($query, $request, ['status']);
        $this->applySort($query, $request, ['code', 'created_at']);

        return Inertia::render('Procurement/Rfqs/Index', [
            'rfqs' => $query->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only(['sort', 'filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Procurement/Rfqs/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }

    public function store(RfqRequest $request)
    {
        $data = $request->validated();
        $companyId = CurrentCompany::id();

        $rfq = DB::transaction(function () use ($data, $companyId) {
            $rfq = Rfq::create([
                'company_id' => $companyId,
                'code' => $this->numbering->next('rfq', $companyId),
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'deadline' => $data['deadline'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                RfqLine::create([
                    'company_id' => $companyId,
                    'rfq_id' => $rfq->id,
                    'product_id' => $line['product_id'],
                    'qty' => (float) $line['qty'],
                    'quoted_price' => $line['quoted_price'] ?? null,
                ]);
            }

            return $rfq;
        });

        return redirect()->route('procurement.rfqs.show', $rfq)->with('success', 'Đã tạo yêu cầu báo giá '.$rfq->code.'.');
    }

    public function show(Rfq $rfq)
    {
        return Inertia::render('Procurement/Rfqs/Show', [
            'rfq' => $rfq->load(['supplier', 'lines.product']),
        ]);
    }

    /** Chuyển trạng thái RFQ: gửi, đã nhận báo giá, chấp nhận, từ chối. */
    public function changeStatus(Request $request, Rfq $rfq)
    {
        $request->validate(['status' => ['required', 'in:sent,received,accepted,rejected']]);

        $allowed = [
            'draft' => ['sent'],
            'sent' => ['received', 'rejected'],
            'received' => ['accepted', 'rejected'],
        ];

        if (! in_array($request->input('status'), $allowed[$rfq->status] ?? [], true)) {
            return redirect()->back()->with('error', 'Không thể chuyển báo giá từ trạng thái '.$rfq->status.' sang '.$request->input('status').'.');
        }

        $rfq->update(['status' => $request->input('status')]);

        return redirect()->back()->with('success', 'Đã cập nhật trạng thái báo giá.');
    }

    public function destroy(Rfq $rfq)
    {
        if ($rfq->status !== 'draft') {
            return redirect()->back()->with('error', 'Chỉ báo giá nháp mới được xóa.');
        }

        $rfq->delete();

        return redirect()->route('procurement.rfqs.index')->with('success', 'Đã xóa yêu cầu báo giá.');
    }
}
