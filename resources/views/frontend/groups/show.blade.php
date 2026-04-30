@extends('layouts.frontend')

@section('title', $group->name)

@section('content')
    <x-frontend.page-hero
        :title="$group->name"
        :subtitle="trim((optional($group->package)->title ?: 'Package not linked').($group->group_type ? ' · '.$group->group_type : ''))"
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Groups', 'url' => route('frontend.groups.index')],
            ['label' => $group->name, 'url' => null],
        ]"
    />

    <section class="section-block pt-0">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <x-ui.form-section-card title="Group snapshot">
                        <div class="d-flex flex-wrap gap-2">
                            <x-ui.badge tone="primary">{{ ucfirst($group->status) }}</x-ui.badge>
                            @if($group->departure_date)
                                <x-ui.badge tone="muted">Departure: {{ $group->departure_date->format('d M Y') }}</x-ui.badge>
                            @endif
                            @if($group->return_date)
                                <x-ui.badge tone="muted">Return: {{ $group->return_date->format('d M Y') }}</x-ui.badge>
                            @endif
                        </div>
                    </x-ui.form-section-card>
                </div>
                <div class="col-lg-4">
                    <div class="content-shell p-4 h-100 small">
                        <div class="mb-2"><span class="text-secondary">Seats left:</span> <strong class="text-brand-navy">{{ $group->seats_left ?? '-' }}</strong></div>
                        <div class="mb-2"><span class="text-secondary">Capacity:</span> <strong class="text-brand-navy">{{ $group->capacity ?? '-' }}</strong></div>
                        <div class="mb-0"><span class="text-secondary">Destination:</span> <strong class="text-brand-navy">{{ optional(optional($group->package)->destination)->name ?? 'TBA' }}</strong></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    @if($group->images->isNotEmpty())
                        <div class="content-shell p-4 mb-4">
                            <h2 class="h5 text-brand-navy fw-bold mb-3">Gallery</h2>
                            <div class="row g-2">
                                @foreach($group->images as $img)
                                    <div class="col-6 col-md-4">
                                        <img src="{{ \App\Support\Media::url($img->image_path) }}" class="img-fluid rounded-3 shadow-sm w-100" alt="{{ $img->alt_text ?? $group->name }}" style="height: 160px; object-fit: cover;">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="content-shell p-4">
                        <h2 class="h5 text-brand-navy fw-bold mb-3">Group notes</h2>
                        <p class="text-secondary mb-0">{!! nl2br(e($group->notes ?: 'No additional notes available.')) !!}</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <x-ui.quote-request-card class="sticky-lg-top" style="top: 1rem;" title="Send inquiry" subtitle="Share your traveler details to hold seats or request callback support.">
                        <h2 class="h5 text-brand-navy fw-bold mb-3">Send inquiry</h2>
                        <form method="POST" action="{{ route('frontend.inquiries.store-group') }}">
                            @csrf
                            <x-forms.inquiry-form-fields :group="$group" />
                            <button type="submit" class="btn btn-brand-green text-white w-100 rounded-pill mt-3">Submit inquiry</button>
                        </form>
                    </x-ui.quote-request-card>
                </div>
            </div>
        </div>
    </section>

    @if($relatedGroups->isNotEmpty())
        <section class="section-block section-block--muted">
            <div class="container">
                <h2 class="section-title h5 mb-3">Related groups</h2>
                <div class="row g-3">
                    @foreach($relatedGroups as $related)
                        <div class="col-md-6 col-xl-3">
                            <x-cards.public-group-card :group="$related" />
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
