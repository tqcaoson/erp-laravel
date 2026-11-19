<?php

namespace App\Modules\HR\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Hồ sơ nhân viên (danh mục nên dùng soft delete). */
class Employee extends BaseModel
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_RESIGNED = 'resigned';

    protected $fillable = [
        'company_id', 'code', 'full_name', 'email', 'phone',
        'department_id', 'position_id', 'user_id', 'hire_date',
        'termination_date', 'dependents_count',
        'status', 'base_salary',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'dependents_count' => 'integer',
        'base_salary' => 'decimal:2',
    ];

    /**
     * Gán mã nhân viên chính thức NV-0001… theo id rồi lưu lại.
     * Dùng sau khi tạo nhân viên (lúc tạo chưa có id nên chưa gán được mã).
     */
    public static function assignCode(Employee $employee): void
    {
        $employee->update(['code' => 'NV-'.str_pad($employee->id, 4, '0', STR_PAD_LEFT)]);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Số ngày phép còn lại trong năm cho trước (mặc định năm hiện tại).
     * Chưa có bản ghi số dư nghĩa là chưa dùng ngày nào → còn đủ 12 ngày
     * mặc định (khớp với LeaveService::balanceFor()).
     */
    public function remainingLeaveDays(?int $year = null): float
    {
        $balance = $this->leaveBalances()->where('year', $year ?? (int) now()->format('Y'))->first();

        return $balance
            ? (float) ($balance->total_days - $balance->used_days)
            : (float) LeaveBalance::DEFAULT_ANNUAL_DAYS;
    }
}
