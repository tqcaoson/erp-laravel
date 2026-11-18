<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Requests\StoreCustomerRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Trang web quản lý khách hàng (Inertia). */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->withCount('salesOrders');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customers = $query->orderBy('name')->paginate(15)->withQueryString();

        // Kèm công nợ hiện tại + hạn mức còn lại để hiển thị trên bảng.
        $customers->getCollection()->each(function (Customer $customer) {
            $customer->current_debt = $customer->currentDebt();
            $customer->remaining_credit = $customer->remainingCredit();
        });

        return Inertia::render('Sales/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Sales/Customers/Create', ['customer' => null]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $data['code'] = $data['code'] ?? $this->nextCode();

        Customer::create($data);

        return redirect()->route('sales.customers.index')->with('success', 'Đã tạo khách hàng.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['contacts', 'salesOrders' => fn ($q) => $q->latest()->limit(10)]);
        $customer->current_debt = $customer->currentDebt();
        $customer->remaining_credit = $customer->remainingCredit();

        return Inertia::render('Sales/Customers/Show', ['customer' => $customer]);
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Sales/Customers/Create', ['customer' => $customer]);
    }

    public function update(StoreCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return redirect()->route('sales.customers.index')->with('success', 'Đã cập nhật khách hàng.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('sales.customers.index')->with('success', 'Đã xóa khách hàng.');
    }

    /** Sinh mã KH-YYYY-XXXX (không dùng DocumentNumberingService vì không có loại 'customer'). */
    protected function nextCode(): string
    {
        $count = Customer::withTrashed()
            ->where('company_id', CurrentCompany::id())
            ->whereYear('created_at', now()->year)
            ->count();

        return sprintf('KH-%s-%04d', now()->format('Y'), $count + 1);
    }
}
