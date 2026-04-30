<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Integrations\ProviderCredentialResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DuffelConnectivityTestController extends Controller
{
    public function __invoke(ProviderCredentialResolver $resolver): JsonResponse
    {
        if (! config('integrations.duffel_connectivity_test_enabled')) {
            throw new NotFoundHttpException();
        }

        if (! app()->isLocal() && ! config('app.debug')) {
            throw new NotFoundHttpException();
        }

        $selection = $resolver->resolveDuffelTokenSelection(operation: 'search');
        $token = trim((string) ($selection['token'] ?? ''));
        if ($token === '') {
            return response()->json([
                'ok' => false,
                'error' => 'duffel_token_missing',
                'message' => 'No Duffel API token resolved for the search operation.',
            ], 422);
        }

        $baseUrl = rtrim((string) config('duffel.base_url'), '/');
        $version = (string) config('duffel.version', 'v2');
        $uri = $baseUrl.'/air/airlines?limit=1';

        $pending = Http::timeout(30)
            ->connectTimeout((int) config('integrations.http_connect_timeout_seconds', 10));

        if (config('integrations.duffel_debug_disable_ssl_verify')) {
            $pending = $pending->withOptions(['verify' => false]);
        }

        $response = $pending->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Duffel-Version' => $version,
            'Accept' => 'application/json',
        ])->get($uri);

        $body = $response->body();

        return response()->json([
            'ok' => $response->successful(),
            'http_status' => $response->status(),
            'duffel_version' => $version,
            'runtime_environment' => $selection['runtime_environment'] ?? null,
            'token_source' => $selection['source'] ?? null,
            'body_preview' => mb_substr($body, 0, 500),
        ], $response->successful() ? 200 : 502);
    }
}
