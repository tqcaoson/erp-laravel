<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo đơn mua hàng. */
class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Nhà cung cấp không được để trống.',
            'order_date.required' => 'Ngày đặt hàng không được để trống.',
            'lines.required' => 'Đơn mua cần ít nhất một dòng sản phẩm.',
            'lines.*.unit_price.required' => 'Đơn giá không được để trống.',
        ];
    }
}
