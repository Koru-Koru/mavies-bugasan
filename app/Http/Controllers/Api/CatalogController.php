<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The order form's variety list and, in the customer app, the Catalog tab.
 * Public on purpose (a customer browses before signing in) — this is an
 * explicit exception to default-deny, not an oversight. Paginated like every
 * other list endpoint (invariant 9).
 */
class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 50), 100);

        $data = $request->validate([
            'category' => ['nullable', Rule::in(['Local', 'Imported', 'Japanese', 'Thailand'])],
            'sort' => ['nullable', Rule::in(['name', 'price_asc', 'price_desc', 'stock'])],
        ]);

        $query = InventoryItem::query()->where('is_active', true);

        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }

        $sort = $data['sort'] ?? 'name';

        $query->orderBy(
            match ($sort) {
                'price_asc', 'price_desc' => 'unit_price',
                'stock' => 'stock',
                default => 'name',
            },
            match ($sort) {
                'price_asc' => 'asc',
                'price_desc', 'stock' => 'desc',
                default => 'asc',
            },
        );

        if ($sort === 'name') {
            $query->orderBy('name');
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (InventoryItem $i) => [
                'inventory_id' => $i->inventory_id,
                'name' => $i->name,
                'category' => $i->category,
                'unit_price' => (float) $i->unit_price,
                'wholesale_price' => (float) $i->wholesale_price,
                'stock' => $i->stock,
                'low_stock' => $i->isLowStock(),
            ]),
            'facets' => [
                'categories' => InventoryItem::query()
                    ->where('is_active', true)
                    ->distinct()
                    ->orderBy('category')
                    ->pluck('category'),
            ],
            'pagination' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'pages' => $page->lastPage(),
            ],
        ]);
    }
}
