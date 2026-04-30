<?php

namespace App\Repositories;

use App\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QuotationRepository
{
    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Quotation::query()
            ->with(['agency', 'user'])
            ->when(($filters['q'] ?? null), function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('quote_number', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_email', 'like', "%{$term}%");
                });
            })
            ->when(($filters['agency_id'] ?? null), fn ($query, $agencyId) => $query->where('agency_id', $agencyId))
            ->when(($filters['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Quotation
    {
        return Quotation::create($data);
    }

    public function update(Quotation $quotation, array $data): Quotation
    {
        $quotation->update($data);

        return $quotation->fresh();
    }

    public function nextQuoteNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "QTN-{$year}-";
        $lastQuote = Quotation::query()
            ->where('quote_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('quote_number');

        $next = 1;
        if ($lastQuote) {
            $lastPart = (int) substr($lastQuote, -4);
            $next = $lastPart + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
