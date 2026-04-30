<?php

namespace App\Services\Travel;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirportDirectoryService
{
    private const CACHE_KEY = 'travel.airports.directory.v4.local';
    private const REMOTE_CACHE_KEY = 'travel.airports.directory.v4.remote';
    private const GLOBAL_INDEX_CACHE_KEY = 'travel.airports.directory.v4.global_index';

    /**
     * In-process mirror of the normalized directory so repeated searches in the same PHP worker
     * avoid hitting the cache layer after the first resolution.
     *
     * @var array<int, array<string, string>>|null
     */
    private static ?array $runtimeIndex = null;

    /**
     * @return array<int, array<string, string>>
     */
    public function search(string $query, int $limit = 20): array
    {
        $normalizedQuery = mb_strtolower(trim($query));
        $limit = max(1, min($limit, 50));
        $rows = $this->allAirports();

        $localMatches = $this->rankedSearch($rows, $normalizedQuery, $limit);
        if ($normalizedQuery === '' || mb_strlen($normalizedQuery) < 2) {
            return $localMatches;
        }

        $remoteMatches = $this->rankedSearch($this->remoteAirports(), $normalizedQuery, $limit);
        if ($remoteMatches === []) {
            return $localMatches;
        }

        // Keep local matches first; append unique remote additions.
        $seen = [];
        $merged = [];
        foreach (array_merge($localMatches, $remoteMatches) as $row) {
            $key = strtoupper((string) ($row['iata'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $row;
            if (count($merged) >= $limit) {
                break;
            }
        }

        return $merged;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, string>>
     */
    private function rankedSearch(array $rows, string $normalizedQuery, int $limit): array
    {
        if ($normalizedQuery === '') {
            return array_slice(array_map(static fn (array $airport): array => [
                'iata' => $airport['iata'],
                'city' => $airport['city'],
                'country' => $airport['country'],
                'airport' => $airport['airport'],
                'label' => $airport['label'],
            ], $rows), 0, $limit);
        }

        $exactIata = [];
        $prefixMatches = [];
        $containsMatches = [];
        foreach ($rows as $airport) {
            $isExactIata = $airport['iata_lower'] === $normalizedQuery;
            $isPrefixMatch = str_starts_with($airport['iata_lower'], $normalizedQuery)
                || str_starts_with($airport['city_lower'], $normalizedQuery)
                || str_starts_with($airport['airport_lower'], $normalizedQuery)
                || str_starts_with($airport['country_lower'], $normalizedQuery);
            $isContainsMatch = strlen($normalizedQuery) >= 2
                && str_contains($airport['search'], $normalizedQuery);

            if (! $isExactIata && ! $isPrefixMatch && ! $isContainsMatch) {
                continue;
            }

            $payload = [
                'iata' => $airport['iata'],
                'city' => $airport['city'],
                'country' => $airport['country'],
                'airport' => $airport['airport'],
                'label' => $airport['label'],
            ];

            if ($isExactIata) {
                $exactIata[] = $payload;
                continue;
            }

            if ($isPrefixMatch) {
                $prefixMatches[] = $payload;
                continue;
            }

            $containsMatches[] = $payload;
        }

        $ordered = array_merge($exactIata, $prefixMatches, $containsMatches);

        return array_slice($ordered, 0, $limit);
    }

    /**
     * Remote fallback airport list for broader world coverage.
     *
     * @return array<int, array<string, string>>
     */
    private function remoteAirports(): array
    {
        if (! (bool) config('services.airport_directory.remote_search_enabled', true)) {
            return [];
        }

        $ttlSeconds = max(300, (int) config('services.airport_directory.remote_cache_seconds', 86400));

        return Cache::remember(self::REMOTE_CACHE_KEY, now()->addSeconds($ttlSeconds), function (): array {
            $baseUrl = (string) config('services.airport_directory.remote_base_url', 'https://raw.githubusercontent.com/mwgg/Airports/master/airports.json');
            $timeout = max(2, (int) config('services.airport_directory.remote_timeout_seconds', 10));

            try {
                $response = Http::timeout($timeout)->acceptJson()->get($baseUrl);
            } catch (\Throwable $e) {
                Log::warning('Airport remote dataset request failed', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }

            if (! $response->successful()) {
                return [];
            }

            $decoded = $response->json();
            if (! is_array($decoded)) {
                return [];
            }

            $rows = [];
            foreach ($decoded as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $iata = strtoupper(trim((string) ($entry['iata'] ?? '')));
                $airport = trim((string) ($entry['name'] ?? ''));
                $city = trim((string) ($entry['city'] ?? ''));
                $country = trim((string) ($entry['country'] ?? ''));

                if (strlen($iata) !== 3 || $airport === '' || $city === '' || $country === '') {
                    continue;
                }

                $rows[] = [
                    'iata' => $iata,
                    'airport' => $airport,
                    'city' => $city,
                    'country' => $country,
                ];
            }

            return $this->normalizeRows($rows);
        });
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function allAirports(): array
    {
        if (self::$runtimeIndex !== null) {
            return self::$runtimeIndex;
        }

        return self::$runtimeIndex = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->localAirports());
    }

    /**
     * @return array{count:int,updated_at:?int}
     */
    public function localDatasetMeta(): array
    {
        $path = $this->datasetPath();

        return [
            'count' => count($this->allAirports()),
            'updated_at' => is_file($path) ? filemtime($path) : null,
        ];
    }

    /**
     * Compact global index payload for frontend autocomplete preloading.
     *
     * @return array{v:string,r:array<int, array{0:string,1:string,2:string,3:string}>}
     */
    public function globalAutocompleteIndexPayload(): array
    {
        $ttlSeconds = max(300, (int) config('services.airport_directory.global_index_cache_seconds', 86400));

        /** @var array{v:string,r:array<int, array{0:string,1:string,2:string,3:string}>} $payload */
        $payload = Cache::remember(
            self::GLOBAL_INDEX_CACHE_KEY,
            now()->addSeconds($ttlSeconds),
            function (): array {
                $rows = $this->allAirports();
                $remoteRows = $this->remoteAirports();
                $mergedByIata = [];

                foreach (array_merge($rows, $remoteRows) as $row) {
                    $iata = strtoupper((string) ($row['iata'] ?? ''));
                    if (strlen($iata) !== 3) {
                        continue;
                    }
                    if (! isset($mergedByIata[$iata])) {
                        $mergedByIata[$iata] = $row;
                    }
                }

                ksort($mergedByIata);

                $compact = [];
                foreach ($mergedByIata as $row) {
                    $compact[] = [
                        (string) $row['iata'],
                        (string) $row['city'],
                        (string) $row['country'],
                        (string) $row['airport'],
                    ];
                }

                $version = substr(hash('sha256', json_encode($compact, JSON_UNESCAPED_UNICODE) ?: ''), 0, 16);

                return [
                    'v' => $version,
                    'r' => $compact,
                ];
            }
        );

        return $payload;
    }

    /**
     * @return array{count:int}
     */
    public function replaceLocalDataset(UploadedFile $file): array
    {
        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);
        if (! is_array($decoded)) {
            return ['count' => 0];
        }

        $rows = $this->normalizeRows($decoded);
        if ($rows === []) {
            return ['count' => 0];
        }

        $path = $this->datasetPath();
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, json_encode(array_map(static fn (array $row): array => [
            'iata' => $row['iata'],
            'airport' => $row['airport'],
            'city' => $row['city'],
            'country' => $row['country'],
        ], $rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::GLOBAL_INDEX_CACHE_KEY);
        self::$runtimeIndex = null;

        $this->rebuildPublicSearchIndexFiles();

        return ['count' => count($rows)];
    }

    /**
     * Writes minified frontend search payloads ({@see public/data/airports.index.min.json})
     * and a tiny version file for cache busting ({@see public/data/airports.version.json}).
     */
    public function rebuildPublicSearchIndexFiles(): void
    {
        $path = $this->datasetPath();
        if (! is_file($path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return;
        }

        $rows = $this->normalizeRows($decoded);
        $compact = [];
        foreach ($rows as $row) {
            $compact[] = [$row['iata'], $row['city'], $row['country'], $row['airport']];
        }

        $canonical = json_encode($compact, JSON_UNESCAPED_UNICODE);
        $version = substr(hash('sha256', (string) $canonical), 0, 16);

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir.'/airports.version.json', json_encode(['v' => $version], JSON_UNESCAPED_UNICODE));
        file_put_contents(
            $dir.'/airports.index.min.json',
            json_encode(['v' => $version, 'r' => $compact], JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function localAirports(): array
    {
        $path = $this->datasetPath();
        $rows = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $rows = $this->normalizeRows($decoded);
            }
        }

        // Fallback to prebuilt compact index when local dataset is missing/sparse.
        if (count($rows) >= 10) {
            return $rows;
        }

        $indexPath = public_path('data/airports.index.min.json');
        if (! is_file($indexPath)) {
            return $rows;
        }

        $indexDecoded = json_decode((string) file_get_contents($indexPath), true);
        if (! is_array($indexDecoded) || ! is_array($indexDecoded['r'] ?? null)) {
            return $rows;
        }

        $expanded = [];
        foreach ($indexDecoded['r'] as $entry) {
            if (! is_array($entry) || count($entry) < 4) {
                continue;
            }

            $expanded[] = [
                'iata' => (string) $entry[0],
                'city' => (string) $entry[1],
                'country' => (string) $entry[2],
                'airport' => (string) $entry[3],
            ];
        }

        if ($expanded === []) {
            return $rows;
        }

        return $this->normalizeRows($expanded);
    }

    /**
     * @param  array<int, mixed>  $decoded
     * @return array<int, array<string, string>>
     */
    private function normalizeRows(array $decoded): array
    {
        $rows = [];
        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $iata = strtoupper(trim((string) ($entry['iata'] ?? $entry['iata_code'] ?? '')));
            $airport = trim((string) ($entry['airport'] ?? $entry['name'] ?? ''));
            $city = trim((string) ($entry['city'] ?? ''));
            $country = trim((string) ($entry['country'] ?? ''));
            if (strlen($iata) !== 3 || $airport === '' || $city === '' || $country === '') {
                continue;
            }

            $label = sprintf('%s, %s - %s (%s)', $city, $country, $airport, $iata);
            $iataLower = mb_strtolower($iata);
            $cityLower = mb_strtolower($city);
            $countryLower = mb_strtolower($country);
            $airportLower = mb_strtolower($airport);
            $rows[] = [
                'iata' => $iata,
                'city' => $city,
                'country' => $country,
                'airport' => $airport,
                'label' => $label,
                'iata_lower' => $iataLower,
                'city_lower' => $cityLower,
                'country_lower' => $countryLower,
                'airport_lower' => $airportLower,
                'search' => sprintf('%s %s %s %s', $cityLower, $countryLower, $airportLower, $iataLower),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $rows;
    }

    private function datasetPath(): string
    {
        return public_path('data/airports.json');
    }
}
