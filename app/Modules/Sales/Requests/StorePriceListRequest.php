<?php

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo/cập nhật bảng giá. */
class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.min_qty' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Vui lòng nhập tên bảng giá.'];
    }
}
