<?php

namespace Tests\Feature;

use App\Domain\FulfillmentMethod;
use App\Mail\CustomerPasswordResetMail;
use App\Mail\ResetPasswordMail;
use App\Mail\StaffPasswordResetMail;
use App\Mail\VerifyEmailMail;
use App\Models\Admin;
use App\Models\Customer;
use App\Services\AuthService;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountRecoveryTest extends TestCase
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

    private function customer(string $email = 'ana@example.com', string $phone = '09171234567'): Customer
    {
        return Customer::create([
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'phone' => $phone,
            'email' => $email,
            'password_hash' => Hash::make('secret123'),
            'email_verified' => true,
        ]);
    }

    public function test_registration_sends_the_verification_mailable(): void
    {
        Mail::fake();

        $this->postJson('/api/customer/register', [
            'first_name' => 'Nena',
            'last_name' => 'Reyes',
            'phone' => '09171234567',
            'email' => 'nena@example.com',
            'password' => 'secret123',
            'customer_type' => 'New',
            'terms_accepted' => true,
            'address_city' => 'Baybay',
        ])->assertStatus(201);

        Mail::assertSent(VerifyEmailMail::class, fn ($mail) => $mail->hasTo('nena@example.com'));
    }

    public function test_registration_rejects_a_duplicate_phone_with_a_clean_message(): void
    {
        Mail::fake();
        $this->customer();

        $response = $this->postJson('/api/customer/register', [
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'phone' => '09171234567',
            'email' => 'other@example.com',
            'password' => 'secret123',
            'customer_type' => 'New',
            'terms_accepted' => true,
            'address_city' => 'Baybay',
        ])->assertStatus(422);

        $this->assertStringContainsString('already registered', $response->json('message'));
        Mail::assertNothingSent();
    }

    public function test_registration_rejects_a_duplicate_email_with_a_clean_message(): void
    {
        Mail::fake();
        $this->customer();

        $this->postJson('/api/customer/register', [
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'phone' => '09181234567',
            'email' => 'ana@example.com',
            'password' => 'secret123',
            'customer_type' => 'New',
            'terms_accepted' => true,
            'address_city' => 'Baybay',
        ])->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_forgot_password_emails_a_reset_link_and_hides_unknown_emails(): void
    {
        Mail::fake();
        $this->customer();

        $this->postJson('/api/customer/password/forgot', ['email' => 'ana@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, a reset link is on its way.');

        Mail::assertSent(ResetPasswordMail::class, fn ($mail) => $mail->hasTo('ana@example.com'));

        // Unknown email gets the same response and no mail (no enumeration).
        $this->postJson('/api/customer/password/forgot', ['email' => 'nobody@example.com'])->assertOk();
        Mail::assertSent(ResetPasswordMail::class, 1);
    }

    public function test_reset_password_changes_it_and_the_token_is_single_use(): void
    {
        $customer = $this->customer();
        $token = app(AuthService::class)->requestPasswordReset('customer', $customer->customer_id, $customer->email);

        $this->postJson('/api/customer/password/reset', [
            'email' => 'ana@example.com',
            'token' => $token,
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
        ])->assertOk();

        $this->assertTrue(Hash::check('brandnew123', $customer->fresh()->password_hash));

        // Replaying the same token fails.
        $this->postJson('/api/customer/password/reset', [
            'email' => 'ana@example.com',
            'token' => $token,
            'password' => 'another123',
            'password_confirmation' => 'another123',
        ])->assertStatus(422);

        // A bad token fails.
        $this->postJson('/api/customer/password/reset', [
            'email' => 'ana@example.com',
            'token' => 'not-a-real-token',
            'password' => 'another123',
            'password_confirmation' => 'another123',
        ])->assertStatus(422);
    }

    public function test_customer_can_cancel_only_their_own_cancellable_order(): void
    {
        $admin = $this->owner();
        $item = app(InventoryService::class)->create([
            'name' => 'Boy Kanin 50kg', 'category' => 'Local',
            'unit_price' => 1180.00, 'wholesale_price' => 1180.00, 'initial_stock' => 10,
        ], $admin);

        $ana = $this->customer();
        $ben = $this->customer('ben@example.com', '09181234567');

        $order = app(OrderService::class)->create($ana, [['inventory_id' => $item->inventory_id, 'sacks' => 3]], FulfillmentMethod::PICKUP);
        $this->assertSame(7, $item->fresh()->stock);

        // Someone else's order is invisible.
        $this->actingAs($ben, 'customer')
            ->postJson("/api/customer/orders/{$order->order_id}/cancel")
            ->assertStatus(404);

        // The owner cancels; stock is restored exactly once.
        $this->actingAs($ana, 'customer')
            ->postJson("/api/customer/orders/{$order->order_id}/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', 'Cancelled');

        $this->assertSame(10, $item->fresh()->stock);

        // Cancelling again is refused (terminal), and stock is untouched.
        $this->actingAs($ana, 'customer')
            ->postJson("/api/customer/orders/{$order->order_id}/cancel")
            ->assertStatus(422);

        $this->assertSame(10, $item->fresh()->stock);
    }

    public function test_a_delivered_order_cannot_be_cancelled_by_the_customer(): void
    {
        $admin = $this->owner();
        $item = app(InventoryService::class)->create([
            'name' => 'Dinorado 25kg', 'category' => 'Local',
            'unit_price' => 900.00, 'wholesale_price' => 850.00, 'initial_stock' => 10,
        ], $admin);

        $ana = $this->customer();
        $order = app(OrderService::class)->create($ana, [['inventory_id' => $item->inventory_id, 'sacks' => 2]], FulfillmentMethod::PICKUP);

        app(OrderService::class)->changeStatus($order, 'Confirmed', $admin);
        app(OrderService::class)->changeStatus($order->refresh(), 'Out for Delivery', $admin);
        app(OrderService::class)->changeStatus($order->refresh(), 'Delivered', $admin);

        $this->actingAs($ana, 'customer')
            ->postJson("/api/customer/orders/{$order->order_id}/cancel")
            ->assertStatus(422);
    }

    public function test_admin_can_reset_a_customer_password(): void
    {
        Mail::fake();
        $this->actingAs($this->owner(), 'admin');
        $customer = $this->customer();

        $this->postJson("/api/admin/customers/{$customer->customer_id}/reset-password")->assertOk();

        Mail::assertSent(CustomerPasswordResetMail::class, function ($mail) use ($customer) {
            $this->assertSame($customer->email, $mail->to[0]['address']);

            // The emailed temporary password actually works.
            $this->assertTrue(Hash::check($mail->temporaryPassword, $customer->fresh()->password_hash));

            return true;
        });
    }

    public function test_admin_cannot_reset_a_walkin_customers_password(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $walkin = Customer::create([
            'first_name' => 'Walk', 'last_name' => 'In', 'phone' => '09190001111', 'is_walk_in' => true,
        ]);

        $this->postJson("/api/admin/customers/{$walkin->customer_id}/reset-password")->assertStatus(422);
    }

    public function test_admin_can_reset_a_staff_password(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $staff = Admin::create([
            'admin_name' => 'Cashier', 'email' => 'cashier@maviesbugasan.ph', 'password_hash' => Hash::make('cash1234'),
        ]);

        $this->actingAs($owner, 'admin')
            ->postJson("/api/admin/staff/{$staff->admin_id}/reset-password")
            ->assertOk();

        Mail::assertSent(StaffPasswordResetMail::class, fn ($mail) => $mail->hasTo('cashier@maviesbugasan.ph'));
    }

    public function test_admin_can_change_their_own_password(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'admin');

        $this->postJson('/api/admin/change-password', [
            'current_password' => 'wrongpass',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])->assertStatus(422);

        $this->postJson('/api/admin/change-password', [
            'current_password' => 'secret123',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass123', $owner->fresh()->password_hash));
    }

    public function test_admin_forgot_password_emails_a_reset_link_and_hides_unknown_emails(): void
    {
        Mail::fake();
        $owner = $this->owner();

        $this->postJson('/api/admin/password/forgot', ['email' => 'owner@maviesbugasan.ph'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, a reset link is on its way.');

        Mail::assertSent(ResetPasswordMail::class, function ($mail) use ($owner) {
            $this->assertSame('owner@maviesbugasan.ph', $mail->to[0]['address']);
            $this->assertStringContainsString('/admin/reset-password', $mail->resetUrl);
            return true;
        });

        // Unknown email gets the same response and no mail (no enumeration).
        $this->postJson('/api/admin/password/forgot', ['email' => 'nobody@example.com'])->assertOk();
        Mail::assertSent(ResetPasswordMail::class, 1);
    }

    public function test_admin_forgot_password_ignores_inactive_staff(): void
    {
        Mail::fake();
        Admin::create([
            'admin_name' => 'Gone',
            'email' => 'gone@maviesbugasan.ph',
            'password_hash' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/admin/password/forgot', ['email' => 'gone@maviesbugasan.ph'])->assertOk();
        Mail::assertNothingSent();
    }

    public function test_admin_reset_password_changes_it_and_the_token_is_single_use(): void
    {
        $owner = $this->owner();
        $token = app(AuthService::class)->requestPasswordReset('admin', $owner->admin_id, $owner->email);

        $this->postJson('/api/admin/password/reset', [
            'email' => 'owner@maviesbugasan.ph',
            'token' => $token,
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
        ])->assertOk();

        $this->assertTrue(Hash::check('brandnew123', $owner->fresh()->password_hash));

        // Replaying the same token fails.
        $this->postJson('/api/admin/password/reset', [
            'email' => 'owner@maviesbugasan.ph',
            'token' => $token,
            'password' => 'another123',
            'password_confirmation' => 'another123',
        ])->assertStatus(422);

        // A bad token fails.
        $this->postJson('/api/admin/password/reset', [
            'email' => 'owner@maviesbugasan.ph',
            'token' => 'not-a-real-token',
            'password' => 'another123',
            'password_confirmation' => 'another123',
        ])->assertStatus(422);
    }
}
