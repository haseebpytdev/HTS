<?php

namespace App\Repositories;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookingRepository
{
    /**
     * @param  array{q?: string|null, status?: string|null, agency_id?: int|null}  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Booking::query()
            ->with(['agency', 'quotation'])
            ->when($filters['agency_id'] ?? null, fn ($q, int $id) => $q->where('agency_id', $id))
            ->when($filters['status'] ?? null, fn ($q, string $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, function ($q, string $term): void {
                $like = '%'.$term.'%';
                $q->where(function ($n) use ($like): void {
                    $n->where('booking_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('invoice_number', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function nextBookingNumber(): string
    {
        $prefix = 'BK-';
        $latest = Booking::query()->withoutGlobalScopes()
            ->where('booking_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('booking_number');

        if (! is_string($latest) || ! preg_match('/^BK-(\d+)$/', $latest, $m)) {
            return $prefix.str_pad('1', 6, '0', STR_PAD_LEFT);
        }

        $next = (int) $m[1] + 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
