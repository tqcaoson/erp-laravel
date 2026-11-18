<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Đơn xin nghỉ phép. */
class LeaveRequest extends BaseModel
{
    public const TYPE_ANNUAL = 'annual';
    public const TYPE_SICK = 'sick';
    public const TYPE_UNPAID = 'unpaid';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id', 'code', 'employee_id', 'leave_type',
        'start_date', 'end_date', 'days', 'reason', 'status', 'approver_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approver_id');
    }
}
