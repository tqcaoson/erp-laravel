<?php

namespace App\Modules\Accounting\Services;

use App\Core\Contracts\JournalPostingInterface;
use App\Core\Services\CurrentCompany;
use App\Core\Services\DocumentNumberingService;
use App\Modules\Accounting\Events\InvoicePosted;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Nghiệp vụ hóa đơn: tạo, ghi sổ, ghi nhận thanh toán. */
class InvoiceService
{
    public function __construct(
        protected DocumentNumberingService $numbering,
        protected JournalPostingInterface $journal,
    ) {}

    /**
     * Tạo hóa đơn nháp từ dữ liệu nhập tay hoặc từ chứng từ nguồn
     * (vd đơn bán hàng qua source_type/source_id). Tổng tiền tính lại
     * từ các dòng: line_total = qty × unit_price, thuế theo tax_rate.
     *
     * @param  array{type: string, customer_id?: int|null, supplier_id?: int|null, source_type?: string|null, source_id?: int|null, invoice_date: string, due_date?: string|null, lines: array<int, array{product_id?: int|null, description: string, qty: float, unit_price: float, tax_rate?: float}>} $data
     */
    public function create(array $data, ?int $companyId = null): Invoice
    {
        $companyId ??= CurrentCompany::id();

        if (! $companyId) {
            throw new DomainException('Chưa xác định được công ty để lập hóa đơn.');
        }

        return DB::transaction(function () use ($data, $companyId) {
            $subtotal = 0;
            $taxAmount = 0;
            $lines = [];

            foreach ($data['lines'] as $line) {
                $lineTotal = round((float) $line['qty'] * (float) $line['unit_price'], 2);
                $lineTax = round($lineTotal * ((float) ($line['tax_rate'] ?? 0) / 100), 2);
                $subtotal += $lineTotal;
                $taxAmount += $lineTax;

                $lines[] = [
                    'product_id' => $line['product_id'] ?? null,
                    'description' => $line['description'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'] ?? 0,
                    'line_total' => $lineTotal,
                ];
            }

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'code' => $this->numbering->next('invoice', $companyId),
                'type' => $data['type'],
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount,
                'status' => Invoice::STATUS_DRAFT,
            ]);

            $invoice->lines()->createMany($lines);

            return $invoice->load('lines');
        });
    }

    /**
     * Ghi sổ hóa đơn nháp: sinh bút toán rồi dispatch event InvoicePosted
     * (sau khi commit, để Sales/Reporting nhận được dữ liệu đã lưu chắc chắn).
     *
     * - Hóa đơn bán: Nợ 131 (phải thu KH) / Có 511 (doanh thu) + Có 3331 (thuế GTGT).
     * - Hóa đơn mua: Nợ 156 (hàng hóa) + Nợ 133 (thuế GTGT được khấu trừ) / Có 331 (phải trả NCC).
     *   (Đơn giản hóa: hóa đơn mua từ phiếu nhập đã ghi sổ sẽ ghi trùng phần hàng hóa
     *   nếu lập thêm — thực tế cần đối chiếu 3 chiều PO–GR–hóa đơn trước khi ghi sổ.)
     */
    public function post(Invoice $invoice): Invoice
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new DomainException('Chỉ ghi sổ được hóa đơn đang ở trạng thái nháp.');
        }

        DB::transaction(function () use ($invoice) {
            if ($invoice->type === Invoice::TYPE_SALE) {
                $this->journal->post([
                    'entry_date' => $invoice->invoice_date->toDateString(),
                    'description' => "Ghi sổ hóa đơn bán hàng {$invoice->code}",
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'lines' => array_values(array_filter([
                        ['account_code' => '131', 'debit' => (float) $invoice->total_amount, 'credit' => 0, 'partner_type' => 'customer', 'partner_id' => $invoice->customer_id],
                        ['account_code' => '511', 'debit' => 0, 'credit' => (float) $invoice->subtotal],
                        (float) $invoice->tax_amount > 0
                            ? ['account_code' => '3331', 'debit' => 0, 'credit' => (float) $invoice->tax_amount]
                            : null,
                    ])),
                ]);
            } else {
                $this->journal->post([
                    'entry_date' => $invoice->invoice_date->toDateString(),
                    'description' => "Ghi sổ hóa đơn mua hàng {$invoice->code}",
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'lines' => array_values(array_filter([
                        ['account_code' => '156', 'debit' => (float) $invoice->subtotal, 'credit' => 0, 'partner_type' => 'supplier', 'partner_id' => $invoice->supplier_id],
                        (float) $invoice->tax_amount > 0
                            ? ['account_code' => '133', 'debit' => (float) $invoice->tax_amount, 'credit' => 0]
                            : null,
                        ['account_code' => '331', 'debit' => 0, 'credit' => (float) $invoice->total_amount, 'partner_type' => 'supplier', 'partner_id' => $invoice->supplier_id],
                    ])),
                ]);
            }

            $invoice->update([
                'status' => Invoice::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            DB::afterCommit(function () use ($invoice) {
                InvoicePosted::dispatch(
                    $invoice->id,
                    $invoice->company_id,
                    $invoice->type,
                    $invoice->source_type,
                    $invoice->source_id,
                    $invoice->customer_id,
                    $invoice->supplier_id,
                    (float) $invoice->total_amount,
                );
            });
        });

        return $invoice;
    }

    /** Hủy hóa đơn nháp (hóa đơn đã ghi sổ thì đảo bút toán, không xóa). */
    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new DomainException('Chỉ hủy được hóa đơn đang ở trạng thái nháp.');
        }

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        return $invoice;
    }

    /**
     * Ghi nhận thanh toán cho hóa đơn: tạo phiếu thu/chi đã ghi sổ + bút toán
     * tiền, cập nhật trạng thái hóa đơn thành paid khi đủ tiền.
     *
     * - Thu tiền bán hàng: Nợ 112 (hoặc 111 nếu thu tiền mặt) / Có 131.
     * - Trả tiền mua hàng: Nợ 331 / Có 112 (hoặc 111 nếu trả tiền mặt).
     *
     * @param  array{amount: float, payment_date: string, bank_account_id?: int|null, description?: string|null} $data
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        if (! in_array($invoice->status, [Invoice::STATUS_POSTED, Invoice::STATUS_PAID], true)) {
            throw new DomainException('Chỉ thanh toán được hóa đơn đã ghi sổ.');
        }

        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw new DomainException('Số tiền thanh toán phải lớn hơn 0.');
        }

        if ($amount > $invoice->remaining_amount + 0.01) {
            throw new DomainException('Số tiền thanh toán vượt quá số tiền còn phải thu/trả của hóa đơn.');
        }

        return DB::transaction(function () use ($invoice, $data, $amount) {
            $isSale = $invoice->type === Invoice::TYPE_SALE;
            $cashAccount = ! empty($data['bank_account_id']) ? '112' : '111';

            $payment = Payment::create([
                'company_id' => $invoice->company_id,
                'code' => $this->numbering->next('payment', $invoice->company_id),
                'type' => $isSale ? Payment::TYPE_RECEIPT : Payment::TYPE_PAYMENT,
                'partner_type' => $isSale ? 'customer' : 'supplier',
                'partner_id' => $isSale ? $invoice->customer_id : $invoice->supplier_id,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'status' => Payment::STATUS_POSTED,
                'description' => $data['description'] ?? "Thanh toán hóa đơn {$invoice->code}",
            ]);

            $this->journal->post([
                'entry_date' => $data['payment_date'],
                'description' => $payment->description,
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'lines' => $isSale
                    ? [
                        ['account_code' => $cashAccount, 'debit' => $amount, 'credit' => 0],
                        ['account_code' => '131', 'debit' => 0, 'credit' => $amount, 'partner_type' => 'customer', 'partner_id' => $invoice->customer_id],
                    ]
                    : [
                        ['account_code' => '331', 'debit' => $amount, 'credit' => 0, 'partner_type' => 'supplier', 'partner_id' => $invoice->supplier_id],
                        ['account_code' => $cashAccount, 'debit' => 0, 'credit' => $amount],
                    ],
            ]);

            // Đủ tiền thì đánh dấu hóa đơn đã thanh toán xong.
            if ($invoice->fresh()->remaining_amount <= 0.01) {
                $invoice->update(['status' => Invoice::STATUS_PAID]);
            }

            return $payment;
        });
    }

    /**
     * Ghi sổ phiếu thu/chi độc lập (không gắn hóa đơn).
     * Đơn giản hóa: thu độc lập ghi Có 711 (thu nhập khác),
     * chi độc lập ghi Nợ 811 (chi phí khác); tiền qua 112 (ngân hàng)
     * hoặc 111 (tiền mặt) tùy có chọn tài khoản ngân hàng hay không.
     */
    public function postStandalonePayment(Payment $payment): Payment
    {
        if ($payment->status !== Payment::STATUS_DRAFT) {
            throw new DomainException('Chỉ ghi sổ được phiếu thu/chi đang ở trạng thái nháp.');
        }

        if ($payment->invoice_id) {
            throw new DomainException('Phiếu gắn với hóa đơn được ghi sổ cùng lúc khi thanh toán, không ghi sổ riêng.');
        }

        return DB::transaction(function () use ($payment) {
            $cashAccount = $payment->bank_account_id ? '112' : '111';
            $amount = (float) $payment->amount;

            $this->journal->post([
                'entry_date' => $payment->payment_date->toDateString(),
                'description' => $payment->description ?? "Phiếu thu/chi {$payment->code}",
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'lines' => $payment->type === Payment::TYPE_RECEIPT
                    ? [
                        ['account_code' => $cashAccount, 'debit' => $amount, 'credit' => 0],
                        ['account_code' => '711', 'debit' => 0, 'credit' => $amount],
                    ]
                    : [
                        ['account_code' => '811', 'debit' => $amount, 'credit' => 0],
                        ['account_code' => $cashAccount, 'debit' => 0, 'credit' => $amount],
                    ],
            ]);

            $payment->update(['status' => Payment::STATUS_POSTED]);

            return $payment;
        });
    }
}
