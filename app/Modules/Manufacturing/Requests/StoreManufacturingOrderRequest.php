<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo lệnh sản xuất. */
class StoreManufacturingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'bom_id' => ['nullable', 'exists:boms,id'],
            'routing_id' => ['nullable', 'exists:routings,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Vui lòng chọn thành phẩm cần sản xuất.',
            'warehouse_id.required' => 'Vui lòng chọn kho xuất nguyên liệu / nhập thành phẩm.',
            'qty.gt' => 'Số lượng sản xuất phải lớn hơn 0.',
        ];
    }
}
