<div>
    <h4 class="mb-1">{{ __('Live occupancy') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Present, not allocated — the same feed BRD-04 catering and OPS-06 muster both read.') }}</p>

    <input type="date" class="form-control mb-4" style="max-width: 220px" wire:model.live="date">

    <div class="card mb-4">
        <div class="card-body d-flex gap-4">
            <div><strong>{{ __('Allocated') }}:</strong> {{ $overall->allocated }}</div>
            <div><strong>{{ __('Present') }}:</strong> {{ $overall->present }}</div>
            <div><strong>{{ __('On exeat') }}:</strong> {{ $overall->onExeat }}</div>
            <div><strong>{{ __('Sick bay/hospital') }}:</strong> {{ $overall->inSickBay }}</div>
            <div><strong>{{ __('Missing') }}:</strong> {{ $overall->missing }}</div>
            @unless ($overall->rollCallAvailable)
                <span class="badge text-bg-secondary">{{ __('No completed roll call yet — showing nominal') }}</span>
            @endunless
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Hostel') }}</th><th>{{ __('Allocated') }}</th><th>{{ __('Present') }}</th><th>{{ __('Exeat') }}</th><th>{{ __('Sick bay') }}</th><th>{{ __('Missing') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['hostel']->code }}</td>
                            <td>{{ $row['occupancy']->allocated }}</td>
                            <td>{{ $row['occupancy']->present }}</td>
                            <td>{{ $row['occupancy']->onExeat }}</td>
                            <td>{{ $row['occupancy']->inSickBay }}</td>
                            <td>{{ $row['occupancy']->missing > 0 ? '⚠ '.$row['occupancy']->missing : 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
