@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<section class="page-hero py-4 py-md-5">
    <div class="container">
        @if(count($breadcrumbs))
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0 small">
                    @foreach ($breadcrumbs as $crumb)
                        @if(! empty($crumb['url']) && ! $loop->last)
                            <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}" class="text-decoration-none">{{ $crumb['label'] }}</a></li>
                        @else
                            <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="page-hero__title mb-2">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-hero__subtitle mb-0 col-lg-8">{{ $subtitle }}</p>
        @endif
    </div>
</section>
