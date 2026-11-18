<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\StoreJournalEntryRequest;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API v1: bút toán tổng hợp. */
class JournalEntryApiController
{
    use HandlesApiListing;

    public function __construct(protected JournalEntryService $entries) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->applyApiListing(
            $request,
            JournalEntry::query()->with(['lines.account', 'fiscalPeriod']),
            ['created_at', 'entry_date', 'code'],
        );

        return response()->json($query->paginate($this->apiPerPage($request)));
    }

    public function show(JournalEntry $journalEntry): JsonResponse
    {
        return response()->json($journalEntry->load(['lines.account', 'fiscalPeriod', 'reversedBy']));
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        try {
            $entry = $this->entries->create($request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($entry->load('lines'), 201);
    }

    public function post(JournalEntry $journalEntry): JsonResponse
    {
        try {
            $this->entries->postEntry($journalEntry);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($journalEntry->fresh()->load('lines'));
    }

    public function reverse(JournalEntry $journalEntry): JsonResponse
    {
        try {
            $reversal = $this->entries->reverse($journalEntry);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($reversal->load('lines'), 201);
    }
}
