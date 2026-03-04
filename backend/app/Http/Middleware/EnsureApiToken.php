<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $expected = env('API_BEARER_TOKEN', 'dev-earthquake-token');

        if (!$token || $token !== $expected) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
