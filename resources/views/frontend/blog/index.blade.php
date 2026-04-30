@extends('layouts.frontend')

@section('title', 'Blog')

@section('content')
    <x-frontend.page-hero title="Travel Blog" :subtitle="'Insights, guides, and updates from '.config('brand.name').'.'" :breadcrumbs="[['label' => 'Home', 'url' => route('frontend.home')], ['label' => 'Blog', 'url' => null]]" />
    <section class="section-block pt-0">
        <div class="container">
            <div class="row g-3 g-lg-4">
                @forelse($posts as $post)
                    <div class="col-md-6">
                        <article class="content-shell p-3 p-md-4 h-100">
                            <h2 class="h5 mb-2"><a class="text-brand-navy text-decoration-none" href="{{ route('frontend.blog.show', $post->slug) }}">{{ $post->title }}</a></h2>
                            <p class="text-muted small mb-2">{{ optional($post->published_at)->format('d M Y') }}</p>
                            <p class="mb-3">{{ $post->excerpt }}</p>
                            <a href="{{ route('frontend.blog.show', $post->slug) }}" class="btn btn-sm btn-outline-brand-navy rounded-pill">Read article</a>
                        </article>
                    </div>
                @empty
                    <div class="col-12">
                        <x-ui.empty-state title="No blog posts published yet" message="Travel tips and planning stories will appear here once published." icon="bi-journal-richtext" />
                    </div>
                @endforelse
            </div>
            <x-ui.pagination :paginator="$posts" />
        </div>
    </section>
@endsection
