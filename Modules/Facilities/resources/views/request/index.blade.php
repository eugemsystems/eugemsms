<div>
    <h4 class="mb-1">{{ __('Booking request') }}</h4>

    @if ($clashReason !== null)
        <div class="alert alert-danger py-2" style="max-width:40rem">{{ $clashReason }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Resource') }}</th><th>{{ __('Window') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($bookings as $booking)
                                <tr wire:key="req-booking-{{ $booking->id }}">
                                    <td>{{ $booking->resource->name }}</td>
                                    <td>{{ $booking->starts_at->format('d M H:i') }}–{{ $booking->ends_at->format('H:i') }}</td>
                                    <td>{{ $booking->booking_type }}</td>
                                    <td>{{ $booking->status }}</td>
                                    <td>
                                        @unless (in_array($booking->status, ['completed', 'cancelled', 'rejected'], true))
                                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="cancel({{ $booking->id }})">{{ __('Cancel') }}</button>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No bookings.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New booking') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="resourceId" wire:change="checkAvailability">
                        <option value="">{{ __('Resource') }}</option>
                        @foreach ($resources as $resource)
                            <option value="{{ $resource->id }}">{{ $resource->code }} — {{ $resource->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model.live="bookingType">
                        <option value="internal">{{ __('Internal') }}</option>
                        <option value="external">{{ __('External') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="purpose" placeholder="{{ __('Purpose') }}">
                    <input type="datetime-local" class="form-control mb-2" wire:model.live="startsAt" wire:change="checkAvailability">
                    <input type="datetime-local" class="form-control mb-2" wire:model.live="endsAt" wire:change="checkAvailability">

                    @if ($bookingType === 'external')
                        <input type="text" class="form-control mb-2" wire:model="hirerName" placeholder="{{ __('Hirer name') }}">
                        <input type="text" class="form-control mb-2" wire:model="hirerContact" placeholder="{{ __('Hirer contact') }}">
                        <input type="text" class="form-control mb-2" wire:model="hirerOrganisation" placeholder="{{ __('Hirer organisation (optional)') }}">
                    @endif

                    <select class="form-select mb-2" wire:model="recurrenceFrequency">
                        <option value="">{{ __('No recurrence') }}</option>
                        <option value="daily">{{ __('Daily') }}</option>
                        <option value="weekly">{{ __('Weekly') }}</option>
                    </select>
                    @if ($recurrenceFrequency !== null && $recurrenceFrequency !== '')
                        <input type="number" min="1" class="form-control mb-2" wire:model="recurrenceOccurrences" placeholder="{{ __('Occurrences') }}">
                    @endif

                    <button type="button" class="btn btn-outline-secondary btn-sm mb-2" wire:click="checkAvailability">{{ __('Check availability') }}</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="request">{{ __('Request booking') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
