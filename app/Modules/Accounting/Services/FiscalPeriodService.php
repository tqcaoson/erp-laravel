<?php

namespace App\Modules\Accounting\Services;

use App\Core\Services\CurrentCompany;
use App\Modules\Accounting\Models\FiscalPeriod;
use Carbon\Carbon;
use DomainException;

/** Quản lý kỳ kế toán: tìm/tạo kỳ theo ngày, khóa/mở kỳ. */
class FiscalPeriodService
{
    /**
     * Tìm kỳ đang mở chứa ngày cho trước; nếu chưa có kỳ nào của tháng đó
     * thì tự tạo kỳ tháng đó ở trạng thái mở.
     *
     * @throws DomainException khi kỳ của tháng đó đã bị khóa.
     */
    public function findOrCreateOpenPeriod(string $date, ?int $companyId = null): FiscalPeriod
    {
        $companyId ??= CurrentCompany::id();
        $day = Carbon::parse($date);
        $code = $day->format('Y-m');

        if (! $companyId) {
            throw new DomainException('Chưa xác định được công ty để tạo kỳ kế toán.');
        }

        $period = FiscalPeriod::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if ($period) {
            if ($period->isClosed()) {
                throw new DomainException("Kỳ kế toán {$code} đã bị khóa, không được ghi sổ chứng từ.");
            }

            return $period;
        }

        return FiscalPeriod::create([
            'company_id' => $companyId,
            'code' => $code,
            'start_date' => $day->copy()->startOfMonth()->toDateString(),
            'end_date' => $day->copy()->endOfMonth()->toDateString(),
            'status' => FiscalPeriod::STATUS_OPEN,
        ]);
    }

    /** Khóa kỳ — sau đó không ghi sổ được chứng từ thuộc kỳ này nữa. */
    public function lock(FiscalPeriod $period): FiscalPeriod
    {
        if ($period->isClosed()) {
            throw new DomainException("Kỳ kế toán {$period->code} đã khóa rồi.");
        }

        $period->update(['status' => FiscalPeriod::STATUS_CLOSED]);

        return $period;
    }

    /** Mở lại kỳ đã khóa (chỉ dùng khi cần điều chỉnh, có ghi audit log). */
    public function unlock(FiscalPeriod $period): FiscalPeriod
    {
        if ($period->isOpen()) {
            throw new DomainException("Kỳ kế toán {$period->code} đang mở rồi.");
        }

        $period->update(['status' => FiscalPeriod::STATUS_OPEN]);

        return $period;
    }
}
