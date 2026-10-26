<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('transaction_log');

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->string('action_type'));
        }
        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->string('actor_type'));
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type'));
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->integer('entity_id'));
        }

        $page = $query->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 30), 100));

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
}
