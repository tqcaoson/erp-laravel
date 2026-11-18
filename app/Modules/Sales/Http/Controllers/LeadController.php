<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Lead;
use App\Modules\Sales\Requests\StoreLeadRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý đầu mối (Inertia). */
class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query();

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Sales/Leads/Index', [
            'leads' => $query->latest()->paginate(15)->withQueryString(),
            'statuses' => Lead::STATUS_LABELS,
            'filters' => $request->only(['search', 'filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Sales/Leads/Create', ['lead' => null]);
    }

    public function store(StoreLeadRequest $request)
    {
        Lead::create($request->validated());

        return redirect()->route('sales.leads.index')->with('success', 'Đã tạo đầu mối.');
    }

    public function update(StoreLeadRequest $request, Lead $lead)
    {
        $lead->update($request->validated());

        return redirect()->route('sales.leads.index')->with('success', 'Đã cập nhật đầu mối.');
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return redirect()->route('sales.leads.index')->with('success', 'Đã xóa đầu mối.');
    }
}
