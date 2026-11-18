<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\WorkCenter;
use App\Modules\Manufacturing\Requests\StoreWorkCenterRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý trung tâm sản xuất (Inertia). */
class WorkCenterController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkCenter::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return Inertia::render('Manufacturing/WorkCenters/Index', [
            'workCenters' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Manufacturing/WorkCenters/Create', ['workCenter' => null]);
    }

    public function store(StoreWorkCenterRequest $request)
    {
        $data = $request->validated();
        $data['code'] = $data['code'] ?? $this->nextCode();

        WorkCenter::create($data);

        return redirect()->route('manufacturing.work-centers.index')->with('success', 'Đã tạo trung tâm sản xuất.');
    }

    public function edit(WorkCenter $workCenter)
    {
        return Inertia::render('Manufacturing/WorkCenters/Create', ['workCenter' => $workCenter]);
    }

    public function update(StoreWorkCenterRequest $request, WorkCenter $workCenter)
    {
        $workCenter->update($request->validated());

        return redirect()->route('manufacturing.work-centers.index')->with('success', 'Đã cập nhật trung tâm sản xuất.');
    }

    protected function nextCode(): string
    {
        $count = WorkCenter::where('company_id', CurrentCompany::id())
            ->whereYear('created_at', now()->year)
            ->count();

        return sprintf('WC-%s-%04d', now()->format('Y'), $count + 1);
    }
}
