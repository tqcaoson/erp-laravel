<?php

namespace App\Modules\Procurement\Requests;

use App\Core\Services\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate tạo/cập nhật nhà cung cấp. */
class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier');

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('suppliers', 'code')->where('company_id', CurrentCompany::id())->ignore($supplierId)],
            'name' => ['required', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã nhà cung cấp không được để trống.',
            'code.unique' => 'Mã nhà cung cấp đã tồn tại trong công ty.',
            'name.required' => 'Tên nhà cung cấp không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
        ];
    }
}
