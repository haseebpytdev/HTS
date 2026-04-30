<?php

namespace App\Console\Commands;

use App\Services\Travel\AirportDirectoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmAirportGlobalIndexCacheCommand extends Command
{
    protected $signature = 'airports:warm-global-index {--refresh : Clear existing global cache before warming}';

    protected $description = 'Warm cached global airport autocomplete index to avoid first-user cold starts.';

    public function handle(AirportDirectoryService $airportDirectoryService): int
    {
        if ((bool) $this->option('refresh')) {
            Cache::forget('travel.airports.directory.v4.global_index');
            Cache::forget('travel.airports.directory.v4.remote');
        }

        $payload = $airportDirectoryService->globalAutocompleteIndexPayload();
        $count = is_array($payload['r'] ?? null) ? count($payload['r']) : 0;
        $version = (string) ($payload['v'] ?? 'n/a');

        $this->info(sprintf(
            'Airport global index warmed. version=%s rows=%d',
            $version,
            $count
        ));

        return self::SUCCESS;
    }
}

