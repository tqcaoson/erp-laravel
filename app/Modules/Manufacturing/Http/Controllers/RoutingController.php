<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\Routing;
use App\Modules\Manufacturing\Models\WorkCenter;
use App\Modules\Manufacturing\Requests\StoreRoutingRequest;
use App\Modules\Manufacturing\Support\CatalogLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Trang web quản lý quy trình sản xuất (routing) — Inertia. */
class RoutingController extends Controller
{
    use CatalogLookup;

    public function index(Request $request)
    {
        $routings = Routing::query()->withCount('operations')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $products = $this->productMap($routings->getCollection()->pluck('product_id')->filter()->unique()->all());
        $routings->getCollection()->each(function (Routing $routing) use ($products) {
            $routing->product_name = $routing->product_id
                ? ($products[$routing->product_id]['name'] ?? ('Sản phẩm #'.$routing->product_id))
                : null;
        });

        return Inertia::render('Manufacturing/Routings/Index', [
            'routings' => $routings,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Manufacturing/Routings/Create', [
            'products' => $this->productList(),
            'workCenters' => WorkCenter::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(StoreRoutingRequest $request)
    {
        $data = $request->validated();

        $routing = DB::transaction(function () use ($data) {
            $routing = Routing::create([
                'code' => $data['code'] ?? $this->nextCode(),
                'name' => $data['name'],
                'product_id' => $data['product_id'] ?? null,
            ]);

            foreach ($data['operations'] as $index => $operation) {
                $routing->operations()->create([
                    'work_center_id' => $operation['work_center_id'],
                    'sequence' => $operation['sequence'] ?? ($index + 1),
                    'operation_name' => $operation['operation_name'],
                    'standard_minutes' => $operation['standard_minutes'] ?? 0,
                ]);
            }

            return $routing;
        });

        return redirect()->route('manufacturing.routings.show', $routing)->with('success', "Đã tạo quy trình {$routing->code}.");
    }

    public function show(Routing $routing)
    {
        $routing->load(['operations.workCenter:id,code,name']);
        $products = $this->productMap($routing->product_id ? [$routing->product_id] : []);
        $routing->product_name = $routing->product_id
            ? ($products[$routing->product_id]['name'] ?? ('Sản phẩm #'.$routing->product_id))
            : null;

        return Inertia::render('Manufacturing/Routings/Show', ['routing' => $routing]);
    }

    protected function nextCode(): string
    {
        $count = Routing::where('company_id', CurrentCompany::id())
            ->whereYear('created_at', now()->year)
            ->count();

        return sprintf('RTG-%s-%04d', now()->format('Y'), $count + 1);
    }
}
