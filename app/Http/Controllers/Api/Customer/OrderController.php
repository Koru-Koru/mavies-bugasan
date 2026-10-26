<?php

namespace App\Http\Controllers\Api\Customer;

use App\Domain\FulfillmentMethod;
use App\Domain\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $page = DB::table('v_orders')
            ->where('customer_id', $customer->customer_id)
            ->orderByDesc('created_at')
            ->paginate(15);

        $rows = $page->items();
        $items = $this->itemsFor($rows);

        return response()->json([
            'data' => array_map(
                fn ($r) => $this->presentRow($r, $items[(int) $r->order_id] ?? []),
                $rows,
            ),
            'pagination' => $this->pagination($page),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fulfillment_method' => ['required', Rule::in(FulfillmentMethod::ALL)],
            'payment_method' => ['required', Rule::in(PaymentMethod::CUSTOMER_CHOICES)],
            'payment_proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'address_bldg' => ['nullable', 'string', 'max:90'],
            'address_street' => ['nullable', 'string', 'max:120'],
            'address_brgy' => ['nullable', 'string', 'max:120'],
            'address_city' => ['nullable', 'string', 'max:90'],
            'address_notes' => ['nullable', 'string', 'max:200'],
            'staff_notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.inventory_id' => ['required', 'integer', 'exists:inventory,inventory_id'],
            'lines.*.sacks' => ['required', 'integer', 'min:1', 'max:500'],
            'lines.*.staff_note' => ['nullable', 'string', 'max:255'],
        ]);

        $proofPath = null;

        if ($request->hasFile('payment_proof')
            && in_array($data['payment_method'], [PaymentMethod::GCASH, PaymentMethod::MAYA], true)) {
            $dir = public_path('uploads/payment-proofs');
            File::ensureDirectoryExists($dir);

            $name = $request->file('payment_proof')->hashName();
            $request->file('payment_proof')->move($dir, $name);
            $proofPath = 'payment-proofs/'.$name;
        }

        // The customer id comes from the session, never the request body
        // (invariant 11): a posted customer_id is ignored entirely.
        try {
            $order = $this->orders->create(
                $request->user('customer'),
                $data['lines'],
                $data['fulfillment_method'],
                [
                    'bldg' => $data['address_bldg'] ?? null,
                    'street' => $data['address_street'] ?? null,
                    'brgy' => $data['address_brgy'] ?? null,
                    'city' => $data['address_city'] ?? null,
                    'notes' => $data['address_notes'] ?? null,
                ],
                null,
                $data['staff_notes'] ?? null,
                $data['payment_method'],
                $proofPath,
            );
        } catch (\Throwable $e) {
            if ($proofPath !== null) {
                File::delete(public_path('uploads/'.$proofPath));
            }

            throw $e;
        }

        return response()->json(['order' => $this->presentOne($order->order_id)], 201);
    }

    public function show(Request $request, int $orderId): JsonResponse
    {
        $customer = $request->user('customer');

        $row = DB::table('v_orders')
            ->where('order_id', $orderId)
            ->where('customer_id', $customer->customer_id)
            ->first();

        abort_if($row === null, 404);

        return response()->json([
            'order' => $this->presentOne($orderId),
            'items' => DB::table('order_items')->where('order_id', $orderId)->get(),
        ]);
    }

    /**
     * A customer may cancel their own order while it is still cancellable
     * (Pending/Confirmed/Ready/Out for Delivery). Stock restoration and the
     * single-use guard live in OrderService::changeStatus.
     */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $customer = $request->user('customer');

        $order = Order::query()
            ->where('order_id', $orderId)
            ->where('customer_id', $customer->customer_id)
            ->first();

        abort_if($order === null, 404);

        $this->orders->cancel($order, null, $data['reason'] ?? 'Cancelled by customer', $customer);

        return response()->json(['order' => $this->presentOne($order->order_id)]);
    }

    private function presentOne(int $orderId): array
    {
        $row = DB::table('v_orders')->where('order_id', $orderId)->first();

        if ($row === null) {
            return [];
        }

        $items = $this->itemsFor([$row]);

        return $this->presentRow($row, $items[(int) $row->order_id] ?? []);
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
            'variety_name' => $item->variety_name,
            'sacks' => (int) $item->sacks,
            'unit_price' => (float) ($item->final_unit_price ?? $item->unit_price),
            'line_total' => (float) $item->subtotal,
            'staff_note' => $item->staff_note,
        ];
    }

    private function presentRow(?object $r, array $items = []): array
    {
        if ($r === null) {
            return [];
        }

        return [
            'order_id' => (int) $r->order_id,
            'order_code' => $r->order_code,
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
