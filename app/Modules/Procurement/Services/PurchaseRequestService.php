<?php

namespace App\Modules\Procurement\Services;

use App\Core\Contracts\PurchaseRequestCreatorInterface;
use App\Core\Models\ApprovalLog;
use App\Core\Services\CurrentCompany;
use App\Core\Services\DocumentNumberingService;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\PurchaseRequestLine;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Yêu cầu mua hàng (PR) + luồng duyệt theo ngưỡng giá trị.
 * Ngưỡng: tổng trên 50.000.000 ₫ thì chuyển pending_approval (cần duyệt),
 * ngược lại duyệt tự động (approved) khi trình duyệt.
 */
class PurchaseRequestService implements PurchaseRequestCreatorInterface
{
    /** Ngưỡng cần duyệt (50 triệu đồng). */
    public const APPROVAL_THRESHOLD = 50000000;

    public function __construct(
        protected DocumentNumberingService $numbering,
    ) {}

    /**
     * Tạo PR gợi ý (từ tồn dưới mức tối thiểu hoặc SO thiếu hàng).
     * Luôn tạo ở trạng thái draft để người phụ trách xem xét trước khi trình duyệt.
     *
     * @param  array<int, array{product_id: int, qty: float, warehouse_id?: int|null, note?: string|null}> $lines
     */
    public function createSuggestion(int $companyId, array $lines, string $reason): int
    {
        CurrentCompany::setId($companyId);

        return DB::transaction(function () use ($companyId, $lines, $reason) {
            $request = PurchaseRequest::create([
                'company_id' => $companyId,
                'code' => $this->numbering->next('purchase_request', $companyId),
                'requested_by' => auth()->id(),
                'status' => 'draft',
                'total_amount' => 0,
                'note' => $reason,
            ]);

            $total = 0;
            foreach ($lines as $line) {
                $product = Product::query()->findOrFail($line['product_id']);
                // Đơn giá ước tính: giá vốn bình quân hiện tại của sản phẩm.
                $unitPrice = (float) $product->average_cost;

                PurchaseRequestLine::create([
                    'company_id' => $companyId,
                    'purchase_request_id' => $request->id,
                    'product_id' => $line['product_id'],
                    'qty' => (float) $line['qty'],
                    'unit_price' => $unitPrice,
                    'warehouse_id' => $line['warehouse_id'] ?? null,
                    'note' => $line['note'] ?? null,
                ]);

                $total += (float) $line['qty'] * $unitPrice;
            }

            $request->update(['total_amount' => round($total, 2)]);

            return $request->id;
        });
    }

    /**
     * Tạo PR từ form người dùng, rồi trình duyệt theo ngưỡng ngay.
     *
     * @param  array{requested_by?: int|null, note?: string|null, lines: array<int, array{product_id: int, qty: float, unit_price?: float|null, warehouse_id?: int|null, note?: string|null}>} $data
     */
    public function create(array $data, int $companyId): PurchaseRequest
    {
        $request = DB::transaction(function () use ($data, $companyId) {
            $request = PurchaseRequest::create([
                'company_id' => $companyId,
                'code' => $this->numbering->next('purchase_request', $companyId),
                'requested_by' => $data['requested_by'] ?? auth()->id(),
                'status' => 'draft',
                'total_amount' => 0,
                'note' => $data['note'] ?? null,
            ]);

            $total = 0;
            foreach ($data['lines'] as $line) {
                $unitPrice = (float) ($line['unit_price'] ?? 0);

                PurchaseRequestLine::create([
                    'company_id' => $companyId,
                    'purchase_request_id' => $request->id,
                    'product_id' => $line['product_id'],
                    'qty' => (float) $line['qty'],
                    'unit_price' => $unitPrice,
                    'warehouse_id' => $line['warehouse_id'] ?? null,
                    'note' => $line['note'] ?? null,
                ]);

                $total += (float) $line['qty'] * $unitPrice;
            }

            $request->update(['total_amount' => round($total, 2)]);

            return $request;
        });

        return $this->submit($request->id);
    }

    /**
     * Trình duyệt theo ngưỡng: tổng > 50 triệu → pending_approval,
     * ngược lại approved luôn (duyệt tự động).
     */
    public function submit(int $requestId): PurchaseRequest
    {
        $request = PurchaseRequest::query()->findOrFail($requestId);

        if ($request->status !== 'draft') {
            throw new DomainException('Chỉ yêu cầu mua ở trạng thái nháp mới được trình duyệt.');
        }

        $request->update([
            'status' => (float) $request->total_amount > self::APPROVAL_THRESHOLD
                ? 'pending_approval'
                : 'approved',
        ]);

        return $request;
    }

    /** Duyệt PR (ghi ApprovalLog). Chấp nhận từ pending_approval hoặc draft. */
    public function approve(int $requestId, ?string $note = null): PurchaseRequest
    {
        $request = PurchaseRequest::query()->findOrFail($requestId);

        if (! in_array($request->status, ['draft', 'pending_approval'], true)) {
            throw new DomainException('Chỉ yêu cầu mua nháp hoặc chờ duyệt mới được duyệt.');
        }

        $this->writeApprovalLog($request, 'approved', $note);
        $request->update(['status' => 'approved']);

        return $request;
    }

    /** Từ chối PR (ghi ApprovalLog). */
    public function reject(int $requestId, ?string $note = null): PurchaseRequest
    {
        $request = PurchaseRequest::query()->findOrFail($requestId);

        if (! in_array($request->status, ['draft', 'pending_approval'], true)) {
            throw new DomainException('Chỉ yêu cầu mua nháp hoặc chờ duyệt mới được từ chối.');
        }

        $this->writeApprovalLog($request, 'rejected', $note);
        $request->update(['status' => 'rejected']);

        return $request;
    }

    /** Hủy PR (chỉ khi chưa chuyển thành đơn mua). */
    public function cancel(int $requestId): PurchaseRequest
    {
        $request = PurchaseRequest::query()->findOrFail($requestId);

        if (! in_array($request->status, ['draft', 'pending_approval', 'approved'], true)) {
            throw new DomainException('Yêu cầu mua ở trạng thái này không thể hủy.');
        }

        $request->update(['status' => 'cancelled']);

        return $request;
    }

    /** Đánh dấu PR đã chuyển thành đơn mua hàng. */
    public function markConverted(int $requestId): void
    {
        PurchaseRequest::query()
            ->where('id', $requestId)
            ->whereIn('status', ['approved', 'pending_approval', 'draft'])
            ->update(['status' => 'converted']);
    }

    protected function writeApprovalLog(PurchaseRequest $request, string $decision, ?string $note): void
    {
        if (! auth()->check()) {
            throw new DomainException('Cần đăng nhập để duyệt yêu cầu mua hàng.');
        }

        ApprovalLog::create([
            'company_id' => $request->company_id,
            'approval_rule_id' => null,
            'approvable_type' => PurchaseRequest::class,
            'approvable_id' => $request->id,
            'approver_id' => auth()->id(),
            'level' => 1,
            'decision' => $decision,
            'note' => $note,
            'ip_address' => request()->ip(),
        ]);
    }
}
