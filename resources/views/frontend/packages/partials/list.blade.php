<div class="row g-3">
    @forelse($packages as $package)
        <div class="col-md-6 col-xl-4">
            <x-cards.public-package-card :package="$package" />
        </div>
    @empty
        <div class="col-12">
            <x-ui.empty-state
                title="No Umrah packages found"
                message="Try broader filters or contact Hayat Travel Solutions for custom package planning."
                icon="bi-suitcase2"
            />
        </div>
    @endforelse
</div>
