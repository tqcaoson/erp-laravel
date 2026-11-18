<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate tạo/cập nhật sản phẩm. Quyền đã kiểm tra ở middleware route. */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->where('company_id', \App\Core\Services\CurrentCompany::id())->ignore($productId)],
            'name' => ['required', 'string', 'max:255'],
            'uom_id' => ['required', 'exists:units,id'],
            'cost_method' => ['nullable', 'in:avg,fifo'],
            'track_lot' => ['nullable', 'boolean'],
            'track_serial' => ['nullable', 'boolean'],
            'reorder_min' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'Mã sản phẩm (SKU) không được để trống.',
            'sku.unique' => 'Mã sản phẩm (SKU) đã tồn tại trong công ty.',
            'name.required' => 'Tên sản phẩm không được để trống.',
            'uom_id.required' => 'Đơn vị tính không được để trống.',
            'uom_id.exists' => 'Đơn vị tính không tồn tại.',
        ];
    }
}
