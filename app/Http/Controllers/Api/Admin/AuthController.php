<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\Admin;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = $this->auth->adminLogin($data['email'], $data['password'], $request->ip());

        // One browser session = one role; drop any customer login in this jar.
        Auth::guard('customer')->logout();

        $request->session()->regenerate();
        Auth::guard('admin')->login($admin);

        return response()->json(['admin' => $this->present($admin)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            $this->auth->adminLogout($admin);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['admin' => $this->present($request->user('admin'))]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = $request->user('admin');

        $this->auth->changePassword('admin', $admin->admin_id, $data['current_password'], $data['new_password'], $request->ip());

        return response()->json(['message' => 'Password changed.']);
    }

    /**
     * Self-service recovery for a staff/owner account. Returns the same
     * message regardless of whether the email exists (no enumeration).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        $admin = $this->auth->findAdminByEmail($data['email']);

        if ($admin && $admin->is_active) {
            $token = $this->auth->requestPasswordReset('admin', $admin->admin_id, $admin->email);

            $resetUrl = rtrim(config('app.url'), '/').'/admin/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $admin->email,
            ]);

            Mail::to($admin->email, $admin->admin_name)
                ->send(new ResetPasswordMail($resetUrl, $admin->admin_name, AuthService::TOKEN_TTL_MINUTES));
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

        $admin = $this->auth->findAdminByEmail($data['email']);

        if (! $admin) {
            throw new DomainException('That reset link is invalid or has expired.');
        }

        $this->auth->completePasswordReset('admin', $admin->admin_id, $data['token'], $data['password'], $request->ip());

        return response()->json(['message' => 'Your password has been reset. You can sign in now.']);
    }

    /** @return array<string,mixed> */
    private function present(?Admin $admin): array
    {
        return [
            'admin_id' => $admin?->admin_id,
            'admin_name' => $admin?->admin_name,
            'email' => $admin?->email,
        ];
    }
}
