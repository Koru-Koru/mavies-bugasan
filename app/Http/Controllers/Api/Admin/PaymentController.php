<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('payments as p')
            ->leftJoin('admins as a', 'a.admin_id', '=', 'p.received_by')
            ->select('p.*', 'a.admin_name as received_by_name');

        if ($request->filled('order_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('p.order_id', $request->integer('order_id'))
                    ->orWhereIn('p.payment_id', function ($sub) use ($request) {
                        $sub->select('payment_id')->from('payment_allocations')->where('order_id', $request->integer('order_id'));
                    });
            });
        }

        $page = $query->orderByDesc('p.received_at')->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json([
            'data' => array_map(function ($p) {
                $p->amount = (float) $p->amount;
                $p->allocations = DB::table('payment_allocations as pa')
                    ->join('orders as o', 'o.order_id', '=', 'pa.order_id')
                    ->where('pa.payment_id', $p->payment_id)
                    ->orderBy('pa.allocation_id')
                    ->select('pa.order_id', 'pa.amount', 'o.order_code')
                    ->get();

                return $p;
            }, $page->items()),
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(PaymentMethod::ALL)],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.order_id' => ['required', 'integer', 'exists:orders,order_id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $payment = $this->payments->record(
            $data['allocations'],
            $data['amount'],
            $data['method'],
            $request->user('admin')->admin_id,
            $data['reference_no'] ?? null,
            $data['note'] ?? null,
            $data['received_at'] ?? null,
            $request->user('admin'),
        );

        return response()->json(['payment' => $payment], 201);
    }

    public function void(Request $request, int $paymentId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $payment = $this->payments->void(Payment::findOrFail($paymentId), $data['reason'], $request->user('admin'));

        return response()->json(['payment' => $payment]);
    }

    /** Auto-split oldest-open-first, offered to the UI as a starting point. */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,customer_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json([
            'allocations' => $this->payments->suggestOldestFirst($data['customer_id'], $data['amount']),
        ]);
    }
}
