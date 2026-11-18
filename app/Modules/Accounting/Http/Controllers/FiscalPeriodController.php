<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\StoreFiscalPeriodRequest;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Modules\Accounting\Services\FiscalPeriodService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Web: kỳ kế toán (xem, tạo, khóa/mở kỳ). */
class FiscalPeriodController
{
    public function __construct(protected FiscalPeriodService $periods) {}

    public function index(): Response
    {
        return Inertia::render('Accounting/FiscalPeriods/Index', [
            'periods' => FiscalPeriod::query()
                ->orderByDesc('code')
                ->paginate(15),
        ]);
    }

    public function store(StoreFiscalPeriodRequest $request): RedirectResponse
    {
        FiscalPeriod::create($request->validated());

        return back()->with('success', "Đã tạo kỳ kế toán {$request->input('code')}.");
    }

    public function lock(FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        try {
            $this->periods->lock($fiscalPeriod);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Đã khóa kỳ {$fiscalPeriod->code}.");
    }

    public function unlock(FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        try {
            $this->periods->unlock($fiscalPeriod);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Đã mở lại kỳ {$fiscalPeriod->code}.");
    }
}
