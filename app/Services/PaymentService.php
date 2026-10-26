<?php

namespace App\Services;

use App\Domain\AuditAction;
use App\Domain\OrderStatus;
use App\Domain\PaymentMethod;
use App\Exceptions\Domain\AllocationMismatchException;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\OverpaymentException;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Payments are recorded, never inferred (invariant 5). One payment can span
 * several orders; payment_allocations holds the split, and the split must sum
 * to exactly the payment amount (invariant 13).
 *
 * The owner asked for manual allocation selection, so the caller supplies the
 * split explicitly. suggestOldestFirst() is offered only as a starting point.
 */
class PaymentService
{
    public function __construct(private AuditService $audit) {}

    /**
     * @param  list<array{order_id:int, amount:numeric}>  $allocations
     */
    public function record(
        array $allocations,
        $amount,
        string $method,
        int $receivedByAdminId,
        ?string $referenceNo = null,
        ?string $note = null,
        ?string $receivedAt = null,
        ?Admin $actor = null,
    ): Payment {
        $amount = round((float) $amount, 2);

        if (! in_array($method, PaymentMethod::ALL, true)) {
            throw new DomainException("Unknown payment method: {$method}");
        }
        if ($amount <= 0) {
            throw new DomainException('A payment must be greater than zero.');
        }
        if (count($allocations) === 0) {
            throw new DomainException('A payment must be allocated to at least one order.');
        }

        $allocated = round(array_sum(array_map(fn ($a) => (float) $a['amount'], $allocations)), 2);

        if (abs($allocated - $amount) > 0.005) {
            throw AllocationMismatchException::sum(number_format($allocated, 2), number_format($amount, 2));
        }

        return DB::transaction(function () use ($allocations, $amount, $method, $receivedByAdminId, $referenceNo, $note, $receivedAt, $actor) {
            $orderIds = array_map(fn ($a) => (int) $a['order_id'], $allocations);
            $balances = DB::table('v_orders')->whereIn('order_id', $orderIds)->get()->keyBy('order_id');

            foreach ($allocations as $allocation) {
                Validator::make($allocation, [
                    'order_id' => ['required', 'integer'],
                    'amount' => ['required', 'numeric', 'gt:0'],
                ])->validate();

                $orderId = (int) $allocation['order_id'];
                $view = $balances->get($orderId);

                if (! $view) {
                    throw new DomainException("Order #{$orderId} was not found.");
                }
                if ($view->status === OrderStatus::CANCELLED) {
                    throw new DomainException("Order {$view->order_code} is cancelled and cannot be paid.");
                }

                $balance = (float) $view->balance;
                if ((float) $allocation['amount'] - $balance > 0.005) {
                    throw OverpaymentException::for($view->order_code, number_format($balance, 2));
                }
            }

            $singleOrderId = count($allocations) === 1 ? (int) $allocations[0]['order_id'] : null;

            $payment = Payment::create([
                'order_id' => $singleOrderId,
                'amount' => $amount,
                'method' => $method,
                'reference_no' => $referenceNo,
                'received_by' => $receivedByAdminId,
                'note' => $note,
                'received_at' => $receivedAt ?? now(),
            ]);

            foreach ($allocations as $allocation) {
                $payment->allocations()->create([
                    'order_id' => (int) $allocation['order_id'],
                    'amount' => round((float) $allocation['amount'], 2),
                ]);
            }

            $this->audit->admin(
                AuditAction::PAYMENT_RECORDED,
                $actor?->admin_id ?? $receivedByAdminId,
                $actor?->admin_name,
                'payment',
                $payment->payment_id,
                '₱'.number_format($amount, 2)." via {$method} across ".count($allocations).' order(s)',
            );

            return $payment->refresh();
        });
    }

    public function void(Payment $payment, string $reason, ?Admin $actor = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor) {
            if ($payment->isVoided()) {
                throw new DomainException('This payment is already voided.');
            }
            if (blank($reason)) {
                throw new DomainException('Voiding a payment requires a reason.');
            }

            $payment->update([
                'voided_at' => now(),
                'voided_by' => $actor?->admin_id,
                'void_reason' => $reason,
            ]);

            $this->audit->admin(
                AuditAction::PAYMENT_VOIDED,
                $actor?->admin_id,
                $actor?->admin_name,
                'payment',
                $payment->payment_id,
                '₱'.number_format((float) $payment->amount, 2)." — {$reason}",
            );

            return $payment->refresh();
        });
    }

    /**
     * Oldest open order first — the rice-shop convention, offered as a UI
     * convenience only. Selection remains manual.
     *
     * @return list<array{order_id:int, amount:numeric}>
     */
    public function suggestOldestFirst(int $customerId, $amount): array
    {
        $remaining = round((float) $amount, 2);

        $rows = DB::table('v_orders')
            ->where('customer_id', $customerId)
            ->whereNotIn('status', [OrderStatus::DELIVERED, OrderStatus::CANCELLED])
            ->where('balance', '>', 0.005)
            ->orderBy('created_at')
            ->orderBy('order_id')
            ->get();

        $suggestions = [];

        foreach ($rows as $row) {
            if ($remaining <= 0.005) {
                break;
            }
            $take = round(min($remaining, (float) $row->balance), 2);
            $suggestions[] = ['order_id' => (int) $row->order_id, 'amount' => $take];
            $remaining = round($remaining - $take, 2);
        }

        return $suggestions;
    }
}
