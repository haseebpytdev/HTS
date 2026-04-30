<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\DashboardHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_queue_counts_expiring_tokens_from_config_when_column_is_missing(): void
    {
        DB::table('integration_connections')->insert([
            [
                'provider' => 'amadeus',
                'environment' => 'sandbox',
                'status' => 'healthy',
                'is_active' => 1,
                'is_default' => 0,
                'config' => json_encode([
                    'last_token_expires_at' => Carbon::now()->addHours(6)->toDateTimeString(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'provider' => 'sabre',
                'environment' => 'sandbox',
                'status' => 'healthy',
                'is_active' => 1,
                'is_default' => 0,
                'config' => json_encode([
                    'last_token_expires_at' => Carbon::now()->addDays(2)->toDateTimeString(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $actionQueue = app(DashboardHealthService::class)->actionQueue();

        $this->assertSame(1, $actionQueue['expiring_api_tokens']);
    }
}
