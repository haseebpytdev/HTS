@props([
    'paginator',
])

@if($paginator instanceof \Illuminate\Contracts\Pagination\Paginator)
    <div {{ $attributes->class('as-pagination-wrap mt-3') }}>
        {{ $paginator->links() }}
    </div>
@endif
