<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo/cập nhật trung tâm sản xuất. */
class StoreWorkCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'cost_per_hour' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Vui lòng nhập tên trung tâm sản xuất.'];
    }
}
