<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.payment.create');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:receipt,payment'],
            'partner_type' => ['nullable', 'in:customer,supplier,employee'],
            'partner_id' => ['nullable', 'integer', 'min:1'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Loại phiếu phải là thu hoặc chi.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'payment_date.required' => 'Ngày thanh toán là bắt buộc.',
            'invoice_id.exists' => 'Hóa đơn không tồn tại.',
            'bank_account_id.exists' => 'Tài khoản ngân hàng không tồn tại.',
        ];
    }
}
