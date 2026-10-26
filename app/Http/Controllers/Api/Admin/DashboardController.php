<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->startOfDay();

        $monthStart = now()->startOfMonth();

        $stats = DB::selectOne(<<<'SQL'
            SELECT
              (SELECT COUNT(*) FROM orders WHERE status NOT IN ('Delivered','Cancelled')) AS open_orders,
              (SELECT COUNT(*) FROM orders WHERE created_at >= ?)                          AS orders_today,
              (SELECT COUNT(*) FROM orders WHERE status = 'Delivered' AND delivered_at >= ?) AS delivered_today,
              (SELECT COALESCE(SUM(amount_due),0) FROM v_orders WHERE status = 'Delivered' AND delivered_at >= ?) AS revenue_month,
              (SELECT COUNT(*) FROM v_orders WHERE status <> 'Cancelled' AND balance > 0.005) AS unpaid_orders,
              (SELECT COALESCE(SUM(balance),0) FROM v_orders WHERE status <> 'Cancelled' AND balance > 0.005) AS outstanding,
              (SELECT COALESCE(SUM(stock),0) FROM inventory)                               AS sacks_in_stock
        SQL, [$today, $today, $monthStart]);

        $salesSeries = DB::select(
            'SELECT DATE(delivered_at) AS day,
                    COALESCE(SUM(amount_due), 0) AS revenue,
                    COUNT(*)                    AS orders
               FROM v_orders
              WHERE status = \'Delivered\' AND delivered_at >= ?
              GROUP BY DATE(delivered_at)
              ORDER BY day',
            [now()->subDays(13)->startOfDay()]
        );

        $bestSeller = DB::selectOne(
            'SELECT i.name,
                    SUM(oi.sacks) AS sacks,
                    SUM(oi.sacks * COALESCE(oi.final_unit_price, oi.unit_price)) AS revenue
               FROM order_items oi
               JOIN orders o    ON o.order_id = oi.order_id
               JOIN inventory i ON i.inventory_id = oi.inventory_id
              WHERE o.status = \'Delivered\' AND o.delivered_at >= ?
              GROUP BY i.inventory_id, i.name
              ORDER BY sacks DESC
              LIMIT 1',
            [$monthStart]
        );

        $lowStock = InventoryItem::query()
            ->where('is_active', true)
            ->whereRaw('stock <= low_stock_threshold')
            ->orderBy('stock')
            ->get(['inventory_id', 'name', 'stock', 'low_stock_threshold']);

        $recent = DB::table('v_orders')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['order_id', 'order_code', 'customer_name', 'status', 'amount_due', 'balance', 'payment_status', 'created_at']);

        return response()->json([
            'stats' => $stats,
            'low_stock' => $lowStock,
            'recent_orders' => $recent,
            'sales_series' => $salesSeries,
            'best_seller' => $bestSeller,
        ]);
    }
}
