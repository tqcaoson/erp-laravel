<?php

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo đơn bán hàng (kèm các dòng sản phẩm). */
class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'order_date' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Vui lòng chọn khách hàng.',
            'warehouse_id.required' => 'Vui lòng chọn kho giao hàng.',
            'lines.required' => 'Đơn bán phải có ít nhất 1 dòng sản phẩm.',
            'lines.*.product_id.required' => 'Vui lòng chọn sản phẩm cho từng dòng.',
            'lines.*.qty.gt' => 'Số lượng phải lớn hơn 0.',
        ];
    }
}
