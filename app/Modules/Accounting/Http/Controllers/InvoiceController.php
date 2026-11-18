<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\StoreInvoiceRequest;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\InvoiceService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Web: hóa đơn bán/mua (xem, lập, ghi sổ, thanh toán). */
class InvoiceController
{
    public function __construct(protected InvoiceService $invoices) {}

    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('filter.type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->input('search'), fn ($q, $search) => $q->where('code', 'like', "%{$search}%"))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Accounting/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'status' => $request->input('filter.status'),
                'type' => $request->input('filter.type'),
                'search' => $request->input('search'),
            ],
        ]);
    }

    public function create(): Response
    {
        // Module Sales/Procurement chưa có danh mục khách hàng/NCC để chọn,
        // nên form nhập id đối tác trực tiếp (sẽ thay bằng dropdown sau tích hợp).
        return Inertia::render('Accounting/Invoices/Create');
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->invoices->create($request->validated());

        return redirect()
            ->route('accounting.invoices.show', $invoice)
            ->with('success', "Đã lập hóa đơn {$invoice->code} (nháp).");
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load(['lines', 'payments']);

        return Inertia::render('Accounting/Invoices/Show', [
            'invoice' => $invoice,
            'bankAccounts' => BankAccount::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function post(Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoices->post($invoice);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Đã ghi sổ hóa đơn {$invoice->code}.");
    }

    /** Ghi nhận một khoản thanh toán cho hóa đơn đã ghi sổ. */
    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.min' => 'Số tiền thanh toán phải lớn hơn 0.',
            'payment_date.required' => 'Ngày thanh toán là bắt buộc.',
        ]);

        try {
            $this->invoices->recordPayment($invoice, $validated);
        } catch (DomainException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã ghi nhận thanh toán.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoices->cancel($invoice);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Đã hủy hóa đơn {$invoice->code}.");
    }
}
