<?php

namespace App\Repositories;

use App\Models\ContentBlock;
use Illuminate\Support\Collection;

class ContentBlockRepository
{
    /**
     * @param  array<int, string>  $keys
     * @return Collection<string, ContentBlock>
     */
    public function keyedByBlockKey(array $keys): Collection
    {
        if ($keys === []) {
            return collect();
        }

        return ContentBlock::query()
            ->whereIn('block_key', $keys)
            ->where('is_active', true)
            ->get()
            ->keyBy('block_key');
    }
}
