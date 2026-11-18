<?php

namespace App\Modules\Accounting\Services;

use App\Core\Contracts\JournalPostingInterface;
use App\Core\Services\CurrentCompany;
use App\Core\Services\DocumentNumberingService;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ bút toán: tạo nháp, ghi sổ, đảo bút toán.
 * Đồng thời là implementation của JournalPostingInterface để các module
 * khác (Kho, Mua hàng, Nhân sự) ghi bút toán tự động bằng mã tài khoản.
 */
class JournalEntryService implements JournalPostingInterface
{
    public function __construct(
        protected DocumentNumberingService $numbering,
        protected FiscalPeriodService $periods,
    ) {}

    /**
     * Tạo bút toán ở trạng thái nháp (chưa kiểm tra cân bằng — kiểm tra
     * khi ghi sổ, để người dùng lưu nháp dở dang được).
     *
     * @param  array{entry_date: string, description?: string|null, source_type?: string|null, source_id?: int|null, lines: array<int, array{account_id?: int|null, account_code?: string|null, debit?: float, credit?: float, partner_type?: string|null, partner_id?: int|null, description?: string|null}>} $data
     */
    public function create(array $data, ?int $companyId = null): JournalEntry
    {
        $companyId ??= CurrentCompany::id();

        if (! $companyId) {
            throw new DomainException('Chưa xác định được công ty để tạo bút toán.');
        }

        return DB::transaction(function () use ($data, $companyId) {
            $period = $this->periods->findOrCreateOpenPeriod($data['entry_date'], $companyId);

            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'fiscal_period_id' => $period->id,
                'code' => $this->numbering->next('journal_entry', $companyId),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => JournalEntry::STATUS_DRAFT,
            ]);

            foreach ($data['lines'] as $line) {
                $entry->lines()->create([
                    'account_id' => $this->resolveAccountId($line, $companyId),
                    'partner_type' => $line['partner_type'] ?? null,
                    'partner_id' => $line['partner_id'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Ghi sổ bút toán nháp: kiểm tra cân bằng Nợ = Có và kỳ chưa khóa.
     *
     * @throws DomainException khi bút toán không ở trạng thái nháp,
     *                         mất cân bằng, hoặc kỳ đã khóa.
     */
    public function postEntry(JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== JournalEntry::STATUS_DRAFT) {
            throw new DomainException('Chỉ ghi sổ được bút toán đang ở trạng thái nháp.');
        }

        $entry->loadMissing(['lines', 'fiscalPeriod']);

        if ($entry->lines->isEmpty()) {
            throw new DomainException('Bút toán chưa có dòng nào, không ghi sổ được.');
        }

        $totalDebit = (float) $entry->lines->sum('debit');
        $totalCredit = (float) $entry->lines->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new DomainException(sprintf(
                'Bút toán không cân bằng: tổng Nợ %s khác tổng Có %s.',
                number_format($totalDebit, 2, ',', '.'),
                number_format($totalCredit, 2, ',', '.'),
            ));
        }

        if ($entry->fiscalPeriod && $entry->fiscalPeriod->isClosed()) {
            throw new DomainException("Kỳ kế toán {$entry->fiscalPeriod->code} đã bị khóa, không ghi sổ được.");
        }

        $entry->update(['status' => JournalEntry::STATUS_POSTED]);

        return $entry;
    }

    /**
     * Đảo bút toán đã ghi sổ: sinh bút toán mới đảo ngược Nợ/Có và ghi sổ
     * ngay, bút toán gốc chuyển sang trạng thái reversed. Bút toán gốc
     * không bị sửa hay xóa (giữ lại để đối chiếu).
     *
     * @throws DomainException khi bút toán chưa ghi sổ hoặc kỳ hiện tại đã khóa.
     */
    public function reverse(JournalEntry $entry, ?string $reversalDate = null): JournalEntry
    {
        if ($entry->status !== JournalEntry::STATUS_POSTED) {
            throw new DomainException('Chỉ đảo được bút toán đã ghi sổ.');
        }

        $entry->loadMissing('lines');
        $reversalDate ??= now()->toDateString();

        return DB::transaction(function () use ($entry, $reversalDate) {
            $reversal = $this->create([
                'entry_date' => $reversalDate,
                'description' => "Đảo bút toán {$entry->code}".($entry->description ? " — {$entry->description}" : ''),
                'source_type' => JournalEntry::class,
                'source_id' => $entry->id,
                'lines' => $entry->lines->map(fn ($line) => [
                    'account_id' => $line->account_id,
                    'partner_type' => $line->partner_type,
                    'partner_id' => $line->partner_id,
                    // Đảo ngược: Nợ cũ thành Có, Có cũ thành Nợ.
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => $line->description,
                ])->all(),
            ], $entry->company_id);

            $this->postEntry($reversal);

            $entry->update([
                'status' => JournalEntry::STATUS_REVERSED,
                'reversed_by_id' => $reversal->id,
            ]);

            return $reversal;
        });
    }

    /**
     * Tạo + ghi sổ ngay một bút toán từ module khác.
     * Tài khoản tham chiếu bằng MÃ (account_code), tự tìm/tạo kỳ kế toán
     * theo entry_date. Nếu cùng source đã có bút toán ghi sổ rồi thì
     * trả về id cũ (chống ghi trùng khi event bị xử lý 2 lần).
     *
     * @throws DomainException khi thiếu mã tài khoản, mất cân bằng, kỳ đã khóa.
     */
    public function post(array $data): int
    {
        $companyId = CurrentCompany::id();

        if (! $companyId) {
            throw new DomainException('Chưa xác định được công ty để ghi bút toán.');
        }

        // Chống ghi trùng cho các bút toán tự động (event chạy lại, retry queue).
        if (! empty($data['source_type']) && ! empty($data['source_id'])) {
            $existing = JournalEntry::query()
                ->where('company_id', $companyId)
                ->where('source_type', $data['source_type'])
                ->where('source_id', $data['source_id'])
                ->where('status', JournalEntry::STATUS_POSTED)
                ->first();

            if ($existing) {
                return $existing->id;
            }
        }

        return DB::transaction(function () use ($data, $companyId) {
            $entry = $this->create($data, $companyId);
            $this->postEntry($entry);

            return $entry->id;
        });
    }

    /** Tìm id tài khoản theo account_id hoặc mã tài khoản (account_code). */
    protected function resolveAccountId(array $line, int $companyId): int
    {
        if (! empty($line['account_id'])) {
            return (int) $line['account_id'];
        }

        $code = $line['account_code'] ?? null;

        if (! $code) {
            throw new DomainException('Dòng bút toán thiếu tài khoản (account_id hoặc account_code).');
        }

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if (! $account) {
            throw new DomainException("Không tìm thấy tài khoản có mã {$code}.");
        }

        return $account->id;
    }
}
