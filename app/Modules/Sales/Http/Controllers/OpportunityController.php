<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\Services\DocumentNumberingService;
use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Lead;
use App\Modules\Sales\Models\Opportunity;
use App\Modules\Sales\Requests\StoreOpportunityRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web pipeline cơ hội bán hàng — kanban theo giai đoạn (Inertia). */
class OpportunityController extends Controller
{
    public function __construct(protected DocumentNumberingService $numbering) {}

    public function index()
    {
        $opportunities = Opportunity::with(['customer:id,name', 'owner:id,name'])
            ->orderBy('expected_close_date')
            ->get()
            ->groupBy('stage')
            ->map(fn ($group) => $group->values())
            ->all();

        // Đảm bảo đủ 6 cột kanban kể cả khi chưa có cơ hội ở giai đoạn đó.
        $board = [];
        foreach (Opportunity::STAGES as $stage) {
            $board[$stage] = $opportunities[$stage] ?? [];
        }

        return Inertia::render('Sales/Opportunities/Index', [
            'board' => $board,
            'stages' => Opportunity::STAGE_LABELS,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'leads' => Lead::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreOpportunityRequest $request)
    {
        $data = $request->validated();
        $data['code'] = $this->numbering->next('opportunity');
        $data['owner_id'] = $data['owner_id'] ?? auth()->id();

        Opportunity::create($data);

        return redirect()->route('sales.opportunities.index')->with('success', 'Đã tạo cơ hội bán hàng.');
    }

    public function update(StoreOpportunityRequest $request, Opportunity $opportunity)
    {
        $opportunity->update($request->validated());

        return redirect()->route('sales.opportunities.index')->with('success', 'Đã cập nhật cơ hội.');
    }

    /** Đổi giai đoạn (kéo thả trên kanban). */
    public function updateStage(Request $request, Opportunity $opportunity)
    {
        $validated = $request->validate([
            'stage' => ['required', 'in:'.implode(',', Opportunity::STAGES)],
        ]);

        $opportunity->update($validated);

        return redirect()->route('sales.opportunities.index')->with('success', 'Đã chuyển giai đoạn cơ hội.');
    }

    public function destroy(Opportunity $opportunity)
    {
        $opportunity->delete();

        return redirect()->route('sales.opportunities.index')->with('success', 'Đã xóa cơ hội.');
    }
}
