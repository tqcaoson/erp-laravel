<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.invoice.create');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:sale,purchase'],
            'customer_id' => ['nullable', 'integer', 'min:1', 'required_if:type,sale'],
            'supplier_id' => ['nullable', 'integer', 'min:1', 'required_if:type,purchase'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Loại hóa đơn phải là bán hàng hoặc mua hàng.',
            'customer_id.required_if' => 'Hóa đơn bán hàng phải chọn khách hàng.',
            'supplier_id.required_if' => 'Hóa đơn mua hàng phải chọn nhà cung cấp.',
            'invoice_date.required' => 'Ngày hóa đơn là bắt buộc.',
            'lines.required' => 'Hóa đơn phải có ít nhất 1 dòng.',
            'lines.*.description.required' => 'Mỗi dòng phải có mô tả.',
            'lines.*.qty.min' => 'Số lượng phải lớn hơn 0.',
        ];
    }
}
