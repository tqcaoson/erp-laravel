<?php

namespace App\Modules\Sales\Services;

use App\Core\Services\DocumentNumberingService;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\SalesOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Nghiệp vụ báo giá: tạo báo giá, chuyển báo giá đã chấp nhận thành đơn bán. */
class QuotationService
{
    public function __construct(
        protected DocumentNumberingService $numbering,
        protected SalesOrderService $salesOrders,
    ) {}

    /** Tạo báo giá ở trạng thái nháp kèm các dòng, tự tính tiền. */
    public function create(array $data): Quotation
    {
        $totals = DocumentTotals::compute($data['lines'] ?? [], (float) ($data['discount_amount'] ?? 0));

        return DB::transaction(function () use ($data, $totals) {
            $quotation = Quotation::create([
                'code' => $this->numbering->next('quotation'),
                'customer_id' => $data['customer_id'],
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'status' => 'draft',
                'valid_until' => $data['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => (float) ($data['discount_amount'] ?? 0),
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'note' => $data['note'] ?? null,
            ]);

            foreach ($totals['lines'] as $line) {
                $quotation->lines()->create([
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount_rate' => $line['discount_rate'],
                    'tax_rate' => $line['tax_rate'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $quotation->fresh(['lines']);
        });
    }

    /**
     * Sửa báo giá — CHỈ khi còn ở trạng thái nháp. Báo giá đã gửi trở đi
     * (khách đã thấy nội dung) tuyệt đối không được sửa.
     *
     * Đồng bộ dòng theo id: dòng có id thì cập nhật, dòng không id thì thêm
     * mới, dòng cũ vắng mặt trong request thì xóa. Tổng tiền tính lại qua
     * DocumentTotals. Toàn bộ chạy trong 1 transaction.
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        if ($quotation->status !== 'draft') {
            throw new DomainException('Chỉ sửa được báo giá ở trạng thái nháp.');
        }

        $totals = DocumentTotals::compute($data['lines'] ?? [], (float) ($data['discount_amount'] ?? 0));

        return DB::transaction(function () use ($quotation, $data, $totals) {
            $quotation->update([
                'customer_id' => $data['customer_id'],
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => (float) ($data['discount_amount'] ?? 0),
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'note' => $data['note'] ?? null,
            ]);

            $keptIds = [];

            foreach ($totals['lines'] as $line) {
                $attributes = [
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount_rate' => $line['discount_rate'],
                    'tax_rate' => $line['tax_rate'],
                    'line_total' => $line['line_total'],
                ];

                if (! empty($line['id'])) {
                    $existing = $quotation->lines()->whereKey((int) $line['id'])->first();

                    if (! $existing) {
                        throw new DomainException("Dòng #{$line['id']} không thuộc báo giá này.");
                    }

                    $existing->update($attributes);
                    $keptIds[] = $existing->id;
                } else {
                    $keptIds[] = $quotation->lines()->create($attributes)->id;
                }
            }

            // Dòng cũ vắng mặt trong request thì xóa.
            $quotation->lines()->whereNotIn('id', $keptIds)->delete();

            return $quotation->fresh(['lines']);
        });
    }

    /** Đổi trạng thái báo giá (gửi / chấp nhận / từ chối / hết hạn). */
    public function setStatus(Quotation $quotation, string $status): Quotation
    {
        if (! in_array($status, Quotation::STATUSES, true)) {
            throw new DomainException('Trạng thái báo giá không hợp lệ.');
        }

        if (in_array($quotation->status, ['accepted', 'rejected', 'expired'], true) && $quotation->status !== $status) {
            throw new DomainException('Báo giá đã chốt trạng thái, không đổi được nữa.');
        }

        $quotation->update(['status' => $status]);

        return $quotation->fresh();
    }

    /**
     * Chuyển báo giá ĐÃ ĐƯỢC CHẤP NHẬN thành đơn bán: copy toàn bộ dòng,
     * sinh mã đơn qua DocumentNumberingService, đơn mới ở trạng thái nháp.
     */
    public function convertToSalesOrder(Quotation $quotation, int $warehouseId): SalesOrder
    {
        if ($quotation->status !== 'accepted') {
            throw new DomainException('Chỉ chuyển được báo giá đã được khách hàng chấp nhận thành đơn bán.');
        }

        $quotation->loadMissing('lines');

        return $this->salesOrders->create([
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'warehouse_id' => $warehouseId,
            'discount_amount' => (float) $quotation->discount_amount,
            'note' => "Tạo từ báo giá {$quotation->code}.",
            'lines' => $quotation->lines->map(fn ($line) => [
                'product_id' => (int) $line->product_id,
                'qty' => (float) $line->qty,
                'unit_price' => (float) $line->unit_price,
                'discount_rate' => (float) $line->discount_rate,
                'tax_rate' => (float) $line->tax_rate,
            ])->all(),
        ]);
    }
}
