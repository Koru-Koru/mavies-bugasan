<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AuditAction;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => DB::table('settings')->pluck('setting_value', 'setting_key'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:400'],
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['settings'] as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => (string) $value, 'updated_at' => now()],
                );
            }

            $this->audit->admin(
                AuditAction::SETTINGS_UPDATED,
                $request->user('admin')->admin_id,
                $request->user('admin')->admin_name,
                'settings',
                null,
                implode(', ', array_keys($data['settings'])),
            );
        });

        return response()->json([
            'settings' => DB::table('settings')->pluck('setting_value', 'setting_key'),
        ]);
    }
}
