<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo/cập nhật phòng ban. */
class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('hr.employee.manage');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
