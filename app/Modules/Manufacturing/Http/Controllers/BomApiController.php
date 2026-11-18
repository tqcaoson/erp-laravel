<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Core\Services\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Requests\StoreBomRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** REST API định mức BOM: danh sách, tạo, xem chi tiết. */
class BomApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Bom::query()->withCount('lines');

        if ($request->filled('filter.product_id')) {
            $query->where('product_id', $request->input('filter.product_id'));
        }

        if ($request->filled('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json($query->latest()->paginate($perPage));
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

        return response()->json($bom->load('lines'), 201);
    }

    public function show(Bom $bom)
    {
        return response()->json($bom->load(['lines.unit']));
    }

    protected function nextCode(): string
    {
        $count = Bom::where('company_id', CurrentCompany::id())
            ->whereYear('created_at', now()->year)
            ->count();

        return sprintf('BOM-%s-%04d', now()->format('Y'), $count + 1);
    }
}
