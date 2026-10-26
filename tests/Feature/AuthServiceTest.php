<?php

namespace Tests\Feature;

use App\Exceptions\Domain\AccountLockedException;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Models\Admin;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = app(AuthService::class);
    }

    public function test_owner_can_only_be_created_once(): void
    {
        $this->auth->createOwner('Owner', 'owner@maviesbugasan.ph', 'secret123');

        $this->expectException(DomainException::class);
        $this->auth->createOwner('Second', 'other@maviesbugasan.ph', 'secret123');
    }

    public function test_password_rules_are_enforced(): void
    {
        $this->expectException(DomainException::class);
        $this->auth->createOwner('Owner', 'owner@maviesbugasan.ph', 'short');
    }

    public function test_admin_lockout_after_five_failures_is_server_side(): void
    {
        $this->auth->createOwner('Owner', 'owner@maviesbugasan.ph', 'secret123');

        for ($i = 0; $i < 4; $i++) {
            try {
                $this->auth->adminLogin('owner@maviesbugasan.ph', 'wrongpass1');
            } catch (InvalidCredentialsException) {
                // expected
            }
        }

        // Fifth failure locks the account.
        try {
            $this->auth->adminLogin('owner@maviesbugasan.ph', 'wrongpass1');
            $this->fail('Expected lockout on the fifth failure.');
        } catch (AccountLockedException) {
            // expected
        }

        // Correct password is still refused while locked.
        $this->expectException(AccountLockedException::class);
        $this->auth->adminLogin('owner@maviesbugasan.ph', 'secret123');
    }

    public function test_successful_login_clears_the_failure_counter(): void
    {
        $admin = $this->auth->createOwner('Owner', 'owner@maviesbugasan.ph', 'secret123');

        try {
            $this->auth->adminLogin('owner@maviesbugasan.ph', 'wrongpass1');
        } catch (InvalidCredentialsException) {
            // expected
        }

        $this->auth->adminLogin('owner@maviesbugasan.ph', 'secret123');

        $this->assertSame(0, $admin->refresh()->failed_login_count);
        $this->assertNull($admin->locked_until);
    }

    public function test_phone_normalisation_accepts_common_philippine_prefixes(): void
    {
        $this->assertSame('09171234567', $this->auth->normalisePhone('09171234567'));
        $this->assertSame('09171234567', $this->auth->normalisePhone('9171234567'));
        $this->assertSame('09171234567', $this->auth->normalisePhone('+63 917 123 4567'));
    }

    public function test_password_reset_token_is_hashed_and_single_use(): void
    {
        $customer = \App\Models\Customer::create([
            'first_name' => 'Ana', 'last_name' => 'Cruz', 'phone' => '09171234567',
            'email' => 'ana@example.com', 'password_hash' => \Illuminate\Support\Facades\Hash::make('secret123'),
        ]);

        $token = $this->auth->requestPasswordReset('customer', $customer->customer_id, $customer->email);

        // The raw token is never stored.
        $stored = \Illuminate\Support\Facades\DB::table('auth_tokens')->get();
        $this->assertCount(1, $stored);
        $this->assertSame(hash('sha256', $token), $stored->first()->token_hash);

        $this->auth->completePasswordReset('customer', $customer->customer_id, $token, 'newpass123');

        // A used token cannot be replayed.
        $this->expectException(DomainException::class);
        $this->auth->completePasswordReset('customer', $customer->customer_id, $token, 'again123');
    }
}
