@props(['title', 'subtitle'])

<article class="content-card package-card shadow-sm">
    <div class="package-image"></div>
    <div class="p-2">
        <h6 class="mb-1">{{ $title }}</h6>
        <small class="text-muted">{{ $subtitle }}</small>
    </div>
</article>
