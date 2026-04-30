@extends('layouts.frontend')

@section('title', $page->title)

@section('content')
    <x-frontend.page-hero :title="$page->title" :subtitle="$page->excerpt ?? 'Landing page'" :breadcrumbs="[['label' => 'Home', 'url' => route('frontend.home')], ['label' => $page->title, 'url' => null]]" />
    <section class="section-block pt-0">
        <div class="container">
            <article class="content-shell p-3 p-md-4">
                <div>{!! nl2br(e($page->body ?? '')) !!}</div>
            </article>
        </div>
    </section>
@endsection
