<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\InventoryCategory;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): JsonResponse
    {
        $query = InventoryItem::query();

        // status: active (default), archived (only), all. include_archived is
        // still honoured for older callers that only send the checkbox.
        $status = (string) $request->input('status', '');

        if ($status === 'archived') {
            $query->where('is_active', false);
        } elseif ($status === 'all') {
            // no filter - show both
        } elseif (! $request->boolean('include_archived')) {
            $query->where('is_active', true);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }
        if ($request->input('stock_status') === 'out') {
            $query->where('stock', 0);
        } elseif ($request->input('stock_status') === 'low') {
            $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold');
        } elseif ($request->input('stock_status') === 'in') {
            $query->whereColumn('stock', '>', 'low_stock_threshold');
        }

        $page = $query->orderBy('category')->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 30), 100));

        return response()->json([
            'data' => $page->items(),
            'pagination' => $this->pagination($page),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:inventory,name'],
            'category' => ['required', Rule::in(InventoryCategory::ALL)],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $item = $this->inventory->create($data, $request->user('admin'));

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, int $inventoryId): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('inventory', 'name')->ignore($inventoryId, 'inventory_id')],
            'category' => ['sometimes', Rule::in(InventoryCategory::ALL)],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'wholesale_price' => ['sometimes', 'numeric', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $item = $this->inventory->update($inventoryId, $data, $request->user('admin'));

        return response()->json(['item' => $item]);
    }

    public function adjustStock(Request $request, int $inventoryId): JsonResponse
    {
        $data = $request->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $item = $this->inventory->adjustStock($inventoryId, $data['stock'], $data['reason'], $request->user('admin'));

        return response()->json(['item' => $item]);
    }

    public function archive(Request $request, int $inventoryId): JsonResponse
    {
        return response()->json(['item' => $this->inventory->archive($inventoryId, $request->user('admin'))]);
    }

    public function restore(Request $request, int $inventoryId): JsonResponse
    {
        return response()->json(['item' => $this->inventory->restore($inventoryId, $request->user('admin'))]);
    }

    public function movements(Request $request, int $inventoryId): JsonResponse
    {
        $page = DB::table('stock_movements')
            ->where('inventory_id', $inventoryId)
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 30), 100));

        return response()->json([
            'data' => $page->items(),
            'pagination' => $this->pagination($page),
        ]);
    }

    private function pagination($page): array
    {
        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'pages' => $page->lastPage(),
        ];
    }
}
