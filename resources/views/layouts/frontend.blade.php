@extends('layouts.frontend-public')

@push('head')
    <x-seo.meta :data="$pageSeo ?? $sharedSeo ?? []" />
@endpush
