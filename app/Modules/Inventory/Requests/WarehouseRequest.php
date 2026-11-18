<?php

namespace App\Modules\Inventory\Requests;

use App\Core\Services\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate tạo/cập nhật kho hàng. */
class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseId = $this->route('warehouse');

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('warehouses', 'code')->where('company_id', CurrentCompany::id())->ignore($warehouseId)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã kho không được để trống.',
            'code.unique' => 'Mã kho đã tồn tại trong công ty.',
            'name.required' => 'Tên kho không được để trống.',
        ];
    }
}
