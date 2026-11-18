<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;

/** Bản ghi chấm công (nhập tay hoặc import từ máy chấm công). */
class AttendanceLog extends BaseModel
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LEAVE = 'leave';

    protected $fillable = [
        'company_id', 'employee_id', 'shift_id', 'work_date',
        'check_in', 'check_out', 'status',
    ];

    protected $casts = [
        'work_date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
