@props([
    'headers' => [],
    'rows' => [],
    'emptyTitle' => 'No records found',
    'emptyMessage' => 'Try adjusting filters or add a new record.',
])

<div class="d-none d-md-block">
    <x-ui.table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </x-ui.table>
</div>

<div class="d-md-none">
    @forelse($rows as $row)
        <article class="content-shell p-3 mb-2">
            {!! $row !!}
        </article>
    @empty
        <x-ui.empty-state :title="$emptyTitle" :message="$emptyMessage" />
    @endforelse
</div>
