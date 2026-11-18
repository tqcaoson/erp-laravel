<?php

namespace Tests\Feature;

use App\Modules\Accounting\Services\JournalEntryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesErpFixtures;
use Tests\TestCase;

/** Bút toán mất cân bằng (tổng Nợ khác tổng Có) phải bị từ chối khi ghi sổ. */
class UnbalancedJournalEntryTest extends TestCase
{
    use CreatesErpFixtures, RefreshDatabase;

    public function test_bút_toán_mất_cân_bằng_bị_từ_chối_khi_ghi_sổ(): void
    {
        $this->seedBaseData();

        $service = app(JournalEntryService::class);

        $entry = $service->create([
            'entry_date' => now()->toDateString(),
            'description' => 'Bút toán kiểm thử mất cân bằng',
            'lines' => [
                ['account_code' => '111', 'debit' => 1000000, 'credit' => 0],
                ['account_code' => '511', 'debit' => 0, 'credit' => 500000],
            ],
        ], $this->company->id);

        $this->assertSame('draft', $entry->status);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('không cân bằng');

        $service->postEntry($entry);
    }

    public function test_bút_toán_cân_bằng_ghi_sổ_thành_công(): void
    {
        $this->seedBaseData();

        $service = app(JournalEntryService::class);

        $entry = $service->create([
            'entry_date' => now()->toDateString(),
            'description' => 'Bút toán kiểm thử cân bằng',
            'lines' => [
                ['account_code' => '111', 'debit' => 1000000, 'credit' => 0],
                ['account_code' => '511', 'debit' => 0, 'credit' => 1000000],
            ],
        ], $this->company->id);

        $posted = $service->postEntry($entry);

        $this->assertSame('posted', $posted->status);
    }
}
