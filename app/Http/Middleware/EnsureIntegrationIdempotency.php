<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        $key = (string) $request->header('X-Idempotency-Key', '');
        if ($key === '') {
            return response()->json([
                'error' => [
                    'code' => 'idempotency_key_required',
                    'message' => 'X-Idempotency-Key header is required.',
                    'correlation_id' => $request->header('X-Correlation-Id'),
                ],
            ], 422);
        }

        $ttl = max(30, (int) config('integrations.idempotency_ttl_seconds', 600));
        $fingerprint = sha1($request->path().'|'.$request->getContent().'|'.$request->header('X-Integration-Key', '').'|'.$key);
        $cacheKey = 'integrations:idempotency:'.$fingerprint;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached['body'] ?? [], (int) ($cached['status'] ?? 200), ['X-Idempotent-Replay' => '1']);
        }

        $response = $next($request);
        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], now()->addSeconds($ttl));
        }

        return $response;
    }
}
