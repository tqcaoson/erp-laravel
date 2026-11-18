<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo/cập nhật định mức BOM (kèm các dòng nguyên liệu). */
class StoreBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:40'],
            'product_id' => ['required', 'exists:products,id'],
            'version' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.component_product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_id' => ['required', 'exists:units,id'],
            'lines.*.scrap_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Vui lòng chọn thành phẩm.',
            'lines.required' => 'BOM phải có ít nhất 1 dòng nguyên liệu.',
            'lines.*.component_product_id.required' => 'Vui lòng chọn nguyên liệu cho từng dòng.',
            'lines.*.unit_id.required' => 'Vui lòng chọn đơn vị tính cho từng dòng.',
        ];
    }
}
