<?php

namespace App\Services;

use App\Domain\ActorType;
use App\Domain\AuditAction;
use App\Exceptions\Domain\AccountLockedException;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Models\Admin;
use App\Models\AuthToken;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Everything auth. Passwords are always bcrypt (invariant 4); login lockout
 * for admins is held server-side on the admins row so clearing the browser
 * cannot undo it (BUSINESS_LOGIC §15). Customer throttling is handled at the
 * HTTP layer by Laravel's rate limiter — no lockout columns are added to
 * customers (owner decision).
 *
 * Verification/reset tokens are stored only as SHA-256 hashes, so a database
 * dump contains no usable links (BUSINESS_LOGIC §18).
 */
class AuthService
{
    public const MAX_FAILED_LOGINS = 5;
    public const LOCKOUT_MINUTES = 15;
    public const TOKEN_TTL_MINUTES = 60;
    public const OTP_TTL_MINUTES = 10;

    public function __construct(private AuditService $audit) {}

    // -----------------------------------------------------------------
    // Owner / staff
    // -----------------------------------------------------------------

    public function ownerExists(): bool
    {
        return Admin::query()->exists();
    }

    public function createOwner(string $name, string $email, string $password): Admin
    {
        if ($this->ownerExists()) {
            throw new DomainException('An owner already exists.');
        }

        $this->assertPassword($password);

        return DB::transaction(function () use ($name, $email, $password) {
            $admin = Admin::create([
                'admin_name' => $name,
                'email' => strtolower(trim($email)),
                'password_hash' => Hash::make($password),
                'is_active' => true,
            ]);

            $this->audit->system(AuditAction::SETUP_OWNER_CREATED, 'admin', $admin->admin_id, $admin->email);

            return $admin;
        });
    }

    public function createStaff(string $name, string $email, string $password, Admin $actor): Admin
    {
        $this->assertPassword($password);

        return DB::transaction(function () use ($name, $email, $password, $actor) {
            $admin = Admin::create([
                'admin_name' => $name,
                'email' => strtolower(trim($email)),
                'password_hash' => Hash::make($password),
                'is_active' => true,
            ]);

            $this->audit->admin(AuditAction::STAFF_CREATED, $actor->admin_id, $actor->admin_name, 'admin', $admin->admin_id, $admin->email);

            return $admin;
        });
    }

