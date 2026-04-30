<?php

namespace App\Console\Commands;

use App\Services\Travel\AirportDirectoryService;
use Illuminate\Console\Command;

class BuildAirportSearchIndexCommand extends Command
{
    protected $signature = 'airports:build-search-index';

    protected $description = 'Rebuild minified public/data/airports.index.min.json and airports.version.json from public/data/airports.json';

    public function handle(AirportDirectoryService $airportDirectoryService): int
    {
        $airportDirectoryService->rebuildPublicSearchIndexFiles();
        $this->info('Airport search index and version files updated in public/data/.');

        return self::SUCCESS;
    }
}
