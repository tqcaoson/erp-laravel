<?php

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo/cập nhật đầu mối. */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:new,contacted,qualified,lost'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Vui lòng nhập tên đầu mối.'];
    }
}
