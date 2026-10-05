<div>
    <h4 class="mb-1">{{ __('Bursar dashboard') }}</h4>
    <p class="text-body-secondary small">{{ __('Finance indicators against target. Open a report for the detail behind a figure.') }}</p>

    @include('intelligence::executive.partials.kpi-tiles', ['tiles' => $tiles])

    @if ($reportLinks !== [])
        <div class="card mt-4">
            <div class="card-header">{{ __('Finance reports') }}</div>
            <div class="list-group list-group-flush">
                @foreach ($reportLinks as $label => $url) <a href="{{ $url }}" class="list-group-item list-group-item-action" wire:navigate>{{ $label }}</a> @endforeach
            </div>
        </div>
    @endif
</div>
