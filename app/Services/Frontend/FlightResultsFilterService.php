<?php

namespace App\Services\Frontend;

class FlightResultsFilterService
{
    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $input
     * @return array{
     *   offers:list<array<string, mixed>>,
     *   applied:array<string, string|null>,
     *   options:array{
     *     airlines:list<string>,
     *     cabins:list<string>,
     *     price_min:float|null,
     *     price_max:float|null
     *   },
     *   counts:array{total:int,filtered:int}
     * }
     */
    public function apply(array $offers, array $input): array
    {
        $applied = $this->sanitize($input);
        $options = $this->options($offers);
        $filtered = array_values(array_filter($offers, fn (array $offer): bool => $this->matches($offer, $applied)));
        $sorted = $this->sort($filtered, $applied['sort'] ?? 'best_value');

        return [
            'offers' => $sorted,
            'applied' => $applied,
            'options' => $options,
            'counts' => [
                'total' => count($offers),
                'filtered' => count($sorted),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   sort:string,
     *   stops:?string,
     *   airline:?string,
     *   departure_window:?string,
     *   arrival_window:?string,
     *   filter_cabin_class:?string,
     *   price_min:?string,
     *   price_max:?string
     * }
     */
    private function sanitize(array $input): array
    {
        $sort = strtolower(trim((string) ($input['sort'] ?? 'best_value')));
        $sorts = ['best_value', 'cheapest', 'fastest', 'earliest_departure'];
        if (! in_array($sort, $sorts, true)) {
            $sort = 'best_value';
        }

        return [
            'sort' => $sort,
            'stops' => $this->nullable(trim((string) ($input['stops'] ?? ''))),
            'airline' => $this->nullable(strtoupper(trim((string) ($input['airline'] ?? '')))),
            'departure_window' => $this->nullable(strtolower(trim((string) ($input['departure_window'] ?? '')))),
            'arrival_window' => $this->nullable(strtolower(trim((string) ($input['arrival_window'] ?? '')))),
            'filter_cabin_class' => $this->nullable(strtolower(trim((string) ($input['filter_cabin_class'] ?? '')))),
            'price_min' => $this->nullable(trim((string) ($input['price_min'] ?? ''))),
            'price_max' => $this->nullable(trim((string) ($input['price_max'] ?? ''))),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array{
     *   airlines:list<string>,
     *   cabins:list<string>,
     *   price_min:float|null,
     *   price_max:float|null
     * }
     */
    private function options(array $offers): array
    {
        $airlines = [];
        $cabins = [];
        $prices = [];

        foreach ($offers as $offer) {
            $summary = is_array($offer['summary'] ?? null) ? $offer['summary'] : [];
            $airline = strtoupper(trim((string) ($summary['airline_code'] ?? '')));
            if ($airline !== '') {
                $airlines[] = $airline;
            }

            $cabin = strtolower(trim((string) ($summary['cabin_class_normalized'] ?? '')));
            if ($cabin !== '') {
                $cabins[] = $cabin;
            }

            $price = $this->priceAmount($offer);
            if ($price !== null) {
                $prices[] = $price;
            }
        }

        sort($airlines);
        sort($cabins);

        return [
            'airlines' => array_values(array_unique($airlines)),
            'cabins' => array_values(array_unique($cabins)),
            'price_min' => $prices === [] ? null : min($prices),
            'price_max' => $prices === [] ? null : max($prices),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, string|null>  $applied
     */
    private function matches(array $offer, array $applied): bool
    {
        $summary = is_array($offer['summary'] ?? null) ? $offer['summary'] : [];

        if ($applied['stops'] !== null && $applied['stops'] !== 'any') {
            $stopCount = (int) ($summary['stop_count'] ?? 0);
            if ($applied['stops'] === '2_plus') {
                if ($stopCount < 2) {
                    return false;
                }
            } elseif ((string) $stopCount !== $applied['stops']) {
                return false;
            }
        }

        if ($applied['airline'] !== null) {
            $airline = strtoupper(trim((string) ($summary['airline_code'] ?? '')));
            if ($airline !== $applied['airline']) {
                return false;
            }
        }

        if ($applied['filter_cabin_class'] !== null) {
            $cabin = strtolower(trim((string) ($summary['cabin_class_normalized'] ?? '')));
            if ($cabin !== $applied['filter_cabin_class']) {
                return false;
            }
        }

        if ($applied['departure_window'] !== null) {
            if (! $this->windowMatch($summary['departure_minutes'] ?? null, $applied['departure_window'])) {
                return false;
            }
        }

        if ($applied['arrival_window'] !== null) {
            if (! $this->windowMatch($summary['arrival_minutes'] ?? null, $applied['arrival_window'])) {
                return false;
            }
        }

        $price = $this->priceAmount($offer);
        if ($applied['price_min'] !== null && is_numeric($applied['price_min']) && $price !== null) {
            if ($price < (float) $applied['price_min']) {
                return false;
            }
        }
        if ($applied['price_max'] !== null && is_numeric($applied['price_max']) && $price !== null) {
            if ($price > (float) $applied['price_max']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    private function sort(array $offers, string $sort): array
    {
        usort($offers, function (array $a, array $b) use ($sort): int {
            $aSummary = is_array($a['summary'] ?? null) ? $a['summary'] : [];
            $bSummary = is_array($b['summary'] ?? null) ? $b['summary'] : [];

            $aPrice = $this->priceAmount($a) ?? INF;
            $bPrice = $this->priceAmount($b) ?? INF;
            $aDuration = (int) ($aSummary['duration_minutes'] ?? PHP_INT_MAX);
            $bDuration = (int) ($bSummary['duration_minutes'] ?? PHP_INT_MAX);
            $aDep = (int) ($aSummary['departure_minutes'] ?? PHP_INT_MAX);
            $bDep = (int) ($bSummary['departure_minutes'] ?? PHP_INT_MAX);
            $aStops = (int) ($aSummary['stop_count'] ?? 99);
            $bStops = (int) ($bSummary['stop_count'] ?? 99);

            return match ($sort) {
                'cheapest' => [$aPrice, $aDuration, $aDep] <=> [$bPrice, $bDuration, $bDep],
                'fastest' => [$aDuration, $aPrice, $aDep] <=> [$bDuration, $bPrice, $bDep],
                'earliest_departure' => [$aDep, $aPrice, $aDuration] <=> [$bDep, $bPrice, $bDuration],
                default => [$aStops, $aPrice, $aDuration, $aDep] <=> [$bStops, $bPrice, $bDuration, $bDep],
            };
        });

        return $offers;
    }

    private function windowMatch(mixed $minutes, string $window): bool
    {
        if (! is_numeric($minutes)) {
            return false;
        }

        $hour = intdiv((int) $minutes, 60);

        return match ($window) {
            'morning' => $hour >= 5 && $hour < 12,
            'afternoon' => $hour >= 12 && $hour < 18,
            'evening' => $hour >= 18 && $hour < 22,
            'night' => $hour >= 22 || $hour < 5,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function priceAmount(array $offer): ?float
    {
        $display = is_array($offer['display_price'] ?? null) ? $offer['display_price'] : null;
        if ($display !== null && ! empty($display['display_amount_unavailable']) && isset($display['original_amount']) && is_numeric($display['original_amount'])) {
            return (float) $display['original_amount'];
        }

        if ($display !== null && isset($display['display_amount']) && is_numeric($display['display_amount']) && empty($display['display_amount_unavailable'])) {
            return (float) $display['display_amount'];
        }

        $price = is_array($offer['price'] ?? null) ? $offer['price'] : null;
        if ($price !== null && isset($price['total_amount']) && is_numeric($price['total_amount'])) {
            return (float) $price['total_amount'];
        }

        return null;
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
