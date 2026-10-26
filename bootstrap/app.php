<?php

use App\Exceptions\Domain\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Session-based API: the SPA sends X-CSRF-TOKEN from the Blade shell,
        // so requests stay on the web middleware group (invariant 1). No
        // separate token/sanctum layer.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Business refusals are written for humans and safe to show.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => (object) $e->context(),
                ], 422);
            }
        });

        // Invariant 3: no exception message ever reaches the browser. Anything
        // that is not an expected domain/framework response is logged and
        // replaced with a generic message plus a reference code.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            // Let Laravel's safe JSON shape handle validation/auth/authz.
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException) {
                return null;
            }

            // 404/403/419/… : keep the framework status but send only its
            // message — never the exception/trace that would otherwise leak
            // paths and class names.
            if ($e instanceof HttpExceptionInterface) {
                return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
            }

            $reference = (string) Str::uuid();
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Reference: '.$reference,
                'reference' => $reference,
            ], 500);
        });
    })->create();
