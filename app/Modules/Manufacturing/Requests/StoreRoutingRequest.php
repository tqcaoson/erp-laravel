<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo quy trình sản xuất (kèm các công đoạn theo thứ tự). */
class StoreRoutingRequest extends FormRequest
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
            'product_id' => ['nullable', 'exists:products,id'],
            'operations' => ['required', 'array', 'min:1'],
            'operations.*.work_center_id' => ['required', 'exists:work_centers,id'],
            'operations.*.operation_name' => ['required', 'string', 'max:255'],
            'operations.*.standard_minutes' => ['nullable', 'numeric', 'min:0'],
            'operations.*.sequence' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên quy trình.',
            'operations.required' => 'Quy trình phải có ít nhất 1 công đoạn.',
            'operations.*.work_center_id.required' => 'Vui lòng chọn trung tâm sản xuất cho từng công đoạn.',
            'operations.*.operation_name.required' => 'Vui lòng nhập tên công đoạn.',
        ];
    }
}
