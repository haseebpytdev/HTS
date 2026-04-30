@props([
    'title',
    'subtitle' => null,
])

<section {{ $attributes->class('section-block pt-0') }}>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <x-ui.section-heading :title="$title" :subtitle="$subtitle" class="mb-4" />
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
