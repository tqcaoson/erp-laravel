<?php

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo/cập nhật khách hàng. */
class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Quyền đã chặn ở route bằng middleware permission.
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên khách hàng.',
            'email.email' => 'Email không đúng định dạng.',
            'credit_limit.min' => 'Hạn mức công nợ không được âm.',
        ];
    }
}
