<?php

namespace App\Services\Integrations;

use App\Data\Integrations\FlightOfferData;
use DateTimeImmutable;
use Throwable;

final class FlightOfferComparisonEngine
{
    /**
     * @param  list<FlightOfferData>  $offers
     * @return array{cheapest: ?array<string, mixed>, fastest: ?array<string, mixed>, best: ?array<string, mixed>}
     */
    public function compare(array $offers): array
    {
        $scored = array_map(fn (FlightOfferData $offer): array => $this->scoreOffer($offer), $offers);
        $scored = array_values(array_filter($scored, static fn (array $row): bool => $row['duration_minutes'] !== null));

        if ($scored === []) {
            return [
                'cheapest' => null,
                'fastest' => null,
                'best' => null,
            ];
        }

        $cheapest = $this->pickBy($scored, fn (array $x): float => $x['price_total'] ?? INF);
        $fastest = $this->pickBy($scored, fn (array $x): float => (float) $x['duration_minutes']);
        $best = $this->pickBy($scored, function (array $x): float {
            $price = $x['price_total'] ?? INF;
            $duration = (float) $x['duration_minutes'];

            // "Best": weighted blend (price-centric but duration-aware).
            return ($price * 0.65) + ($duration * 0.35);
        });

        return [
            'cheapest' => $this->toSummary($cheapest),
            'fastest' => $this->toSummary($fastest),
            'best' => $this->toSummary($best),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreOffer(FlightOfferData $offer): array
    {
        $segments = $offer->segments;
        $first = $segments[0] ?? null;
        $last = $segments[count($segments) - 1] ?? null;

        $duration = null;
        if ($first !== null && $last !== null) {
            $start = $this->toTime($first->departureAt);
            $end = $this->toTime($last->arrivalAt);
            if ($start !== null && $end !== null && $end >= $start) {
                $duration = (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
            }
        }

        $provider = $offer->metadata?->provider ?? 'unknown';

        return [
            'offer' => $offer,
            'provider' => $provider,
            'price_total' => $offer->price?->totalAmount,
            'currency' => $offer->price?->currency,
            'duration_minutes' => $duration,
            'stops' => max(count($segments) - 1, 0),
        ];
    }

    private function toTime(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>):float  $metric
     * @return array<string, mixed>
     */
    private function pickBy(array $rows, callable $metric): array
    {
        usort($rows, static function (array $a, array $b) use ($metric): int {
            return $metric($a) <=> $metric($b);
        });

        return $rows[0];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toSummary(array $row): array
    {
        /** @var FlightOfferData $offer */
        $offer = $row['offer'];

        return [
            'offer_id' => $offer->id,
            'provider_offer_reference' => $offer->providerOfferReference,
            'provider' => $row['provider'],
            'currency' => $row['currency'],
            'price_total' => $row['price_total'],
            'duration_minutes' => $row['duration_minutes'],
            'stops' => $row['stops'],
        ];
    }
}
