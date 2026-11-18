<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\StoreChartOfAccountRequest;
use App\Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Web: hệ thống tài khoản (dạng cây cha–con). */
class ChartOfAccountController
{
    public function index(): Response
    {
        $accounts = ChartOfAccount::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'parent_id', 'is_active']);

        return Inertia::render('Accounting/ChartOfAccounts/Index', [
            'tree' => $this->buildTree($accounts),
            'types' => ChartOfAccount::TYPES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounting/ChartOfAccounts/Create', [
            'parents' => ChartOfAccount::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'types' => ChartOfAccount::TYPES,
        ]);
    }

    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        ChartOfAccount::create($request->validated());

        return redirect()
            ->route('accounting.chart-of-accounts.index')
            ->with('success', 'Đã thêm tài khoản mới.');
    }

    /** Xếp danh sách phẳng thành cây theo parent_id (đệ quy). */
    protected function buildTree($accounts, ?int $parentId = null): array
    {
        return $accounts
            ->where('parent_id', $parentId)
            ->map(fn ($account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => ChartOfAccount::TYPES[$account->type] ?? $account->type,
                'is_active' => $account->is_active,
                'children' => $this->buildTree($accounts, $account->id),
            ])
            ->values()
            ->all();
    }
}
