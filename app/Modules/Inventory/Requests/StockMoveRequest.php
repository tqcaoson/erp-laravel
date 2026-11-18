<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate phiếu nhập/xuất/chuyển kho. */
class StockMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:receive,issue,transfer'],
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required_if:type,transfer', 'nullable', 'exists:warehouses,id', 'different:warehouse_id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'to_location_id' => ['nullable', 'exists:locations,id'],
            'lot_id' => ['nullable', 'exists:lots,id'],
            'qty' => ['required', 'numeric', 'min:0.0001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Loại phiếu không được để trống.',
            'product_id.required' => 'Sản phẩm không được để trống.',
            'warehouse_id.required' => 'Kho không được để trống.',
            'to_warehouse_id.required_if' => 'Chuyển kho cần chọn kho đích.',
            'to_warehouse_id.different' => 'Kho nguồn và kho đích phải khác nhau.',
            'qty.required' => 'Số lượng không được để trống.',
            'qty.min' => 'Số lượng phải lớn hơn 0.',
        ];
    }
}
