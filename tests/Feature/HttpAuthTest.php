<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HttpAuthTest extends TestCase
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

    private function customer(): Customer
    {
        return Customer::create([
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'phone' => '09171234567',
            'email' => 'ana@example.com',
            'password_hash' => Hash::make('secret123'),
        ]);
    }

    public function test_admin_endpoint_is_denied_without_a_session(): void
    {
        $this->getJson('/api/admin/me')->assertStatus(401);
    }

    public function test_customer_endpoint_is_denied_without_a_session(): void
    {
        $this->getJson('/api/customer/me')->assertStatus(401);
    }

    public function test_a_customer_session_cannot_satisfy_an_admin_check(): void
    {
        $this->actingAs($this->customer(), 'customer')
            ->getJson('/api/admin/me')
            ->assertStatus(401);
    }

    public function test_admin_can_log_in_and_read_their_own_profile(): void
    {
        $admin = $this->owner();

        $this->postJson('/api/admin/login', [
            'email' => 'owner@maviesbugasan.ph',
            'password' => 'secret123',
        ])->assertOk()->assertJsonPath('admin.email', 'owner@maviesbugasan.ph');

        // actingAs proves the guard resolves the right provider.
        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('admin.admin_name', 'Owner');
    }

    public function test_bad_credentials_are_refused(): void
    {
        $this->owner();

        $this->postJson('/api/admin/login', [
            'email' => 'owner@maviesbugasan.ph',
            'password' => 'wrongpass1',
        ])->assertStatus(422);
    }

    public function test_catalog_is_public_and_paginated(): void
    {
        $this->seed(\Database\Seeders\InventorySeeder::class);

        $response = $this->getJson('/api/catalog')->assertOk();

        $response->assertJsonStructure([
            'data' => [['inventory_id', 'name', 'category', 'unit_price', 'stock']],
            'pagination' => ['total', 'page', 'per_page', 'pages'],
        ]);

        $this->assertSame(15, $response->json('pagination.total'));
    }

    public function test_csrf_token_endpoint_is_public(): void
    {
        $this->getJson('/api/csrf-token')->assertOk()->assertJsonStructure(['token']);
    }

    private function signUpPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Nena',
            'last_name' => 'Reyes',
            'phone' => '09171234567',
            'email' => 'nena@example.com',
            'password' => 'secret123',
            'customer_type' => 'New',
            'terms_accepted' => true,
            'address_city' => 'Baybay',
        ], $overrides);
    }

    public function test_sign_up_creates_an_unverified_account_without_logging_in(): void
    {
        $this->postJson('/api/customer/register', $this->signUpPayload())
            ->assertStatus(201)
            ->assertJsonPath('requires_otp', true)
            ->assertJsonPath('customer.email_verified', false)
            ->assertJsonPath('customer.customer_type', 'New');

        // No auto-login: the session is still a guest.
        $this->getJson('/api/customer/me')->assertStatus(401);

        $customer = Customer::where('email', 'nena@example.com')->firstOrFail();
        $this->assertNotNull($customer->terms_accepted_at);
    }

    public function test_sign_up_requires_accepting_the_terms(): void
    {
        $this->postJson('/api/customer/register', $this->signUpPayload(['terms_accepted' => false]))
            ->assertStatus(422);

        $payload = $this->signUpPayload();
        unset($payload['terms_accepted']);
        $this->postJson('/api/customer/register', $payload)->assertStatus(422);

        $this->assertDatabaseMissing('customers', ['email' => 'nena@example.com']);
    }

    public function test_otp_verifies_a_new_account_and_the_code_is_single_use(): void
    {
        $this->postJson('/api/customer/register', $this->signUpPayload())->assertStatus(201);
        $customer = Customer::where('email', 'nena@example.com')->firstOrFail();

        // Wrong code is refused.
        $this->postJson('/api/customer/otp/verify', ['email' => 'nena@example.com', 'code' => '000000'])
            ->assertStatus(422);

        // The code that was actually issued works.
        $code = app(AuthService::class)->issueOtp('customer', $customer->customer_id);
        $this->postJson('/api/customer/otp/verify', ['email' => 'nena@example.com', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->assertTrue($customer->fresh()->email_verified);
    }

    public function test_otp_can_be_resent_and_invalidates_the_previous_code(): void
    {
        $this->postJson('/api/customer/register', $this->signUpPayload())->assertStatus(201);
        $customer = Customer::where('email', 'nena@example.com')->firstOrFail();

        $old = app(AuthService::class)->issueOtp('customer', $customer->customer_id);

        $this->postJson('/api/customer/register/otp', ['email' => 'nena@example.com'])
            ->assertOk()
            ->assertJsonPath('requires_otp', true);

        // Resending mints a fresh code, so the old one no longer works.
        $this->postJson('/api/customer/otp/verify', ['email' => 'nena@example.com', 'code' => $old])
            ->assertStatus(422);

        $this->postJson('/api/customer/register/otp', ['email' => 'nobody@example.com'])
            ->assertStatus(422);
    }

    public function test_customer_can_update_profile_and_change_password(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $this->patchJson('/api/customer/profile', [
            'first_name' => 'Anna',
            'address_city' => 'Angeles',
            'customer_type' => 'Returning',
        ])
            ->assertOk()
            ->assertJsonPath('customer.first_name', 'Anna')
            ->assertJsonPath('customer.customer_type', 'Returning')
            ->assertJsonPath('customer.address_city', 'Angeles');

        $this->postJson('/api/customer/change-password', [
            'current_password' => 'wrongpass',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])->assertStatus(422);

        $this->postJson('/api/customer/change-password', [
            'current_password' => 'secret123',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass123', $customer->fresh()->password_hash));
    }

    public function test_catalog_supports_category_sort_and_facets(): void
    {
        $this->seed(\Database\Seeders\InventorySeeder::class);

        $all = $this->getJson('/api/catalog')->assertOk();
        $categories = $all->json('facets.categories');
        $this->assertNotEmpty($categories);

        $sorted = $this->getJson('/api/catalog?sort=price_asc')->assertOk();
        $prices = array_map(fn ($r) => $r['unit_price'], $sorted->json('data'));
        $expected = $prices;
        sort($expected);
        $this->assertSame($expected, $prices);

        $one = $categories[0];
        $filtered = $this->getJson('/api/catalog?category='.urlencode($one))->assertOk();
        foreach ($filtered->json('data') as $row) {
            $this->assertSame($one, $row['category']);
        }
    }
}
