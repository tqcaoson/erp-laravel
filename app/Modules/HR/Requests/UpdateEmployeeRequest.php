<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate cập nhật nhân viên (giống tạo mới nhưng không bắt buộc mọi trường). */
class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('hr.employee.manage');
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['sometimes', 'required', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,on_leave,resigned'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
