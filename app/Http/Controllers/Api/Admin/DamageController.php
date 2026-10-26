<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\DamageType;
use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DamageController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('damage_logs');
        if ($request->filled('inventory_id')) {
            $query->where('inventory_id', $request->integer('inventory_id'));
        }

        $page = $query->orderByDesc('date_logged')->orderByDesc('damage_log_id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $page->items(),
            'pagination' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'pages' => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_id' => ['required', 'integer', 'exists:inventory,inventory_id'],
            'sacks' => ['required', 'integer', 'min:1', 'max:100000'],
            'damage_type' => ['required', Rule::in(DamageType::ALL)],
            'date_logged' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $damage = $this->inventory->logDamage(
            $data['inventory_id'],
            $data['sacks'],
            $data['damage_type'],
            $data['date_logged'],
            $data['notes'] ?? null,
            $request->user('admin'),
        );

        return response()->json(['damage' => $damage], 201);
    }
}
