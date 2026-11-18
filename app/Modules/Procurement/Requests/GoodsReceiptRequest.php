<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo phiếu nhận hàng. */
class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'receipt_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.purchase_order_line_id' => ['nullable', 'exists:purchase_order_lines,id'],
            'lines.*.lot_id' => ['nullable', 'exists:lots,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Nhà cung cấp không được để trống.',
            'warehouse_id.required' => 'Kho nhận hàng không được để trống.',
            'receipt_date.required' => 'Ngày nhận hàng không được để trống.',
            'lines.required' => 'Phiếu nhận cần ít nhất một dòng sản phẩm.',
        ];
    }
}
