<?php

namespace App\Actions\Integrations;

use App\Data\Integrations\IntegrationRawExchangeData;
use App\Models\IntegrationRequestLog;
use App\Models\IntegrationResponseLog;
use Illuminate\Support\Facades\DB;

final class RecordIntegrationRawExchangeAction
{
    public function execute(IntegrationRawExchangeData $data): IntegrationRequestLog
    {
        return DB::transaction(function () use ($data) {
            $now = now();

            $request = IntegrationRequestLog::query()->create([
                'integration_connection_id' => $data->integrationConnectionId,
                'provider' => $data->provider,
                'operation' => $data->operation,
                'environment' => $data->environment,
                'correlation_id' => $data->correlationId,
                'trace_id' => $data->traceId,
                'http_method' => $data->httpMethod,
                'url' => $data->url,
                'request_headers' => $data->requestHeaders,
                'request_body' => $data->requestBody,
                'user_id' => $data->userId,
                'created_at' => $now,
            ]);

            IntegrationResponseLog::query()->create([
                'integration_request_log_id' => $request->id,
                'correlation_id' => $data->correlationId,
                'status_code' => $data->statusCode,
                'response_headers' => $data->responseHeaders,
                'response_body' => $data->responseBody,
                'latency_ms' => $data->latencyMs,
                'error_category' => $data->errorCategory,
                'created_at' => $now,
            ]);

            return $request->fresh(['responseLog']);
        });
    }
}
