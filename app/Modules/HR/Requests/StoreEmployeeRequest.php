<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo nhân viên mới. */
class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('hr.employee.manage');
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,on_leave,resigned'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên nhân viên là bắt buộc.',
            'department_id.required' => 'Phòng ban là bắt buộc.',
            'department_id.exists' => 'Phòng ban không tồn tại.',
            'position_id.exists' => 'Chức danh không tồn tại.',
            'base_salary.min' => 'Lương cơ bản không được âm.',
        ];
    }
}
