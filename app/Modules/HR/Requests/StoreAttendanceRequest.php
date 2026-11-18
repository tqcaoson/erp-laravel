<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate nhập chấm công tay. */
class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('hr.attendance.manage');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'work_date' => ['required', 'date'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'status' => ['required', 'in:present,late,absent,leave'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_out.after' => 'Giờ ra phải sau giờ vào.',
            'work_date.required' => 'Ngày làm việc là bắt buộc.',
        ];
    }
}
