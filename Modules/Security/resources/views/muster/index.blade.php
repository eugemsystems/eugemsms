<div>
    <h4 class="mb-1">{{ __('Muster roll') }}</h4>

    @if ($activeDrillId === null)
        <div class="card" style="max-width:30rem">
            <div class="card-header">{{ __('Trigger a drill') }}</div>
            <div class="card-body">
                <select class="form-select mb-2" wire:model="drillType">
                    @foreach (['fire', 'evacuation', 'lockdown', 'medical', 'real_incident'] as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="isAnnounced" wire:model="isAnnounced">
                    <label class="form-check-label" for="isAnnounced">{{ __('Announced') }}</label>
                </div>
                <button type="button" class="btn btn-danger btn-sm" wire:click="trigger">{{ __('Trigger drill') }}</button>
            </div>
        </div>
    @else
        <div class="d-flex gap-2 mb-3 align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="refreshRoster">{{ __('Refresh') }}</button>
            <button type="button" class="btn btn-dark btn-sm" wire:click="complete">{{ __('Complete muster') }}</button>
            @if ($mustered !== null)
                <span class="badge bg-success">{{ __('Mustered') }}: {{ $mustered }}</span>
                <span class="badge {{ $unaccounted > 0 ? 'bg-danger' : 'bg-success' }}">{{ __('Unaccounted') }}: {{ $unaccounted }}</span>
            @endif
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Person') }}</th><th>{{ __('Assistance') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($roster as $row)
                            <tr wire:key="muster-{{ $row['personType'] }}-{{ $row['personId'] }}" class="{{ $row['needsAssistance'] ? 'table-warning' : '' }}">
                                <td>{{ $row['category'] }}</td>
                                <td>{{ $row['label'] }}</td>
                                <td>@if ($row['needsAssistance'])<span class="badge bg-warning text-dark">{{ __('Assistance') }}</span>@endif</td>
                                <td>
                                    @if ($row['marked'])
                                        <span class="badge bg-success">{{ __('Present') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('Unaccounted') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @unless ($row['marked'])
                                        <button type="button" class="btn btn-outline-success btn-sm" wire:click="mark('{{ $row['personType'] }}', {{ $row['personId'] }})">{{ __('Mark present') }}</button>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No one on the live roster.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
