<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\PriceList;
use App\Modules\Sales\Requests\StorePriceListRequest;
use App\Modules\Sales\Support\CatalogLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Trang web quản lý bảng giá (Inertia). */
class PriceListController extends Controller
{
    use CatalogLookup;

    public function index(Request $request)
    {
        $query = PriceList::query()->withCount('items');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return Inertia::render('Sales/PriceLists/Index', [
            'priceLists' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Sales/PriceLists/Create', [
            'products' => $this->productList(),
        ]);
    }

    public function store(StorePriceListRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $priceList = PriceList::create([
                'code' => $data['code'] ?? $this->nextCode(),
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                $priceList->items()->create([
                    'product_id' => $item['product_id'],
                    'price' => $item['price'],
                    'min_qty' => $item['min_qty'] ?? 1,
                ]);
            }
        });

        return redirect()->route('sales.price-lists.index')->with('success', 'Đã tạo bảng giá.');
    }

    public function destroy(PriceList $priceList)
    {
        $priceList->delete();

        return redirect()->route('sales.price-lists.index')->with('success', 'Đã xóa bảng giá.');
    }

    protected function nextCode(): string
    {
        $count = PriceList::where('company_id', CurrentCompany::id())
            ->whereYear('created_at', now()->year)
            ->count();

        return sprintf('BG-%s-%04d', now()->format('Y'), $count + 1);
    }
}
