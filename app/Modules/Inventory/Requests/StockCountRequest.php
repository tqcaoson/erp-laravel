<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo phiếu kiểm kê và nhập số thực tế. */
class StockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->routeIs('inventory.stock-counts.update-counted') || $this->isMethod('put')) {
            return [
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.line_id' => ['required', 'exists:stock_count_lines,id'],
                'lines.*.counted_qty' => ['required', 'numeric', 'min:0'],
            ];
        }

        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['exists:products,id'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Kho kiểm kê không được để trống.',
            'lines.required' => 'Cần nhập số thực tế cho ít nhất một dòng.',
            'lines.*.counted_qty.required' => 'Số thực tế không được để trống.',
            'lines.*.counted_qty.min' => 'Số thực tế không được âm.',
        ];
    }
}
