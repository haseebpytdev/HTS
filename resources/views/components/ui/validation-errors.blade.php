@if ($errors->any())
    <x-ui.alert tone="danger" title="Please correct the highlighted fields:">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
