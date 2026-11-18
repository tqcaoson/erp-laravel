<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\StoreJournalEntryRequest;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Web: bút toán tổng hợp (xem, tạo tay, ghi sổ, đảo). */
class JournalEntryController
{
    public function __construct(protected JournalEntryService $entries) {}

    public function index(Request $request): Response
    {
        $entries = JournalEntry::query()
            ->with('fiscalPeriod')
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('search'), fn ($q, $search) => $q->where('code', 'like', "%{$search}%"))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Accounting/JournalEntries/Index', [
            'entries' => $entries,
            'filters' => [
                'status' => $request->input('filter.status'),
                'search' => $request->input('search'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounting/JournalEntries/Create', [
            'accounts' => ChartOfAccount::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        try {
            $entry = $this->entries->create($request->validated());
        } catch (DomainException $e) {
            return back()->withErrors(['entry_date' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('accounting.journal-entries.show', $entry)
            ->with('success', "Đã tạo bút toán {$entry->code} (nháp).");
    }

    public function show(JournalEntry $journalEntry): Response
    {
        $journalEntry->load(['lines.account', 'fiscalPeriod', 'reversedBy']);

        return Inertia::render('Accounting/JournalEntries/Show', [
            'entry' => $journalEntry,
        ]);
    }

    public function post(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->entries->postEntry($journalEntry);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Đã ghi sổ bút toán {$journalEntry->code}.");
    }

    public function reverse(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $reversal = $this->entries->reverse($journalEntry);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('accounting.journal-entries.show', $reversal)
            ->with('success', "Đã đảo bút toán {$journalEntry->code} thành {$reversal->code}.");
    }
}
