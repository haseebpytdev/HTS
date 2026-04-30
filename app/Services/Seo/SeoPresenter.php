<?php

namespace App\Services\Seo;

use App\Repositories\SeoPageRepository;

class SeoPresenter
{
    public function __construct(
        private readonly SeoPageRepository $seoPageRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function mergeDetailMeta(string $pageKey, string $subjectTitle, ?string $fallbackDescription = null): array
    {
        $base = $this->seoPageRepository->findByPageKey($pageKey);
        $app = config('app.name', config('brand.name'));
        $suffix = $base?->meta_title ?? $base?->title ?? $app;
        $metaTitle = $subjectTitle.' | '.$suffix;

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $fallbackDescription ?: $base?->meta_description,
            'meta_keywords' => $base?->meta_keywords,
            'og_image' => $base?->og_image,
            'canonical_url' => $base?->canonical_url,
            'schema_markup' => $base?->schema_markup,
            'is_indexable' => $base?->is_indexable ?? true,
        ];
    }
}
