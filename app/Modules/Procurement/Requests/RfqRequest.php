<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo/cập nhật yêu cầu báo giá (RFQ). */
class RfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'deadline' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.quoted_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Nhà cung cấp không được để trống.',
            'lines.required' => 'Báo giá cần ít nhất một dòng sản phẩm.',
        ];
    }
}
