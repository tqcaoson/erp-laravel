<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\StorePaymentRequest;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Services\InvoiceService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Web: phiếu thu / phiếu chi. */
class PaymentController
{
    public function __construct(protected InvoiceService $invoices) {}

    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->with(['bankAccount', 'invoice'])
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('filter.type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Accounting/Payments/Index', [
            'payments' => $payments,
            'filters' => [
                'status' => $request->input('filter.status'),
                'type' => $request->input('filter.type'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounting/Payments/Create', [
            'bankAccounts' => BankAccount::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'openInvoices' => Invoice::query()
                ->whereIn('status', [Invoice::STATUS_POSTED, Invoice::STATUS_PAID])
                ->orderByDesc('invoice_date')
                ->get(['id', 'code', 'type', 'total_amount']),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            if (! empty($data['invoice_id'])) {
                // Thanh toán gắn hóa đơn: ghi sổ ngay + cập nhật trạng thái hóa đơn.
                $invoice = Invoice::findOrFail($data['invoice_id']);
                $this->invoices->recordPayment($invoice, $data);
            } else {
                // Phiếu độc lập: lưu nháp, ghi sổ sau bằng nút "Ghi sổ".
                Payment::create([
                    'code' => app(\App\Core\Services\DocumentNumberingService::class)->next('payment'),
                    'type' => $data['type'],
                    'partner_type' => $data['partner_type'] ?? null,
                    'partner_id' => $data['partner_id'] ?? null,
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'amount' => $data['amount'],
                    'payment_date' => $data['payment_date'],
                    'status' => Payment::STATUS_DRAFT,
                    'description' => $data['description'] ?? null,
                ]);
            }
        } catch (DomainException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('accounting.payments.index')
            ->with('success', 'Đã tạo phiếu thu/chi.');
    }

    /** Ghi sổ phiếu thu/chi độc lập (không gắn hóa đơn). */
    public function post(Payment $payment): RedirectResponse
    {
        try {
            $this->invoices->postStandalonePayment($payment);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Đã ghi sổ phiếu {$payment->code}.");
    }
}
