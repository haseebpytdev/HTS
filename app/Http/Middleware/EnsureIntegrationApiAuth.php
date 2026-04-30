<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Integration-Key', '');
        $expected = (string) config('integrations.api_key', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => [
                    'code' => 'unauthorized_integration_request',
                    'message' => 'Missing or invalid integration API key.',
                    'correlation_id' => $request->header('X-Correlation-Id'),
                ],
            ], 401);
        }

        return $next($request);
    }
}
