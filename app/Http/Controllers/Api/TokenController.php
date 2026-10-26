<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Hands out a fresh CSRF token. Public on purpose (a token is just a fence);
 * this lets the SPA re-sync after login/register rotate the session token,
 * which would otherwise make the next state-changing call 419.
 */
class TokenController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }
}