    /** @param array{admin_name?:string,email?:string,is_active?:bool} $data */
    public function updateStaff(Admin $staff, array $data, Admin $actor): Admin
    {
        return DB::transaction(function () use ($staff, $data, $actor) {
            $staff->fill(array_filter([
                'admin_name' => $data['admin_name'] ?? null,
                'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($v) => $v !== null));
            $staff->save();

            $this->audit->admin(AuditAction::STAFF_UPDATED, $actor->admin_id, $actor->admin_name, 'admin', $staff->admin_id, $staff->email);

            return $staff;
        });
    }

    public function adminLogin(string $email, string $password, ?string $ip = null): Admin
    {
        $admin = Admin::where('email', strtolower(trim($email)))->first();

        if (! $admin || ! $admin->is_active) {
            $this->audit->log(AuditAction::ADMIN_LOGIN_FAILED, ActorType::ADMIN, null, $email, ip: $ip);
            throw new InvalidCredentialsException();
        }

        if ($admin->locked_until && $admin->locked_until->isFuture()) {
            $this->audit->log(AuditAction::ADMIN_LOGIN_FAILED, ActorType::ADMIN, $admin->admin_id, $admin->admin_name, ip: $ip);
            throw AccountLockedException::until($admin->locked_until->format('H:i'));
        }

        if (! Hash::check($password, $admin->password_hash)) {
            $admin->failed_login_count++;
            if ($admin->failed_login_count >= self::MAX_FAILED_LOGINS) {
                $admin->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
                $admin->failed_login_count = 0;
                $admin->save();
                $this->audit->admin(AuditAction::ADMIN_LOCKED, $admin->admin_id, $admin->admin_name, 'admin', $admin->admin_id, 'Locked for '.self::LOCKOUT_MINUTES.' minutes');
                throw AccountLockedException::until($admin->locked_until->format('H:i'));
            }
            $admin->save();
            $this->audit->log(AuditAction::ADMIN_LOGIN_FAILED, ActorType::ADMIN, $admin->admin_id, $admin->admin_name, ip: $ip);
            throw new InvalidCredentialsException();
        }

        $admin->update([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login' => now(),
            'last_login_ip' => $ip ? @inet_pton($ip) : null,
        ]);

        $this->audit->admin(AuditAction::ADMIN_LOGIN, $admin->admin_id, $admin->admin_name, 'admin', $admin->admin_id);

        return $admin;
    }

    public function adminLogout(Admin $admin): void
    {
        $this->audit->admin(AuditAction::ADMIN_LOGOUT, $admin->admin_id, $admin->admin_name, 'admin', $admin->admin_id);
    }

    // -----------------------------------------------------------------
    // Customers
    // -----------------------------------------------------------------

    public function register(
        string $firstName,
        string $lastName,
        string $phone,
        string $email,
        string $password,
        array $address = [],
        string $customerType = 'New',
        bool $termsAccepted = false,
    ): Customer {
        if (! $termsAccepted) {
            throw new DomainException('You must accept the terms of service to sign up.');
        }

        $this->assertPassword($password);

        return DB::transaction(function () use ($firstName, $lastName, $phone, $email, $password, $address, $customerType, $termsAccepted) {
            $phone = $this->normalisePhone($phone);
            $email = strtolower(trim($email));

            if (Customer::where('phone', $phone)->exists()) {
                throw new DomainException('That phone number is already registered. Try signing in instead.');
            }
            if (Customer::where('email', $email)->exists()) {
                throw new DomainException('That email is already registered. Try signing in instead.');
            }

            $customer = Customer::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'email' => $email,
                'password_hash' => Hash::make($password),
                'address_bldg' => $address['bldg'] ?? null,
                'address_street' => $address['street'] ?? null,
                'address_brgy' => $address['brgy'] ?? null,
                'address_city' => $address['city'] ?? '',
                'is_walk_in' => false,
                'customer_type' => $customerType,
                'terms_accepted_at' => now(),
            ]);

            $this->audit->customer(AuditAction::CUSTOMER_REGISTERED, $customer->customer_id, $customer->fullName(), 'customer', $customer->customer_id);

            return $customer;
        });
    }

    /** @param array{first_name:string,last_name:string,phone:string,address_city?:string} $data */
    public function createWalkIn(array $data, Admin $actor): Customer
    {
        return DB::transaction(function () use ($data, $actor) {
            $phone = $this->normalisePhone($data['phone']);

            if (Customer::where('phone', $phone)->exists()) {
                throw new DomainException('That phone number already belongs to another customer.');
            }

            $customer = Customer::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $phone,
                'address_city' => $data['address_city'] ?? '',
                'is_walk_in' => true,
            ]);

            $this->audit->admin(AuditAction::CUSTOMER_CREATED, $actor->admin_id, $actor->admin_name, 'customer', $customer->customer_id, $customer->fullName());

            return $customer;
        });
    }

    public function customerLogin(string $identifier, string $password, ?string $ip = null): Customer
    {
        $identifier = trim($identifier);
        $digits = preg_replace('/\D+/', '', $identifier);

        $customer = Customer::query()
            ->where('email', strtolower($identifier))
            ->orWhere('phone', $digits)
            ->first();

        if (! $customer || ! $customer->canLogIn() || ! Hash::check($password, (string) $customer->password_hash)) {
            $this->audit->log(AuditAction::CUSTOMER_LOGIN_FAILED, ActorType::CUSTOMER, $customer?->customer_id, $identifier, ip: $ip);
            throw new InvalidCredentialsException();
        }

        $customer->update(['last_login' => now()]);
        $this->audit->customer(AuditAction::CUSTOMER_LOGIN, $customer->customer_id, $customer->fullName(), 'customer', $customer->customer_id);

        return $customer;
    }

    public function customerLogout(Customer $customer): void
    {
        $this->audit->customer(AuditAction::CUSTOMER_LOGOUT, $customer->customer_id, $customer->fullName(), 'customer', $customer->customer_id);
    }

    public function findCustomerByEmail(string $email): ?Customer
    {
        return Customer::where('email', strtolower(trim($email)))->first();
    }

    public function findAdminByEmail(string $email): ?Admin
    {
        return Admin::where('email', strtolower(trim($email)))->first();
    }

    /**
     * Email-verification OTP. Only the SHA-256 hash is stored (invariant 4),
     * the code itself is returned to the caller so it can be echoed/logged in
     * local environments and emailed everywhere else. Any previous unused OTP
     * for the same subject + purpose is invalidated so only the newest code
     * works.
     */
    public function issueOtp(string $subject, int $subjectId, ?int $ttlMinutes = null): string
    {
        AuthToken::query()
            ->where('subject', $subject)
            ->where('subject_id', $subjectId)
            ->where('purpose', 'verify_email')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $otp = (string) random_int(100000, 999999);

        AuthToken::create([
            'subject' => $subject,
            'subject_id' => $subjectId,
            'purpose' => 'verify_email',
            'token_hash' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes($ttlMinutes ?? self::OTP_TTL_MINUTES),
        ]);

        return $otp;
    }

    /** @param array<string,mixed> $data */
    public function updateCustomer(Customer $customer, array $data, ?Admin $actor = null): Customer
    {
        return DB::transaction(function () use ($customer, $data, $actor) {
            if (isset($data['phone'])) {
                $data['phone'] = $this->normalisePhone($data['phone']);
            }
            if (isset($data['email'])) {
                $data['email'] = strtolower(trim($data['email']));
            }

            $customer->fill($data)->save();

            if ($actor) {
                $this->audit->admin(AuditAction::CUSTOMER_UPDATED, $actor->admin_id, $actor->admin_name, 'customer', $customer->customer_id, $customer->fullName());
            } else {
                $this->audit->customer(AuditAction::CUSTOMER_UPDATED, $customer->customer_id, $customer->fullName(), 'customer', $customer->customer_id);
            }

            return $customer;
        });
    }

    public function blacklist(Customer $customer, string $reason, Admin $actor): Customer
    {
        if (blank($reason)) {
            throw new DomainException('Blacklisting requires a reason.');
        }

        return DB::transaction(function () use ($customer, $reason, $actor) {
            $customer->update([
                'blacklisted' => true,
                'blacklist_reason' => $reason,
                'blacklisted_at' => now(),
                'blacklisted_by' => $actor->admin_id,
            ]);

            $this->audit->admin(AuditAction::CUSTOMER_BLACKLISTED, $actor->admin_id, $actor->admin_name, 'customer', $customer->customer_id, $reason);

            return $customer;
        });
    }

    public function whitelist(Customer $customer, Admin $actor): Customer
    {
        return DB::transaction(function () use ($customer, $actor) {
            $customer->update([
                'blacklisted' => false,
                'blacklist_reason' => null,
                'blacklisted_at' => null,
                'blacklisted_by' => null,
            ]);

            $this->audit->admin(AuditAction::CUSTOMER_WHITELISTED, $actor->admin_id, $actor->admin_name, 'customer', $customer->customer_id);

            return $customer;
        });
    }

    // -----------------------------------------------------------------
    // Password change + tokens
    // -----------------------------------------------------------------

    public function changePassword(string $subjectType, int $subjectId, string $current, string $new, ?string $ip = null): void
    {
        $this->assertPassword($new);

        $model = $subjectType === 'admin'
            ? Admin::findOrFail($subjectId)
            : Customer::findOrFail($subjectId);

        if (! Hash::check($current, (string) $model->password_hash)) {
            throw new InvalidCredentialsException('Your current password is incorrect.');
        }

        DB::transaction(function () use ($model, $subjectType, $subjectId, $new, $ip) {
            $model->update(['password_hash' => Hash::make($new)]);

            $this->audit->log(
                AuditAction::PASSWORD_CHANGED,
                $subjectType === 'admin' ? ActorType::ADMIN : ActorType::CUSTOMER,
                $subjectId,
                $model->email ?? $model->phone,
                $subjectType,
                $subjectId,
                ip: $ip,
            );
        });
    }

    /** Returns the raw token; only its SHA-256 hash is stored. */
    public function issueToken(string $subject, int $subjectId, string $purpose, ?int $ttlMinutes = null): string
    {
        $raw = Str::random(64);

        AuthToken::create([
            'subject' => $subject,
            'subject_id' => $subjectId,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addMinutes($ttlMinutes ?? self::TOKEN_TTL_MINUTES),
        ]);

        return $raw;
    }

    public function consumeToken(string $subject, int $subjectId, string $purpose, string $rawToken): bool
    {
        $token = AuthToken::query()
            ->where('subject', $subject)
            ->where('subject_id', $subjectId)
            ->where('purpose', $purpose)
            ->where('token_hash', hash('sha256', $rawToken))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            return false;
        }

        $token->update(['used_at' => now()]);

        return true;
    }

    public function verifyEmail(Customer $customer, string $rawToken): Customer
    {
        if ($customer->email_verified) {
            return $customer;
        }

        if (! $this->consumeToken('customer', $customer->customer_id, 'verify_email', $rawToken)) {
            throw new DomainException('That verification link is invalid or has expired.');
        }

        return DB::transaction(function () use ($customer) {
            $customer->update(['email_verified' => true, 'verified_at' => now()]);
            $this->audit->customer(AuditAction::EMAIL_VERIFIED, $customer->customer_id, $customer->fullName(), 'customer', $customer->customer_id);

            return $customer;
        });
    }

    public function requestPasswordReset(string $subject, int $subjectId, string $email): string
    {
        $token = $this->issueToken($subject, $subjectId, 'password_reset');

        $this->audit->log(
            AuditAction::PASSWORD_RESET_REQUESTED,
            $subject === 'admin' ? ActorType::ADMIN : ActorType::CUSTOMER,
            $subjectId,
            $email,
            $subject,
            $subjectId,
        );

        return $token;
    }

    public function completePasswordReset(string $subject, int $subjectId, string $rawToken, string $newPassword, ?string $ip = null): void
    {
        $this->assertPassword($newPassword);

        if (! $this->consumeToken($subject, $subjectId, 'password_reset', $rawToken)) {
            throw new DomainException('That reset link is invalid or has expired.');
        }

        DB::transaction(function () use ($subject, $subjectId, $newPassword, $ip) {
            $model = $subject === 'admin' ? Admin::findOrFail($subjectId) : Customer::findOrFail($subjectId);
            $model->update(['password_hash' => Hash::make($newPassword)]);

            $this->audit->log(AuditAction::PASSWORD_RESET_COMPLETED, $subject === 'admin' ? ActorType::ADMIN : ActorType::CUSTOMER, $subjectId, $model->email ?? $model->phone, $subject, $subjectId, ip: $ip);
        });
    }

    /**
     * Admin recovery: set a new temporary password on a customer account and
     * return it so the caller can email it. Walk-ins have no login.
     */
    public function resetCustomerPassword(Customer $customer, Admin $actor): string
    {
        if (! $customer->canLogIn()) {
            throw new DomainException('Walk-in customers have no login to reset.');
        }

        $temporary = $this->makeTemporaryPassword();

        DB::transaction(function () use ($customer, $actor, $temporary) {
            $customer->update(['password_hash' => Hash::make($temporary)]);

            $this->audit->admin(AuditAction::PASSWORD_RESET_COMPLETED, $actor->admin_id, $actor->admin_name, 'customer', $customer->customer_id, 'Temporary password issued by admin');
        });

        return $temporary;
    }

    /** Admin recovery: set a new temporary password on a staff account. */
    public function resetStaffPassword(Admin $staff, Admin $actor): string
    {
        $temporary = $this->makeTemporaryPassword();

        DB::transaction(function () use ($staff, $actor, $temporary) {
            $staff->update([
                'password_hash' => Hash::make($temporary),
                'failed_login_count' => 0,
                'locked_until' => null,
            ]);

            $this->audit->admin(AuditAction::PASSWORD_RESET_COMPLETED, $actor->admin_id, $actor->admin_name, 'admin', $staff->admin_id, 'Temporary password issued by admin');
        });

        return $temporary;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** A random password that satisfies the letter + number + length policy. */
    private function makeTemporaryPassword(): string
    {
        return 'Mv'.Str::random(6).random_int(10, 99);
    }

    public function assertPassword(string $password): void
    {
        if (strlen($password) < 8 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
            throw new DomainException('Password must be at least 8 characters and contain a letter and a number.');
        }
    }

    /** Normalise to 09XXXXXXXXX; accepts 09…, 9…, and 63… prefixes. */
    public function normalisePhone(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input);

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/^09\d{9}$/', $digits)) {
            throw new DomainException('Enter a valid mobile number, e.g. 09171234567.');
        }

        return $digits;
    }
}
