<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate đăng ký nghỉ phép. */
class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('hr.leave.request');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type' => ['required', 'in:annual,sick,unpaid'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Nhân viên là bắt buộc.',
            'leave_type.in' => 'Loại nghỉ không hợp lệ (phép năm, ốm, không lương).',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
