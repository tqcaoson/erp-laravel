<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate ghi nhận tiến độ từng phần (sản lượng + phế phẩm của đợt này). */
class LogManufacturingProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty_produced' => ['required', 'numeric', 'gt:0'],
            'qty_scrap' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return ['qty_produced.gt' => 'Số lượng thành phẩm phải lớn hơn 0.'];
    }
}
