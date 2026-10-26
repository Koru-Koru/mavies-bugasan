<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AuditAction;
use App\Domain\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('v_orders');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('order_code', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        $page = $query->orderByDesc('created_at')->paginate(min((int) $request->integer('per_page', 20), 100));

        $rows = $page->items();
        $items = $this->itemsFor($rows);

        return response()->json([
            'data' => array_map(
                fn ($r) => $this->presentRow($r, $items[(int) $r->order_id] ?? []),
                $rows,
            ),
            'pagination' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'pages' => $page->lastPage(),
            ],
        ]);
    }

    public function show(int $orderId): JsonResponse
    {
        $row = DB::table('v_orders')->where('order_id', $orderId)->first();
        abort_if($row === null, 404);

        $items = $this->itemsFor([$row])[(int) $row->order_id] ?? [];

        return response()->json([
            'order' => $this->presentRow($row, $items),
            'items' => $items,
            'allocations' => DB::table('payment_allocations as pa')
                ->join('payments as p', 'p.payment_id', '=', 'pa.payment_id')
                ->leftJoin('admins as a', 'a.admin_id', '=', 'p.received_by')
                ->where('pa.order_id', $orderId)
                ->orderByDesc('p.received_at')
                ->orderByDesc('p.payment_id')
                ->select(
                    'pa.allocation_id',
                    'pa.amount',
                    'p.payment_id',
                    'p.method',
                    'p.reference_no',
                    'p.note',
                    'p.received_at',
                    'p.voided_at',
                    'p.void_reason',
                    'a.admin_name as received_by_name',
                )
                ->get(),
            'history' => DB::table('order_status_history')->where('order_id', $orderId)->orderBy('created_at')->get(),
        ]);
    }

    public function updateStatus(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(OrderStatus::ALL)],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->orders->changeStatus(
            Order::findOrFail($orderId),
            $data['status'],
            $request->user('admin'),
            $data['reason'] ?? null,
            $data['note'] ?? null,
        );

        return response()->json(['order' => $this->presentRow(DB::table('v_orders')->where('order_id', $order->order_id)->first())]);
    }

    public function price(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'items' => ['array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
            'confirmed_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = $this->orders->price(
            Order::findOrFail($orderId),
            $data['items'] ?? [],
            $data['confirmed_total'] ?? null,
            $request->user('admin'),
        );

        return response()->json(['order' => $this->presentRow(DB::table('v_orders')->where('order_id', $order->order_id)->first())]);
    }

    public function note(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate(['staff_notes' => ['nullable', 'string', 'max:1000']]);

        $order = $this->orders->note(Order::findOrFail($orderId), $data['staff_notes'] ?? null, $request->user('admin'));

        return response()->json(['order' => $this->presentRow(DB::table('v_orders')->where('order_id', $order->order_id)->first())]);
    }

    /**
     * Courier details are shown to the customer in View Orders. The link is
     * free text (any tracker URL) and is cleared by sending blanks.
     */
    public function courier(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'courier_name' => ['nullable', 'string', 'max:80'],
            'courier_link' => ['nullable', 'url', 'max:500'],
        ]);

        $order = Order::findOrFail($orderId);

        if ($order->status === OrderStatus::CANCELLED) {
            abort(422, 'A cancelled order cannot have a courier link.');
        }

        $order->forceFill([
            'courier_name' => $data['courier_name'] ?? null,
            'courier_link' => $data['courier_link'] ?? null,
        ])->save();

        $admin = $request->user('admin');

        $this->audit->admin(
            AuditAction::ORDER_COURIER_UPDATED,
            $admin->admin_id,
            $admin->admin_name,
            'order',
            $order->order_id,
            $order->courier_link
                ? 'Courier link set'.($order->courier_name ? " ({$order->courier_name})" : '')
                : 'Courier link removed',
        );

        return response()->json([
            'order' => $this->presentRow(DB::table('v_orders')->where('order_id', $order->order_id)->first()),
        ]);
    }

    /**
     * The varieties in each order, fetched once per page.
     *
     * @param  array<int, object>  $rows
     * @return array<int, array<int, array>>
     */
    private function itemsFor(array $rows): array
    {
        $ids = array_map(fn ($r) => (int) $r->order_id, $rows);

        if ($ids === []) {
            return [];
        }

        return DB::table('order_items')
            ->whereIn('order_id', $ids)
            ->orderBy('order_item_id')
            ->get()
            ->groupBy('order_id')
            ->map(fn ($group) => $group->map(fn ($item) => $this->presentItem($item))->all())
            ->all();
    }

    private function presentItem(object $item): array
    {
        return [
            'order_item_id' => (int) $item->order_item_id,
            'inventory_id' => (int) $item->inventory_id,
            'name' => $item->variety_name,
            'variety_name' => $item->variety_name,
            'sacks' => (int) $item->sacks,
            'unit_price' => (float) ($item->final_unit_price ?? $item->unit_price),
            'base_unit_price' => (float) $item->unit_price,
            'final_unit_price' => $item->final_unit_price === null ? null : (float) $item->final_unit_price,
            'line_total' => (float) $item->subtotal,
            'staff_note' => $item->staff_note,
        ];
    }

    private function presentRow(object $r, array $items = []): array
    {
        return [
            'order_id' => (int) $r->order_id,
            'order_code' => $r->order_code,
            'customer_id' => (int) $r->customer_id,
            'customer_name' => $r->customer_name,
            'phone' => $r->phone,
            'blacklisted' => (bool) $r->blacklisted,
            'status' => $r->status,
            'fulfillment_method' => $r->fulfillment_method,
            'payment_method' => $r->payment_method,
            'payment_proof_path' => $r->payment_proof_path,
            'payment_proof_url' => $r->payment_proof_path ? '/uploads/'.$r->payment_proof_path : null,
            'courier_name' => $r->courier_name,
            'courier_link' => $r->courier_link,
            'total_sacks' => (int) $r->total_sacks,
            'item_count' => (int) $r->item_count,
            'items' => $items,
            'amount_due' => (float) $r->amount_due,
            'amount_paid' => (float) $r->amount_paid,
            'balance' => (float) $r->balance,
            'payment_status' => $r->payment_status,
            'classification' => $r->classification,
            'created_at' => $r->created_at,
        ];
    }
}
