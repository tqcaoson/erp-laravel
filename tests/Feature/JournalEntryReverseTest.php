<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/** Đảo bút toán (6a): redirect về gốc, chặn đảo trùng và đảo bút toán đảo. */
class JournalEntryReverseTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    /** Tạo bút toán cân bằng đã ghi sổ (Nợ 111 / Có 511, 1.000.000). */
    protected function createPostedEntry(): JournalEntry
    {
        $service = app(JournalEntryService::class);

        $entry = $service->create([
            'entry_date' => now()->toDateString(),
            'description' => 'Bút toán kiểm thử đảo',
            'lines' => [
                ['account_code' => '111', 'debit' => 1000000, 'credit' => 0],
                ['account_code' => '511', 'debit' => 0, 'credit' => 1000000],
            ],
        ], $this->company->id);

        return $service->postEntry($entry);
    }

    public function test_đảo_bút_toán_sinh_bút_toán_đảo_ngược_và_gốc_chuyển_reversed(): void
    {
        $this->seedBaseData();
        $entry = $this->createPostedEntry();

        $reversal = app(JournalEntryService::class)->reverse($entry->fresh());

        $this->assertSame('posted', $reversal->status);
        $this->assertSame(JournalEntry::class, $reversal->source_type);
        $this->assertSame($entry->id, $reversal->source_id);
        $this->assertNotSame($entry->code, $reversal->code);

        // Bút toán đảo đảo ngược Nợ/Có từng dòng.
        $reversedLines = $reversal->fresh()->lines->sortBy('id')->values();
        $this->assertSame(0.0, (float) $reversedLines[0]->debit);
        $this->assertSame(1000000.0, (float) $reversedLines[0]->credit);
        $this->assertSame(1000000.0, (float) $reversedLines[1]->debit);
        $this->assertSame(0.0, (float) $reversedLines[1]->credit);

        $original = $entry->fresh();
        $this->assertSame('reversed', $original->status);
        $this->assertSame($reversal->id, $original->reversed_by_id);
    }

    public function test_đảo_lần_2_cùng_bút_toán_gốc_bị_chặn(): void
    {
        $this->seedBaseData();
        $entry = $this->createPostedEntry();

        app(JournalEntryService::class)->reverse($entry->fresh());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('đã ghi sổ');

        app(JournalEntryService::class)->reverse($entry->fresh());
    }

    public function test_đảo_bút_toán_đảo_bị_chặn(): void
    {
        $this->seedBaseData();
        $entry = $this->createPostedEntry();

        $reversal = app(JournalEntryService::class)->reverse($entry->fresh());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('bút toán đảo');

        app(JournalEntryService::class)->reverse($reversal->fresh());
    }

    public function test_controller_reverse_redirect_về_bút_toán_gốc(): void
    {
        $this->seedBaseData();
        $entry = $this->createPostedEntry();

        $response = $this->actingAs($this->admin)
            ->post('/accounting/journal-entries/'.$entry->id.'/reverse');

        $response->assertRedirect(route('accounting.journal-entries.show', $entry->id))
            ->assertSessionHas('success');

        // Flash message phải nêu mã bút toán đảo để người dùng biết.
        $reversal = JournalEntry::where('source_type', JournalEntry::class)
            ->where('source_id', $entry->id)
            ->firstOrFail();
        $this->assertStringContainsString(
            $reversal->code,
            (string) $response->getSession()->get('success')
        );
        $this->assertSame('reversed', $entry->fresh()->status);
    }

    public function test_controller_đảo_lần_2_báo_lỗi_thay_vì_tạo_thêm_bút_toán(): void
    {
        $this->seedBaseData();
        $entry = $this->createPostedEntry();

        $this->actingAs($this->admin)->post('/accounting/journal-entries/'.$entry->id.'/reverse');

        $this->actingAs($this->admin)
            ->from('/accounting/journal-entries/'.$entry->id)
            ->post('/accounting/journal-entries/'.$entry->id.'/reverse')
            ->assertRedirect('/accounting/journal-entries/'.$entry->id)
            ->assertSessionHasErrors('status');

        $this->assertSame(1, JournalEntry::where('source_type', JournalEntry::class)
            ->where('source_id', $entry->id)->count());
    }
}
