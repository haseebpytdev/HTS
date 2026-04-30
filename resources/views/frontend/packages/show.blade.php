@extends('layouts.frontend')

@section('title', $package->title)

@section('content')
    <x-frontend.page-hero
        :title="$package->title"
        :subtitle="$package->excerpt"
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('frontend.home')],
            ['label' => 'Packages', 'url' => route('frontend.packages.index')],
            ['label' => $package->title, 'url' => null],
        ]"
    />

    <section class="section-block pt-0">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="content-shell p-4 d-flex flex-wrap align-items-center gap-3">
                        <div class="fs-4 fw-bold text-brand-navy mb-0">
                            {{ number_format((float) $package->base_price, 0) }} {{ $package->currency }}
                        </div>
                        @if($package->duration_days)
                            <span class="badge rounded-pill text-bg-light border">{{ $package->duration_days }} days</span>
                        @endif
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="content-shell p-4 h-100 small">
                        <div class="mb-2"><span class="text-secondary">Destination:</span> <strong class="text-brand-navy">{{ $package->destination->name ?? 'TBA' }}</strong></div>
                        <div class="mb-2"><span class="text-secondary">Category:</span> <strong class="text-brand-navy">{{ $package->category->name ?? 'General' }}</strong></div>
                        <div class="mb-0"><span class="text-secondary">Reference:</span> <code class="small">{{ $package->slug }}</code></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    @if($package->images->isNotEmpty())
                        <div class="content-shell p-4 mb-4">
                            <h2 class="h5 text-brand-navy fw-bold mb-3">Gallery</h2>
                            <div class="row g-2">
                                @foreach($package->images as $img)
                                    <div class="col-6 col-md-4">
                                        <img src="{{ \App\Support\Media::url($img->image_path) }}" class="img-fluid rounded-3 shadow-sm w-100" alt="{{ $img->alt_text ?? $package->title }}" style="height: 160px; object-fit: cover;">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="content-shell p-4 mb-4">
                        <h2 class="h5 text-brand-navy fw-bold mb-3">Package details</h2>
                        <p class="text-secondary mb-0">{!! nl2br(e($package->description ?: 'Details will be updated soon.')) !!}</p>
                    </div>

                    <div class="content-shell p-4">
                        <h2 class="h5 text-brand-navy fw-bold mb-3">Upcoming departures</h2>
                        <x-ui.table class="mb-0">
                                <thead>
                                <tr class="small text-secondary">
                                    <th>Departure</th>
                                    <th>Return</th>
                                    <th>Seats left</th>
                                    <th>Price</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($package->departures as $departure)
                                    <tr>
                                        <td>{{ $departure->departure_date?->format('d M Y') }}</td>
                                        <td>{{ $departure->return_date?->format('d M Y') ?? '-' }}</td>
                                        <td><x-ui.badge tone="info">{{ $departure->seats_left ?? '-' }} seats</x-ui.badge></td>
                                        <td>{{ $departure->price ? number_format((float) $departure->price, 0).' '.$package->currency : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-secondary">
                                            <x-ui.empty-state title="No departures available yet" message="Please check back soon or send an inquiry for custom travel dates." icon="bi-calendar-event" />
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                        </x-ui.table>
                    </div>
                </div>

                <div class="col-lg-4">
                    <x-ui.quote-request-card class="sticky-lg-top" style="top: 1rem;" title="Send inquiry" subtitle="Get package details, hotel options, transport plan, and payment timeline.">
                        <h2 class="h5 text-brand-navy fw-bold mb-3">Send inquiry</h2>
                        <form method="POST" action="{{ route('frontend.inquiries.store-package') }}">
                            @csrf
                            <x-forms.inquiry-form-fields :package="$package" />
                            <button type="submit" class="btn btn-brand-green text-white w-100 rounded-pill mt-3">Submit inquiry</button>
                        </form>
                    </x-ui.quote-request-card>
                </div>
            </div>
        </div>
    </section>

    @if($relatedPackages->isNotEmpty())
        <section class="section-block section-block--muted">
            <div class="container">
                <h2 class="section-title h5 mb-3">Related packages</h2>
                <div class="row g-3">
                    @foreach($relatedPackages as $related)
                        <div class="col-md-6 col-xl-3">
                            <x-cards.public-package-card :package="$related" />
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
