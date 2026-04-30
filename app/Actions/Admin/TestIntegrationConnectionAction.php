<?php

namespace App\Actions\Admin;

use App\Models\IntegrationConnection;
use App\Services\Integrations\ConnectionHealthCheckService;

final class TestIntegrationConnectionAction
{
    public function __construct(
        private readonly ConnectionHealthCheckService $healthCheck,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, token_expires_at: ?string}
     */
    public function execute(IntegrationConnection $connection): array
    {
        $checkedAt = now();
        $result = $this->healthCheck->check($connection);
        $tokenExpiry = $result['token_expires_at'];

        $connection->forceFill([
            'last_tested_at' => $checkedAt,
            'last_checked_at' => $checkedAt,
            'status' => $result['ok'] ? 'healthy' : ($connection->is_active ? 'failed' : 'disabled'),
            'last_tested_status' => $result['ok'] ? 'ok' : 'failed',
            'last_success_at' => $result['ok'] ? $checkedAt : $connection->last_success_at,
            'last_failure_at' => $result['ok'] ? $connection->last_failure_at : $checkedAt,
            'last_failure_reason' => $result['ok'] ? null : $result['message'],
            'token_expires_at' => $tokenExpiry,
            'config' => array_merge((array) ($connection->config ?? []), [
                'last_token_expires_at' => $tokenExpiry,
            ]),
        ])->save();

        return $result;
    }
}
