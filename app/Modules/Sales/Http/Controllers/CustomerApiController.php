<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Requests\StoreCustomerRequest;
use Illuminate\Http\Request;

/** REST API khách hàng: danh sách (kèm công nợ), tạo, xem chi tiết. */
class CustomerApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $customers = $query->orderBy('name')->paginate($perPage);

        $customers->getCollection()->each(function (Customer $customer) {
            $customer->current_debt = $customer->currentDebt();
            $customer->remaining_credit = $customer->remainingCredit();
        });

        return response()->json($customers);
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $data['code'] = $data['code'] ?? $this->nextCode();

        $customer = Customer::create($data);

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        $customer->load('contacts');
        $customer->current_debt = $customer->currentDebt();
        $customer->remaining_credit = $customer->remainingCredit();

        return response()->json($customer);
    }

    protected function nextCode(): string
    {
        $count = Customer::withTrashed()
            ->where('company_id', CurrentCompany::id())
            ->whereYear('created_at', now()->year)
            ->count();

        return sprintf('KH-%s-%04d', now()->format('Y'), $count + 1);
    }
}
