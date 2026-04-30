@props([
    'title',
    'tag' => 'Explore',
    'href' => null,
])

@if($href)
    <a href="{{ $href }}" class="text-decoration-none text-reset d-block h-100 group-card-link">
@endif
<article class="group-tile h-100 rounded-4 overflow-hidden shadow">
    <div class="group-tile__bg"></div>
    <div class="group-tile__overlay"></div>
    <div class="group-tile__content">
        <span class="group-tile__tag">{{ $tag }}</span>
        <h3 class="group-tile__title text-white fw-bold mb-3">{{ $title }}</h3>
        <span class="btn btn-sm btn-light rounded-pill px-3 fw-semibold">
            Explore <i class="bi bi-arrow-right ms-1"></i>
        </span>
    </div>
</article>
@if($href)
    </a>
@endif
