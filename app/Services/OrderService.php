<?php

namespace App\Services;

use App\Domain\AuditAction;
use App\Domain\FulfillmentMethod;
use App\Domain\OrderStatus;
use App\Domain\PaymentMethod;
use App\Domain\RefType;
use App\Exceptions\Domain\BlacklistedCustomerException;
use App\Exceptions\Domain\CapacityException;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\IllegalTransitionException;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orders: header only, money computed by v_orders (invariant 12).
 *
 * Every method is one DB transaction. Stock is reserved at placement and
 * restored only on cancel; changing status never otherwise touches stock
 * (BUSINESS_LOGIC §2, §3).
 */
class OrderService
{
    public const MAX_LINES = 20;
    public const MAX_SACKS_PER_LINE = 500;
    public const UNVERIFIED_DAILY_CAP = 5;

    public function __construct(
        private InventoryService $inventory,
        private AuditService $audit,
    ) {}

    /**
     * @param  list<array{inventory_id:int,sacks:int,staff_note?:?string}>  $lines
     * @param  array{bldg?:?string,street?:?string,brgy?:?string,city?:?string,notes?:?string}  $address
     */
    public function create(
        Customer $customer,
        array $lines,
        string $fulfillment,
        array $address = [],
        ?Admin $actor = null,
        ?string $staffNotes = null,
        ?string $paymentMethod = null,
        ?string $paymentProofPath = null,
    ): Order {
        if ($customer->blacklisted) {
            throw BlacklistedCustomerException::for($customer->customer_id);
        }

        if (! in_array($fulfillment, FulfillmentMethod::ALL, true)) {
            throw new DomainException("Unknown fulfillment method: {$fulfillment}");
        }

        if ($paymentMethod !== null && ! in_array($paymentMethod, PaymentMethod::CUSTOMER_CHOICES, true)) {
            throw new DomainException("Unknown payment method: {$paymentMethod}");
        }

        $lines = $this->normaliseLines($lines);

        if (FulfillmentMethod::requiresAddress($fulfillment) && blank($address['city'] ?? null)) {
            throw new DomainException('A delivery address (city) is required for '.$fulfillment.' orders.');
        }

        $this->guardUnverifiedDailyCap($customer);

        return DB::transaction(function () use ($customer, $lines, $fulfillment, $address, $actor, $staffNotes, $paymentMethod, $paymentProofPath) {
            $order = Order::create([
                'order_code' => 'TMP'.Str::upper(Str::random(12)),
                'customer_id' => $customer->customer_id,
                'fulfillment_method' => $fulfillment,
                'payment_method' => $paymentMethod,
                'payment_proof_path' => $paymentProofPath,
                'address_bldg' => $address['bldg'] ?? null,
                'address_street' => $address['street'] ?? null,
                'address_brgy' => $address['brgy'] ?? null,
                'address_city' => $address['city'] ?? null,
                'address_notes' => $address['notes'] ?? null,
                'status' => OrderStatus::PENDING,
                'staff_notes' => $staffNotes,
                'created_by_admin' => $actor?->admin_id,
            ]);

            // Reserve stock after the header exists so the ledger can reference
            // the order id. Any shortfall throws and rolls the whole order back.
            foreach ($lines as $line) {
                $this->inventory->move(
                    $line['inventory_id'],
                    \App\Domain\MoveType::OUT,
                    -$line['sacks'],
                    "Reserved for order #{$order->order_id}",
                    RefType::ORDER,
                    $order->order_id,
                    $actor,
                );
            }

            foreach ($lines as $line) {
                $item = \App\Models\InventoryItem::findOrFail($line['inventory_id']);
                $order->items()->create([
                    'inventory_id' => $line['inventory_id'],
                    'variety_name' => $item->name,
                    'sacks' => $line['sacks'],
                    'unit_price' => $item->unit_price,
                    'final_unit_price' => null,
                    'staff_note' => $line['staff_note'] ?? null,
                ]);
            }

            // Race-free code: derive it from the id we just inserted.
            $order->update(['order_code' => 'ORD-'.str_pad((string) $order->order_id, 6, '0', STR_PAD_LEFT)]);

            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'from_status' => null,
                'to_status' => OrderStatus::PENDING,
                'changed_by' => $actor?->admin_id,
                'note' => 'Order placed',
            ]);

            $this->audit->log(
                AuditAction::ORDER_CREATED,
                $actor ? \App\Domain\ActorType::ADMIN : \App\Domain\ActorType::CUSTOMER,
                $actor?->admin_id ?? $customer->customer_id,
                $actor?->admin_name ?? $customer->fullName(),
                'order',
                $order->order_id,
                $order->order_code,
            );

            return $order->refresh();
        });
    }

    /**
     * The single place order status changes. Illegal jumps are refused.
     */
    public function changeStatus(
        Order $order,
        string $to,
        ?Admin $actor = null,
        ?string $reason = null,
        ?string $note = null,
        ?Customer $customerActor = null,
    ): Order {
        if (! in_array($to, OrderStatus::ALL, true)) {
            throw new DomainException("Unknown order status: {$to}");
        }

        if (! OrderStatus::canTransition($order->status, $to)) {
            throw IllegalTransitionException::from($order->status, $to);
        }

        return DB::transaction(function () use ($order, $to, $actor, $reason, $note, $customerActor) {
            $from = $order->status;
            $changes = ['status' => $to];

            if ($to === OrderStatus::CONFIRMED) {
                $changes['confirmed_at'] = $order->confirmed_at ?? now();
                $changes['confirmed_by_admin'] = $order->confirmed_by_admin ?? $actor?->admin_id;
            }

            if ($to === OrderStatus::DELIVERED) {
                $changes['delivered_at'] = now();
                $changes['confirmed_by_admin'] = $order->confirmed_by_admin ?? $actor?->admin_id;
            }

            if ($to === OrderStatus::CANCELLED) {
                if (blank($reason)) {
                    throw new DomainException('Cancelling an order requires a reason.');
                }
                $changes['cancelled_at'] = now();
                $changes['cancel_reason'] = $reason;
            }

            $order->update($changes);

            // Restore stock exactly once — only reachable through this
            // validated transition, and Cancelled is terminal.
            if ($to === OrderStatus::CANCELLED) {
                foreach ($order->items()->orderBy('inventory_id')->get() as $item) {
                    $this->inventory->move(
                        $item->inventory_id,
                        \App\Domain\MoveType::IN,
                        $item->sacks,
                        "Restored from cancelled order #{$order->order_id}",
                        RefType::ORDER,
                        $order->order_id,
                        $actor,
                    );
                }
            }

            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor?->admin_id,
                'note' => $note ?? $reason,
            ]);

            if ($customerActor) {
                $this->audit->customer(AuditAction::ORDER_STATUS_CHANGED, $customerActor->customer_id, $customerActor->fullName(), 'order', $order->order_id, "{$from} -> {$to}");
            } else {
                $this->audit->admin(AuditAction::ORDER_STATUS_CHANGED, $actor?->admin_id, $actor?->admin_name, 'order', $order->order_id, "{$from} -> {$to}");
            }

            if ($to === OrderStatus::CANCELLED) {
                if ($customerActor) {
                    $this->audit->customer(AuditAction::ORDER_CANCELLED, $customerActor->customer_id, $customerActor->fullName(), 'order', $order->order_id, $reason);
                } else {
                    $this->audit->admin(AuditAction::ORDER_CANCELLED, $actor?->admin_id, $actor?->admin_name, 'order', $order->order_id, $reason);
                }
            }

            return $order->refresh();
        });
    }

    public function cancel(Order $order, ?Admin $actor = null, ?string $reason = null, ?Customer $customerActor = null): Order
    {
        return $this->changeStatus($order, OrderStatus::CANCELLED, $actor, $reason, null, $customerActor);
    }

    /**
     * Set staff final prices and/or a confirmed total (spec §5, ORDER_PRICED).
     *
     * @param  array<int, numeric|null>  $finalPrices  order_item_id => price (null clears)
     * @param  numeric|null  $confirmedTotal
     */
    public function price(Order $order, array $finalPrices = [], $confirmedTotal = null, ?Admin $actor = null): Order
    {
        return DB::transaction(function () use ($order, $finalPrices, $confirmedTotal, $actor) {
            foreach ($finalPrices as $itemId => $price) {
                if ($price !== null && (float) $price < 0) {
                    throw new DomainException('A price cannot be negative.');
                }
            }

            if ($confirmedTotal !== null && (float) $confirmedTotal < 0) {
                throw new DomainException('The confirmed total cannot be negative.');
            }

            foreach ($finalPrices as $itemId => $price) {
                $order->items()->whereKey($itemId)->update(['final_unit_price' => $price]);
            }

            if ($confirmedTotal !== null || $order->confirmed_total !== null) {
                $order->update(['confirmed_total' => $confirmedTotal]);
            }

            $this->audit->admin(AuditAction::ORDER_PRICED, $actor?->admin_id, $actor?->admin_name, 'order', $order->order_id, $order->order_code);

            return $order->refresh();
        });
    }

    public function note(Order $order, ?string $staffNotes, ?Admin $actor = null): Order
    {
        return DB::transaction(function () use ($order, $staffNotes, $actor) {
            $order->update(['staff_notes' => $staffNotes]);

            $this->audit->admin(AuditAction::ORDER_NOTED, $actor?->admin_id, $actor?->admin_name, 'order', $order->order_id, $order->order_code);

            return $order->refresh();
        });
    }

    /**
     * Aggregate duplicate varieties, enforce the line/sack limits, and sort by
     * inventory_id so concurrent multi-line orders always lock the same order
     * (deadlock avoidance).
     *
     * @param  list<array{inventory_id:int,sacks:int,staff_note?:?string}>  $lines
     * @return list<array{inventory_id:int,sacks:int,staff_note?:?string}>
     */
    private function normaliseLines(array $lines): array
    {
        if (count($lines) === 0) {
            throw new DomainException('An order needs at least one line.');
        }
        if (count($lines) > self::MAX_LINES) {
            throw CapacityException::lines(self::MAX_LINES);
        }

        $merged = [];

        foreach ($lines as $line) {
            $inventoryId = (int) ($line['inventory_id'] ?? 0);
            $sacks = (int) ($line['sacks'] ?? 0);

            if ($inventoryId <= 0) {
                throw new DomainException('Each line needs a valid variety.');
            }
            if ($sacks <= 0) {
                throw new DomainException('Each line needs at least one sack.');
            }

            if (isset($merged[$inventoryId])) {
                $merged[$inventoryId]['sacks'] += $sacks;
            } else {
                $merged[$inventoryId] = [
                    'inventory_id' => $inventoryId,
                    'sacks' => $sacks,
                    'staff_note' => $line['staff_note'] ?? null,
                ];
            }
        }

        foreach ($merged as $line) {
            if ($line['sacks'] > self::MAX_SACKS_PER_LINE) {
                throw new DomainException('A line may have at most '.self::MAX_SACKS_PER_LINE.' sacks.');
            }
        }

        ksort($merged);

        return array_values($merged);
    }

    private function guardUnverifiedDailyCap(Customer $customer): void
    {
        if ($customer->email_verified) {
            return;
        }

        $todayCount = Order::query()
            ->where('customer_id', $customer->customer_id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayCount >= self::UNVERIFIED_DAILY_CAP) {
            throw CapacityException::dailyOrders(self::UNVERIFIED_DAILY_CAP);
        }
    }
}
