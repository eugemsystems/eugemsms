<div>
    <h4 class="mb-1">{{ __('Consultation windows') }}</h4>
    <p class="text-body-secondary small">{{ __('Set when a teacher is available for parent consultations. Parents book slots through the portal, first come first served; cancelling a booking here frees its slot.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Event') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Available') }}</th><th class="text-end">{{ __('Booked') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($windows as $window)
                                <tr wire:key="win-{{ $window->id }}" class="{{ $selectedWindowId === $window->id ? 'table-active' : '' }}">
                                    <td>{{ $window->event_name }}</td>
                                    <td class="small">{{ $staffNames->get($window->staff_id)?->fullName() }}</td>
                                    <td class="small">{{ $window->available_from->format('D j M H:i') }} – {{ $window->available_to->format('H:i') }} ({{ $window->slot_duration_minutes }} {{ __('min slots') }})</td>
                                    <td class="text-end">{{ $window->booked_count }}</td>
                                    <td class="text-end"><button type="button" class="btn btn-xs btn-outline-secondary" wire:click="$set('selectedWindowId', {{ $window->id }})">{{ __('Bookings') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No windows yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($selectedWindowId)
                <div class="card">
                    <div class="card-header">{{ __('Bookings') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Slot') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($bookings as $booking)
                                    <tr wire:key="bk-{{ $booking->id }}">
                                        <td>{{ $booking->slot_starts_at->format('D j M H:i') }}</td>
                                        <td><span class="badge {{ $booking->status === 'booked' ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $booking->status }}</span></td>
                                        <td class="text-end">@if ($booking->status === 'booked') <button type="button" class="btn btn-xs btn-outline-danger" wire:click="cancelBooking({{ $booking->id }})" wire:confirm="{{ __('Cancel this booking?') }}">{{ __('Cancel') }}</button> @endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('Nothing booked yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New availability window') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="staffId">
                        <option value="">{{ __('Teacher…') }}</option>
                        @foreach ($staff as $member) <option value="{{ $member->id }}">{{ $member->fullName() }}</option> @endforeach
                    </select>
                    @error('staffId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="eventName" placeholder="{{ __('Event, e.g. Term 2 Parents Evening') }}">
                    @error('eventName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <label class="form-label small mb-0">{{ __('Available from') }}</label>
                    <input type="datetime-local" class="form-control mb-2" wire:model="availableFrom">
                    <label class="form-label small mb-0">{{ __('Available to') }}</label>
                    <input type="datetime-local" class="form-control mb-2" wire:model="availableTo">
                    @error('availableFrom') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @error('availableTo') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <label class="form-label small mb-0">{{ __('Slot length (minutes)') }}</label>
                    <input type="number" class="form-control mb-2" wire:model="slotDurationMinutes" min="5" max="60">
                    <label class="form-label small mb-0">{{ __('Booking opens (optional)') }}</label>
                    <input type="datetime-local" class="form-control mb-2" wire:model="bookingOpensAt">
                    <label class="form-label small mb-0">{{ __('Booking closes (optional)') }}</label>
                    <input type="datetime-local" class="form-control mb-2" wire:model="bookingClosesAt">
                    @error('bookingClosesAt') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createWindow">{{ __('Create window') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
