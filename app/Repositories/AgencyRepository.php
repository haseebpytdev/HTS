<?php

namespace App\Repositories;

use App\Models\Agency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AgencyRepository
{
    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'desc';

        return Agency::query()
            ->when(($filters['q'] ?? null), function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
            })
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', function ($query) use ($filters): void {
                $query->where('is_active', (bool) $filters['is_active']);
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Agency
    {
        return Agency::create($data);
    }

    public function update(Agency $agency, array $data): Agency
    {
        $agency->update($data);

        return $agency->fresh();
    }

    public function delete(Agency $agency): void
    {
        $agency->delete();
    }
}
