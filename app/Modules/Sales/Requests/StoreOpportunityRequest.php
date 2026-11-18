<?php

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate tạo/cập nhật cơ hội bán hàng. */
class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'name' => ['required', 'string', 'max:255'],
            'stage' => ['nullable', 'in:lead,qualified,proposal,negotiation,won,lost'],
            'expected_amount' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Vui lòng nhập tên cơ hội.'];
    }
}
