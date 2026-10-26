<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffPasswordResetMail;
use App\Models\Admin;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function index(Request $request): JsonResponse
    {
        $page = Admin::query()->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $page->items(),
            'pagination' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'pages' => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $admin = $this->auth->createStaff(
            $data['admin_name'],
            $data['email'],
            $data['password'],
            $request->user('admin'),
        );

        return response()->json(['admin' => $admin], 201);
    }

    public function update(Request $request, int $adminId): JsonResponse
    {
        $staff = Admin::findOrFail($adminId);

        $data = $request->validate([
            'admin_name' => ['sometimes', 'string', 'max:80'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('admins', 'email')->ignore($adminId, 'admin_id')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $admin = $this->auth->updateStaff($staff, $data, $request->user('admin'));

        return response()->json(['admin' => $admin]);
    }

    public function resetPassword(Request $request, int $adminId): JsonResponse
    {
        $staff = Admin::findOrFail($adminId);

        $temporary = $this->auth->resetStaffPassword($staff, $request->user('admin'));

        Mail::to($staff->email, $staff->admin_name)
            ->send(new StaffPasswordResetMail($temporary, $staff->admin_name));

        return response()->json(['message' => 'A temporary password was emailed to '.$staff->email.'.']);
    }
}
