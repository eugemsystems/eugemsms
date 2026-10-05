<div>
    <h4 class="mb-1">{{ __('Driver manifest — today') }}</h4>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Today\'s route trips') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($trips as $trip)
                        <li class="list-group-item">
                            <a href="javascript:void(0)" wire:click="selectTrip({{ $trip->id }})">{{ $trip->trip_date->toFormattedDateString() }} — {{ $trip->trip_type }}</a>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No route trips today.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">{{ __('Manifest') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($passengers as $passenger)
                                <tr wire:key="pax-{{ $passenger->id }}">
                                    <td>{{ $passenger->student?->first_name }} {{ $passenger->student?->last_name }}</td>
                                    <td><span class="badge text-bg-secondary">{{ $passenger->status }}</span></td>
                                    <td>
                                        @if ($passenger->status === 'expected')
                                            <button type="button" class="btn btn-sm btn-success" wire:click="board({{ $passenger->id }})">{{ __('Board') }}</button>
                                        @elseif ($passenger->status === 'boarded')
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="alight({{ $passenger->id }})">{{ __('Alight') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('Select a trip.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
