<?php

namespace App\Core\Services;

use App\Core\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Sinh số chứng từ duy nhất theo công ty + loại chứng từ.
 *
 * Định dạng: {TIỀN TỐ}-{NĂM}-{SỐ THỨ TỰ 4 CHỮ SỐ}, ví dụ SO-2026-0001.
 * Dùng bảng document_sequences với SELECT ... FOR UPDATE để hai request
 * đồng thời không sinh trùng số.
 */
class DocumentNumberingService
{
    /** @var array<string, string> Tiền tố mặc định theo loại chứng từ. */
    public const PREFIXES = [
        'sales_order' => 'SO',
        'quotation' => 'QT',
        'delivery_order' => 'DO',
        'purchase_order' => 'PO',
        'purchase_request' => 'PR',
        'goods_receipt' => 'GR',
        'rfq' => 'RFQ',
        'journal_entry' => 'JE',
        'invoice' => 'INV',
        'payment' => 'PAY',
        'manufacturing_order' => 'MO',
        'work_order' => 'WO',
        'stock_count' => 'SC',
        'stock_move' => 'SM',
        'payroll_run' => 'PRN',
        'leave_request' => 'LV',
        'opportunity' => 'OPP',
    ];

    public function next(string $type, ?int $companyId = null): string
    {
        $companyId ??= CurrentCompany::id();
        $prefix = self::PREFIXES[$type] ?? strtoupper($type);
        $year = now()->format('Y');

        return DB::transaction(function () use ($type, $companyId, $prefix, $year) {
            $sequence = DocumentSequence::query()
                ->where('company_id', $companyId)
                ->where('document_type', $type)
                ->where('year', (int) $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'company_id' => $companyId,
                    'document_type' => $type,
                    'year' => (int) $year,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');

            return sprintf('%s-%s-%04d', $prefix, $year, $sequence->last_number);
        });
    }
}
