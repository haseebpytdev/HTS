@props(['title', 'tag'])

<article class="content-card group-card shadow-sm">
    <div class="group-overlay"></div>
    <div class="position-relative">
        <h6 class="text-white mb-2">{{ $title }}</h6>
        <span class="pill">{{ $tag }}</span>
    </div>
</article>
