<?php

namespace App\Http\Controllers\Api\Customer;

use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;
use App\Models\Customer;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    /**
     * Self sign-up. The customer is NOT logged in afterwards — they must
     * confirm their email with the OTP first (see sendOtp/verifyOtp). In local
     * environments the OTP is echoed back as dev_otp and it is always logged,
     * so a tester can both see and trace it.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:90'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:190', 'unique:customers,email'],
            'password' => ['required', 'string', 'min:8'],
            'customer_type' => ['required', Rule::in(['New', 'Returning'])],
            'terms_accepted' => ['required', 'accepted'],
            'address_bldg' => ['nullable', 'string', 'max:90'],
            'address_street' => ['nullable', 'string', 'max:120'],
            'address_brgy' => ['nullable', 'string', 'max:120'],
            'address_city' => ['nullable', 'string', 'max:90'],
        ]);

        $customer = $this->auth->register(
            $data['first_name'],
            $data['last_name'],
            $data['phone'],
            $data['email'],
            $data['password'],
            [
                'bldg' => $data['address_bldg'] ?? null,
                'street' => $data['address_street'] ?? null,
                'brgy' => $data['address_brgy'] ?? null,
                'city' => $data['address_city'] ?? '',
            ],
            $data['customer_type'],
            (bool) $data['terms_accepted'],
        );

        $otp = $this->auth->issueOtp('customer', $customer->customer_id);
        $this->sendOtpEmail($customer, $otp);

        return response()->json([
            'customer' => $this->present($customer),
            'requires_otp' => true,
            'dev_otp' => app()->environment('local') ? $otp : null,
        ], 201);
    }

    /** Resend the verification OTP for a registered-but-unverified email. */
    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        $customer = $this->auth->findCustomerByEmail($data['email']);

        if (! $customer) {
            return response()->json([
                'message' => 'We could not find an account with that email.',
            ], 422);
        }

        if ($customer->email_verified) {
            return response()->json([
                'message' => 'This email is already verified. Sign in to continue.',
                'requires_otp' => false,
            ]);
        }

        $otp = $this->auth->issueOtp('customer', $customer->customer_id);
        $this->sendOtpEmail($customer, $otp);

        return response()->json([
            'message' => 'A verification code was sent to '.$customer->email.'.',
            'sent_to' => $customer->email,
            'requires_otp' => true,
            'dev_otp' => app()->environment('local') ? $otp : null,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $customer = $this->auth->findCustomerByEmail($data['email']);

        if (! $customer) {
            return response()->json(['message' => 'We could not find an account with that email.'], 422);
        }

        $this->auth->verifyEmail($customer, $data['code']);

        return response()->json([
            'message' => 'Email verified. You can now sign in.',
            'customer' => $this->present($customer),
            'verified' => true,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $customer = $this->auth->customerLogin($data['identifier'], $data['password'], $request->ip());

        // One browser session = one role. Without this an admin session left in
        // the same cookie jar would win the next session-restore check and send
        // a signed-in customer back to /login.
        Auth::guard('admin')->logout();

        $request->session()->regenerate();
        Auth::guard('customer')->login($customer);

        return response()->json(['customer' => $this->present($customer)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        if ($customer) {
            $this->auth->customerLogout($customer);
        }

        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['customer' => $this->present($request->user('customer'))]);
    }

    /** Edit the profile (name, contact, address) and customer_type. */
    public function profile(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:60'],
            'last_name' => ['sometimes', 'string', 'max:90'],
            'phone' => ['sometimes', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($customer->customer_id, 'customer_id')],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('customers', 'email')->ignore($customer->customer_id, 'customer_id')],
            'customer_type' => ['sometimes', Rule::in(['New', 'Returning'])],
            'address_bldg' => ['sometimes', 'nullable', 'string', 'max:90'],
            'address_street' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address_brgy' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address_city' => ['sometimes', 'nullable', 'string', 'max:90'],
            'address_notes' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $updated = $this->auth->updateCustomer($customer, $data);

        return response()->json(['customer' => $this->present($updated)]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customer = $request->user('customer');

        $this->auth->changePassword('customer', $customer->customer_id, $data['current_password'], $data['new_password'], $request->ip());

        return response()->json(['message' => 'Password changed.']);
    }

    /**
     * Start a password reset. Always returns the same message so the endpoint
     * cannot be used to discover which emails have accounts.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        $customer = $this->auth->findCustomerByEmail($data['email']);

        if ($customer && $customer->canLogIn()) {
            $token = $this->auth->requestPasswordReset('customer', $customer->customer_id, $customer->email);

            $resetUrl = rtrim(config('app.url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $customer->email,
            ]);

            Mail::to($customer->email, $customer->fullName())
                ->send(new ResetPasswordMail($resetUrl, $customer->fullName(), AuthService::TOKEN_TTL_MINUTES));
        }

        return response()->json([
            'message' => 'If that email is registered, a reset link is on its way.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customer = $this->auth->findCustomerByEmail($data['email']);

        if (! $customer) {
            throw new DomainException('That reset link is invalid or has expired.');
        }

        $this->auth->completePasswordReset('customer', $customer->customer_id, $data['token'], $data['password'], $request->ip());

        return response()->json(['message' => 'Your password has been reset. You can sign in now.']);
    }

    private function sendOtpEmail(Customer $customer, string $otp): void
    {
        Mail::to($customer->email, $customer->fullName())
            ->send(new VerifyEmailMail($otp, $customer->fullName()));
    }

    /** @return array<string,mixed> */
    private function present(?Customer $customer): array
    {
        return [
            'customer_id' => $customer?->customer_id,
            'first_name' => $customer?->first_name,
            'last_name' => $customer?->last_name,
            'phone' => $customer?->phone,
            'email' => $customer?->email,
            'email_verified' => (bool) $customer?->email_verified,
            'customer_type' => $customer?->customer_type,
            'terms_accepted_at' => $customer?->terms_accepted_at?->toDateTimeString(),
            'address_bldg' => $customer?->address_bldg,
            'address_street' => $customer?->address_street,
            'address_brgy' => $customer?->address_brgy,
            'address_city' => $customer?->address_city,
            'address_notes' => $customer?->address_notes,
        ];
    }
}