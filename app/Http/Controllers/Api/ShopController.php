<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Public business details for the landing page. Explicitly public, like the
 * catalog: a stranger must know contact details before signing in.
 */
class ShopController extends Controller
{
    public function info(): JsonResponse
    {
        return response()->json([
            'settings' => DB::table('settings')->pluck('setting_value', 'setting_key'),
        ]);
    }
}