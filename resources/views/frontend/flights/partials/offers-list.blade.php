@foreach($offers as $offer)
    <div class="col-12">
        @include('frontend.flights.partials.result-card', [
            'offer' => $offer,
            'driver' => $driver,
            'loopIndex' => $loop->index,
        ])
    </div>
@endforeach
