<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo yêu cầu mua hàng. */
class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'lines.*.note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Yêu cầu mua cần ít nhất một dòng sản phẩm.',
            'lines.*.product_id.required' => 'Sản phẩm không được để trống.',
            'lines.*.qty.required' => 'Số lượng không được để trống.',
            'lines.*.qty.min' => 'Số lượng phải lớn hơn 0.',
        ];
    }
}
