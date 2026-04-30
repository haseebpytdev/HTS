@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<x-frontend.page-hero :title="$title" :subtitle="$subtitle" :breadcrumbs="$breadcrumbs" {{ $attributes }} />
