<?php

namespace Tests\Feature;

use App\Domain\DamageType;
use App\Domain\FulfillmentMethod;
use App\Domain\OrderStatus;
use App\Exceptions\Domain\AllocationMismatchException;
use App\Exceptions\Domain\IllegalTransitionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\OverpaymentException;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderMoneyAndStockTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;
    private InventoryService $inventory;
    private PaymentService $payments;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\InventorySeeder::class);

        $this->orders = app(OrderService::class);
        $this->inventory = app(InventoryService::class);
        $this->payments = app(PaymentService::class);

        $this->admin = Admin::create([
            'admin_name' => 'Owner',
            'email' => 'owner@maviesbugasan.ph',
            'password_hash' => Hash::make('secret123'),
        ]);
    }

    private function variety(string $name): InventoryItem
    {
        return InventoryItem::where('name', $name)->firstOrFail();
    }

    private function walkIn(string $phone = '09171234567'): Customer
    {
        return Customer::create([
            'first_name' => 'Walk',
            'last_name' => 'In',
            'phone' => $phone,
            'address_city' => 'Marikina City',
            'is_walk_in' => true,
        ]);
    }

    public function test_placing_an_order_reserves_stock_and_derives_the_code_from_the_id(): void
    {
        $boy = $this->variety('Boy Kanin'); // ₱1180.00
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening stock', $this->admin);

        $order = $this->orders->create(
            $this->walkIn(),
            [['inventory_id' => $boy->inventory_id, 'sacks' => 10]],
            FulfillmentMethod::PICKUP,
            [],
            $this->admin,
        );

        $this->assertSame('ORD-'.str_pad((string) $order->order_id, 6, '0', STR_PAD_LEFT), $order->order_code);
        $this->assertSame(90, $this->inventory->available($boy->inventory_id));
        $this->assertSame(90, (int) DB::table('stock_movements')->where('inventory_id', $boy->inventory_id)->sum('qty'));

        $view = DB::table('v_orders')->where('order_id', $order->order_id)->first();
        $this->assertEquals(11800.00, (float) $view->amount_due);
        $this->assertEquals(0.00, (float) $view->amount_paid);
        $this->assertEquals(11800.00, (float) $view->balance);
        $this->assertSame('Unpaid', $view->payment_status);
    }

    public function test_illegal_transition_is_refused(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening', $this->admin);
        $order = $this->orders->create($this->walkIn(), [['inventory_id' => $boy->inventory_id, 'sacks' => 1]], FulfillmentMethod::PICKUP, [], $this->admin);

        $this->expectException(IllegalTransitionException::class);
        $this->orders->changeStatus($order, OrderStatus::DELIVERED, $this->admin);
    }

    public function test_cancelling_restores_stock_exactly_once(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening', $this->admin);
        $order = $this->orders->create($this->walkIn(), [['inventory_id' => $boy->inventory_id, 'sacks' => 10]], FulfillmentMethod::PICKUP, [], $this->admin);

        $this->orders->changeStatus($order, OrderStatus::CONFIRMED, $this->admin);
        $this->orders->changeStatus($order, OrderStatus::CANCELLED, $this->admin, 'Customer changed mind');

        $this->assertSame(100, $this->inventory->available($boy->inventory_id));
        $this->assertSame(100, (int) DB::table('stock_movements')->where('inventory_id', $boy->inventory_id)->sum('qty'));

        // A second cancel must be refused before it can restore again.
        $this->expectException(IllegalTransitionException::class);
        $this->orders->changeStatus($order->refresh(), OrderStatus::CANCELLED, $this->admin, 'again');
    }

    public function test_insufficient_stock_is_refused_and_stock_is_unchanged(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 5, 'Opening', $this->admin);

        try {
            $this->orders->create($this->walkIn(), [['inventory_id' => $boy->inventory_id, 'sacks' => 6]], FulfillmentMethod::PICKUP, [], $this->admin);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            $this->assertSame(5, $this->inventory->available($boy->inventory_id));
            $this->assertSame(0, (int) DB::table('orders')->count());
        }
    }

    public function test_money_is_recorded_not_inferred(): void
    {
        $boy = $this->variety('Boy Kanin'); // ₱1180 x 10 = ₱11,800 (the spec example uses ₱20,000 sized orders)
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening', $this->admin);
        $order = $this->orders->create($this->walkIn(), [['inventory_id' => $boy->inventory_id, 'sacks' => 10]], FulfillmentMethod::PICKUP, [], $this->admin);

        $this->payments->record(
            [['order_id' => $order->order_id, 'amount' => 5000.00]],
            5000.00,
            \App\Domain\PaymentMethod::CASH,
            $this->admin->admin_id,
            actor: $this->admin,
        );

        $view = DB::table('v_orders')->where('order_id', $order->order_id)->first();
        $this->assertEquals(5000.00, (float) $view->amount_paid);
        $this->assertEquals(6800.00, (float) $view->balance);
        $this->assertSame('Partial', $view->payment_status);
    }

    public function test_allocations_must_sum_to_the_payment(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening', $this->admin);
        $order = $this->orders->create($this->walkIn(), [['inventory_id' => $boy->inventory_id, 'sacks' => 1]], FulfillmentMethod::PICKUP, [], $this->admin);

        $this->expectException(AllocationMismatchException::class);
        $this->payments->record(
            [['order_id' => $order->order_id, 'amount' => 999.00]],
            1180.00,
            \App\Domain\PaymentMethod::CASH,
            $this->admin->admin_id,
            actor: $this->admin,
        );
    }

    public function test_overpayment_is_refused(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening', $this->admin);
        $order = $this->orders->create($this->walkIn(), [['inventory_id' => $boy->inventory_id, 'sacks' => 1]], FulfillmentMethod::PICKUP, [], $this->admin);

        $this->expectException(OverpaymentException::class);
        $this->payments->record(
            [['order_id' => $order->order_id, 'amount' => 1181.00]],
            1181.00,
            \App\Domain\PaymentMethod::CASH,
            $this->admin->admin_id,
            actor: $this->admin,
        );
    }

    public function test_one_payment_can_span_orders_and_voiding_restores_the_balance(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening', $this->admin);

        $a = $this->orders->create($this->walkIn('09170000001'), [['inventory_id' => $boy->inventory_id, 'sacks' => 1]], FulfillmentMethod::PICKUP, [], $this->admin);
        $b = $this->orders->create($this->walkIn('09170000002'), [['inventory_id' => $boy->inventory_id, 'sacks' => 2]], FulfillmentMethod::PICKUP, [], $this->admin);

        $payment = $this->payments->record(
            [
                ['order_id' => $a->order_id, 'amount' => 1180.00],
                ['order_id' => $b->order_id, 'amount' => 1000.00],
            ],
            2180.00,
            \App\Domain\PaymentMethod::GCASH,
            $this->admin->admin_id,
            actor: $this->admin,
        );

        $this->assertSame('Paid', DB::table('v_orders')->where('order_id', $a->order_id)->value('payment_status'));
        $this->assertEquals(1360.00, (float) DB::table('v_orders')->where('order_id', $b->order_id)->value('balance'));

        $this->payments->void($payment, 'Wrong customer', $this->admin);

        $this->assertEquals(2360.00, (float) DB::table('v_orders')->where('order_id', $b->order_id)->value('balance'));
        $this->assertSame('Unpaid', DB::table('v_orders')->where('order_id', $a->order_id)->value('payment_status'));
    }

    public function test_blacklisted_customer_cannot_order(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening', $this->admin);
        $customer = $this->walkIn();
        $customer->update(['blacklisted' => true, 'blacklist_reason' => 'Unpaid', 'blacklisted_at' => now()]);

        $this->expectException(\App\Exceptions\Domain\BlacklistedCustomerException::class);
        $this->orders->create($customer->refresh(), [['inventory_id' => $boy->inventory_id, 'sacks' => 1]], FulfillmentMethod::PICKUP, [], $this->admin);
    }

    public function test_delivery_requires_an_address(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 100, 'Opening', $this->admin);

        $this->expectException(\App\Exceptions\Domain\DomainException::class);
        $this->orders->create($this->walkIn(), [['inventory_id' => $boy->inventory_id, 'sacks' => 1]], FulfillmentMethod::COURIER, [], $this->admin);
    }

    public function test_damage_deducts_stock_and_codes_from_id(): void
    {
        $boy = $this->variety('Boy Kanin');
        $this->inventory->adjustStock($boy->inventory_id, 50, 'Opening', $this->admin);

        $damage = $this->inventory->logDamage($boy->inventory_id, 3, DamageType::WATER, now()->toDateString(), 'Busted gutter', $this->admin);

        $this->assertSame('DMG-'.str_pad((string) $damage->damage_log_id, 6, '0', STR_PAD_LEFT), $damage->damage_code);
        $this->assertSame(47, $this->inventory->available($boy->inventory_id));
        $this->assertSame(47, (int) DB::table('stock_movements')->where('inventory_id', $boy->inventory_id)->sum('qty'));
    }
}
