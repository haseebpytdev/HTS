@props([
    'data' => null,
])

@php
    $metaTitle = null;
    $metaDescription = null;
    $metaKeywords = null;
    $ogImage = null;
    $canonicalUrl = null;
    $schemaMarkup = null;
    $isIndexable = true;

    if ($data !== null) {
        if (is_array($data)) {
            $metaTitle = $data['meta_title'] ?? $data['title'] ?? null;
            $metaDescription = $data['meta_description'] ?? null;
            $metaKeywords = $data['meta_keywords'] ?? null;
            $ogImage = $data['og_image'] ?? null;
            $canonicalUrl = $data['canonical_url'] ?? null;
            $schemaMarkup = $data['schema_markup'] ?? null;
            $isIndexable = (bool) ($data['is_indexable'] ?? true);
        } else {
            $metaTitle = $data->meta_title ?? $data->title ?? null;
            $metaDescription = $data->meta_description;
            $metaKeywords = $data->meta_keywords;
            $ogImage = $data->og_image;
            $canonicalUrl = $data->canonical_url;
            $schemaMarkup = $data->schema_markup;
            $isIndexable = (bool) $data->is_indexable;
        }
    }
@endphp

@if($metaDescription)
    <meta name="description" content="{{ $metaDescription }}">
@endif
@if($metaKeywords)
    <meta name="keywords" content="{{ $metaKeywords }}">
@endif
@if($metaTitle)
    <meta property="og:title" content="{{ $metaTitle }}">
@endif
@if($metaDescription)
    <meta property="og:description" content="{{ $metaDescription }}">
@endif
@if($ogImage)
    <meta property="og:image" content="{{ \App\Support\Media::url($ogImage) }}">
@endif
@if($canonicalUrl)
    <link rel="canonical" href="{{ $canonicalUrl }}">
@endif
@if(! $isIndexable)
    <meta name="robots" content="noindex, nofollow">
@endif
@if(is_array($schemaMarkup) && $schemaMarkup !== [])
    <script type="application/ld+json">{!! json_encode($schemaMarkup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
