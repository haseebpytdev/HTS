@extends('layouts.frontend')

@section('title', $post->title)

@section('content')
    <x-frontend.page-hero :title="$post->title" :subtitle="$post->excerpt ?? 'Blog article'" :breadcrumbs="[['label' => 'Home', 'url' => route('frontend.home')], ['label' => 'Blog', 'url' => route('frontend.blog.index')], ['label' => $post->title, 'url' => null]]" />
    <section class="section-block pt-0">
        <div class="container">
            <article class="content-shell p-3 p-md-4 p-lg-5">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <x-ui.badge tone="info">Travel Tips</x-ui.badge>
                    <p class="text-muted small mb-0">Published {{ optional($post->published_at)->format('d M Y') }}</p>
                </div>
                <div class="blog-content">{!! nl2br(e($post->body ?? '')) !!}</div>
            </article>
        </div>
    </section>
@endsection
