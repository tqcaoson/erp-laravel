<?php

namespace App\Modules\HR\Services;

use App\Core\Services\DocumentNumberingService;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveBalance;
use App\Modules\HR\Models\LeaveRequest;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ nghỉ phép: đăng ký, duyệt, từ chối.
 *
 * Chỉ nghỉ phép năm (annual) mới trừ vào số dư phép. Nghỉ ốm (sick) và
 * nghỉ không lương (unpaid) không trừ phép năm.
 */
class LeaveService
{
    public function __construct(
        protected DocumentNumberingService $numbering,
    ) {}

    /**
     * Lấy (hoặc tự tạo) số dư phép của nhân viên trong 1 năm.
     * Mặc định 12 ngày/năm nếu chưa có bản ghi.
     */
    public function balanceFor(Employee $employee, ?int $year = null): LeaveBalance
    {
        $year ??= (int) now()->format('Y');

        return LeaveBalance::firstOrCreate(
            [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'year' => $year,
            ],
            ['total_days' => LeaveBalance::DEFAULT_ANNUAL_DAYS, 'used_days' => 0],
        );
    }

    /**
     * Số ngày nghỉ làm việc (chỉ đếm T2–T6, bỏ T7/CN), tính cả ngày
     * bắt đầu và ngày kết thúc. Đơn không có ngày làm việc nào
     * (ví dụ chỉ nghỉ T7/CN) sẽ bị từ chối bằng DomainException.
     */
    public function countDays(string $startDate, string $endDate): float
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        $days = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if (! $day->isWeekend()) {
                $days++;
            }
        }

        if ($days === 0) {
            throw new DomainException('Đơn nghỉ phải có ít nhất 1 ngày làm việc (thứ 2–thứ 6).');
        }

        return (float) $days;
    }

    /**
     * Đăng ký nghỉ. Nghỉ phép năm vượt số ngày còn lại sẽ bị từ chối
     * bằng DomainException (controller/API chuyển thành lỗi 409).
     */
    public function request(Employee $employee, array $data): LeaveRequest
    {
        $days = $this->countDays($data['start_date'], $data['end_date']);

        if (($data['leave_type'] ?? LeaveRequest::TYPE_ANNUAL) === LeaveRequest::TYPE_ANNUAL) {
            $year = (int) Carbon::parse($data['start_date'])->format('Y');
            $balance = $this->balanceFor($employee, $year);

            if ($days > $balance->remainingDays()) {
                throw new DomainException(
                    "Không đủ ngày phép năm. Còn lại {$balance->remainingDays()} ngày, bạn đăng ký {$days} ngày."
                );
            }
        }

        return LeaveRequest::create([
            'company_id' => $employee->company_id,
            'code' => $this->numbering->next('leave_request', $employee->company_id),
            'employee_id' => $employee->id,
            'leave_type' => $data['leave_type'] ?? LeaveRequest::TYPE_ANNUAL,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Duyệt đơn nghỉ: trừ phép năm trong leave_balances (nếu là phép năm)
     * rồi chuyển trạng thái approved. Chạy trong transaction để số dư
     * và trạng thái luôn nhất quán.
     */
    public function approve(LeaveRequest $leaveRequest, int $approverId): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            throw new DomainException('Chỉ duyệt được đơn nghỉ đang chờ xử lý.');
        }

        return DB::transaction(function () use ($leaveRequest, $approverId) {
            if ($leaveRequest->leave_type === LeaveRequest::TYPE_ANNUAL) {
                $year = (int) $leaveRequest->start_date->format('Y');
                $balance = $this->balanceFor($leaveRequest->employee, $year);

                if ((float) $leaveRequest->days > $balance->remainingDays()) {
                    throw new DomainException(
                        "Không đủ ngày phép năm để duyệt. Còn lại {$balance->remainingDays()} ngày, đơn xin {$leaveRequest->days} ngày."
                    );
                }

                $balance->increment('used_days', (float) $leaveRequest->days);
            }

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'approver_id' => $approverId,
            ]);

            return $leaveRequest->fresh();
        });
    }

    /** Từ chối đơn nghỉ (không trừ phép). */
    public function reject(LeaveRequest $leaveRequest, int $approverId): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            throw new DomainException('Chỉ từ chối được đơn nghỉ đang chờ xử lý.');
        }

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'approver_id' => $approverId,
        ]);

        return $leaveRequest->fresh();
    }

    /** Hủy đơn nghỉ (chỉ khi còn chờ xử lý). */
    public function cancel(LeaveRequest $leaveRequest): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            throw new DomainException('Chỉ hủy được đơn nghỉ đang chờ xử lý.');
        }

        $leaveRequest->update(['status' => LeaveRequest::STATUS_CANCELLED]);

        return $leaveRequest->fresh();
    }
}
