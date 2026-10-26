<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerOrderHttpTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create([
            'admin_name' => 'Owner',
            'email' => 'owner@maviesbugasan.ph',
            'password_hash' => Hash::make('secret123'),
        ]);
    }

    private function customer(string $email, string $phone): Customer
    {
        return Customer::create([
            'first_name' => 'Ana', 'last_name' => 'Cruz',
            'phone' => $phone, 'email' => $email,
            'password_hash' => Hash::make('secret123'),
        ]);
    }

    public function test_customer_id_always_comes_from_the_session_not_the_body(): void
    {
        $admin = $this->admin();
        $item = app(InventoryService::class)->create([
            'name' => 'Dinorado 50kg', 'category' => 'Local',
            'unit_price' => 2100.00, 'wholesale_price' => 2000.00, 'initial_stock' => 100,
        ], $admin);

        $attacker = $this->customer('nobody@example.com', '09161112222');
        $victim = $this->customer('victim@example.com', '09173334444');
        $this->actingAs($attacker, 'customer');

        $this->postJson('/api/customer/orders', [
            'fulfillment_method' => 'Pickup',
            'customer_id' => $victim->customer_id,
            'lines' => [['inventory_id' => $item->inventory_id, 'sacks' => 3]],
        ])->assertStatus(201);

        $orderId = $this->getJson('/api/customer/orders')->json('data.0.order_id');
        $this->assertSame($attacker->customer_id, DB::table('orders')->where('order_id', $orderId)->value('customer_id'));
    }

    public function test_customer_order_flow_places_and_views_orders(): void
    {
        $admin = $this->admin();
        $item = app(InventoryService::class)->create([
            'name' => 'Sona 50kg', 'category' => 'Imported',
            'unit_price' => 2400.00, 'wholesale_price' => 2250.00, 'initial_stock' => 20,
        ], $admin);

        $this->actingAs($this->customer('ana@example.com', '09171234567'), 'customer');

        $this->postJson('/api/customer/orders', [
            'fulfillment_method' => 'Courier',
            'address_city' => 'Baybay',
            'lines' => [['inventory_id' => $item->inventory_id, 'sacks' => 2, 'staff_note' => 'Sukli sa sensei']],
        ])->assertStatus(201);

        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(1, 'data');

        $orderId = DB::table('orders')->first()->order_id;
        $this->getJson('/api/customer/orders/'.$orderId)->assertOk();

        // Another customer must not see it.
        $this->actingAs($this->customer('other@example.com', '09181234567'), 'customer');
        $this->getJson('/api/customer/orders/'.$orderId)->assertStatus(404);
    }
}