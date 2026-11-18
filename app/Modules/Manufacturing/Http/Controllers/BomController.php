<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Core\Models\Unit;
use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Requests\StoreBomRequest;
use App\Modules\Manufacturing\Support\CatalogLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Trang web quản lý định mức BOM (Inertia). */
class BomController extends Controller
{
    use CatalogLookup;

    public function index(Request $request)
    {
        $query = Bom::query()->withCount('lines');

        if ($request->filled('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $boms = $query->latest()->paginate(15)->withQueryString();
        $products = $this->productMap($boms->getCollection()->pluck('product_id')->unique()->all());
        $boms->getCollection()->each(function (Bom $bom) use ($products) {
            $bom->product_name = $products[$bom->product_id]['name'] ?? ('Sản phẩm #'.$bom->product_id);
            $bom->product_sku = $products[$bom->product_id]['sku'] ?? null;
        });

        return Inertia::render('Manufacturing/Boms/Index', [
            'boms' => $boms,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Manufacturing/Boms/Create', [
            'products' => $this->productList(),
            'units' => Unit::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(StoreBomRequest $request)
    {
        $data = $request->validated();

        $bom = DB::transaction(function () use ($data) {
            $bom = Bom::create([
                'code' => $data['code'] ?? $this->nextCode(),
                'product_id' => $data['product_id'],
                'version' => $data['version'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $bom->lines()->create([
                    'component_product_id' => $line['component_product_id'],
                    'qty' => $line['qty'],
                    'unit_id' => $line['unit_id'],
                    'scrap_rate' => $line['scrap_rate'] ?? 0,
                ]);
            }

            return $bom;
        });

        return redirect()->route('manufacturing.boms.show', $bom)->with('success', "Đã tạo BOM {$bom->code}.");
    }

    public function show(Bom $bom)
    {
        $bom->load(['lines.unit']);
        $productIds = $bom->lines->pluck('component_product_id')->push($bom->product_id)->unique()->all();
        $products = $this->productMap($productIds);
        $bom->product_name = $products[$bom->product_id]['name'] ?? ('Sản phẩm #'.$bom->product_id);
        $bom->lines->each(function ($line) use ($products) {
            $line->component_name = $products[$line->component_product_id]['name'] ?? ('Sản phẩm #'.$line->component_product_id);
        });

        return Inertia::render('Manufacturing/Boms/Show', ['bom' => $bom]);
    }

    protected function nextCode(): string
    {
        $count = Bom::where('company_id', CurrentCompany::id())
            ->whereYear('created_at', now()->year)
            ->count();

        return sprintf('BOM-%s-%04d', now()->format('Y'), $count + 1);
    }
}
