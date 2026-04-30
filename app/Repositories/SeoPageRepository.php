<?php

namespace App\Repositories;

use App\Models\SeoPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SeoPageRepository
{
    public function findByPageKey(string $pageKey): ?SeoPage
    {
        return SeoPage::query()->where('page_key', $pageKey)->first();
    }

    /**
     * @param  array{q?: string|null}  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return SeoPage::query()
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('page_key', 'like', "%{$term}%")
                        ->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('meta_title', 'like', "%{$term}%");
                });
            })
            ->orderBy('page_key')
            ->paginate($perPage)
            ->withQueryString();
    }
}
