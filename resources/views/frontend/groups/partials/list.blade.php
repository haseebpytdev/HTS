<div class="row g-3">
    @forelse($groups as $group)
        <div class="col-md-6 col-xl-4">
            <x-cards.public-group-card :group="$group" />
        </div>
    @empty
        <div class="col-12">
            <x-ui.empty-state
                title="No group tickets found"
                message="Try another date range or destination, or request a manual group quote."
                icon="bi-people"
            />
        </div>
    @endforelse
</div>
