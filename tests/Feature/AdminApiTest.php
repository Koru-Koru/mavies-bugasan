<?php

namespace Tests\Feature;

use App\Domain\FulfillmentMethod;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): Admin
    {
        return Admin::create([
            'admin_name' => 'Owner',
            'email' => 'owner@maviesbugasan.ph',
            'password_hash' => Hash::make('secret123'),
        ]);
    }

    public function test_dashboard_returns_stats(): void
    {
        $this->actingAs($this->owner(), 'admin')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['stats', 'low_stock', 'recent_orders', 'sales_series', 'best_seller'])
            ->assertJsonPath('best_seller', null);

        $this->getJson('/api/admin/dashboard')->assertJsonStructure(['stats' => ['revenue_month', 'open_orders']]);
    }

    public function test_inventory_lifecycle(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $item = $this->postJson('/api/admin/inventory', [
            'name' => 'Sinandomeng 50kg',
            'category' => 'Local',
            'unit_price' => 0.00,
            'wholesale_price' => 2300.00,
            'initial_stock' => 50,
        ])->assertStatus(201)->json('item');

        $this->getJson('/api/admin/inventory')->assertOk()->assertJsonPath('data.0.name', 'Sinandomeng 50kg');

        $this->patchJson("/api/admin/inventory/{$item['inventory_id']}/stock", ['stock' => 30, 'reason' => 'Physical count'])
            ->assertOk()
            ->assertJsonPath('item.stock', 30);

        $this->getJson("/api/admin/inventory/{$item['inventory_id']}/movements")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->postJson("/api/admin/inventory/{$item['inventory_id']}/archive")->assertOk();
        $this->getJson('/api/admin/inventory')->assertJsonCount(0, 'data');
        $this->postJson("/api/admin/inventory/{$item['inventory_id']}/restore")->assertOk();
    }

    public function test_walkin_customer_create_and_blacklist(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $customer = $this->postJson('/api/admin/customers', [
            'first_name' => 'Pedro',
            'last_name' => 'Santos',
            'phone' => '09181234567',
            'address_city' => 'Baybay',
        ])->assertStatus(201)->json('customer');

        $this->getJson('/api/admin/customers?q=Pedro')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/admin/customers/{$customer['customer_id']}/blacklist", ['reason' => 'No show'])
            ->assertOk()
            ->assertJsonPath('customer.blacklisted', true);

        $this->postJson("/api/admin/customers/{$customer['customer_id']}/whitelist")->assertOk();
    }

    public function test_admin_can_change_a_customers_type(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $customer = Customer::create([
            'first_name' => 'Liza', 'last_name' => 'Ramos',
            'phone' => '09201234567',
            'password_hash' => Hash::make('secret123'),
        ]);

        $this->assertSame('New', $customer->fresh()->customer_type);

        $this->patchJson("/api/admin/customers/{$customer->customer_id}", ['customer_type' => 'Returning'])
            ->assertOk()
            ->assertJsonPath('customer.customer_type', 'Returning');

        $this->getJson("/api/admin/customers/{$customer->customer_id}")
            ->assertOk()
            ->assertJsonPath('customer.customer_type', 'Returning');

        $this->patchJson("/api/admin/customers/{$customer->customer_id}", ['customer_type' => 'Nonsense'])
            ->assertStatus(422);
    }

    public function test_payment_record_and_void_over_http(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');

        $item = app(InventoryService::class)->create([
            'name' => 'C-1 Sona',
            'category' => 'Imported',
            'unit_price' => 2400.00,
            'wholesale_price' => 2200.00,
            'initial_stock' => 40,
        ], $admin);

        $customer = Customer::create([
            'first_name' => 'Liza', 'last_name' => 'Ramos',
            'phone' => '09191234567',
            'password_hash' => Hash::make('secret123'),
        ]);

        $order = app(OrderService::class)->create(
            $customer,
            [['inventory_id' => $item->inventory_id, 'sacks' => 5]],
            FulfillmentMethod::PICKUP,
        );

        $this->postJson('/api/admin/payments', [
            'amount' => 12000.00,
            'method' => 'Cash',
            'allocations' => [['order_id' => $order->order_id, 'amount' => 12000.00]],
        ])->assertStatus(201);

        $this->getJson('/api/admin/payments?order_id='.$order->order_id)->assertOk()->assertJsonCount(1, 'data');

        $paymentId = Payment::query()->first()->payment_id;
        $this->postJson("/api/admin/payments/{$paymentId}/void", ['reason' => 'Wrong amount'])
            ->assertOk();

        $this->getJson('/api/admin/orders/'.$order->order_id)->assertOk()->assertJsonPath('order.balance', 12000);
    }

    public function test_payment_suggestion_endpoint_is_manual_starting_point(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');

        $item = app(InventoryService::class)->create([
            'name' => 'Dinorado 25kg', 'category' => 'Local',
            'unit_price' => 900.00, 'wholesale_price' => 850.00, 'initial_stock' => 30,
        ], $admin);

        $customer = Customer::create([
            'first_name' => 'Nena', 'last_name' => 'Diaz', 'phone' => '09151234567',
            'password_hash' => Hash::make('secret123'),
        ]);

        $order = app(OrderService::class)->create(
            $customer,
            [['inventory_id' => $item->inventory_id, 'sacks' => 2]],
            FulfillmentMethod::PICKUP,
        );

        $this->getJson('/api/admin/payments/suggest?customer_id='.$customer->customer_id.'&amount=1800')
            ->assertOk()
            ->assertJsonPath('allocations.0.order_id', $order->order_id);
    }

    public function test_settings_update(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $this->patchJson('/api/admin/settings', ['settings' => ['shop_motto' => 'Fresh rice daily']])
            ->assertOk()
            ->assertJsonPath('settings.shop_motto', 'Fresh rice daily');
    }

    public function test_staff_create_and_index(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $this->postJson('/api/admin/staff', [
            'admin_name' => 'Cashier One',
            'email' => 'cashier1@maviesbugasan.ph',
            'password' => 'cash1234',
        ])->assertStatus(201);

        $this->getJson('/api/admin/staff')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_activity_log_is_readable(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $this->getJson('/api/admin/activity-log')->assertOk();
    }

    public function test_damage_logging_over_http(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');

        $item = app(InventoryService::class)->create([
            'name' => 'Sinandomeng 25kg', 'category' => 'Local',
            'unit_price' => 1200.00, 'wholesale_price' => 1150.00, 'initial_stock' => 20,
        ], $admin);

        $this->postJson('/api/admin/damages', [
            'inventory_id' => $item->inventory_id,
            'sacks' => 2,
            'damage_type' => 'Pest Infestation',
            'date_logged' => now()->toDateString(),
        ])->assertStatus(201);

        $this->getJson('/api/admin/damages')->assertOk()->assertJsonCount(1, 'data');

        $this->getJson('/api/admin/inventory/'.$item->inventory_id.'/movements')->assertJsonCount(2, 'data');
    }
}