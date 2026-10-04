<div>
    <h4 class="mb-1">{{ __('Visiting days') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Slot booking prevents overcrowding — refused once a slot\'s party limit is reached.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Visiting days') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Name') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Bookings') }}</th></tr></thead>
                        <tbody>
                            @forelse ($days as $day)
                                <tr>
                                    <td>{{ $day->visit_date->toDateString() }}</td>
                                    <td>{{ $day->name }}</td>
                                    <td>{{ $day->starts_at }} - {{ $day->ends_at }}</td>
                                    <td>{{ $day->bookings->count() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No visiting days yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('New visiting day') }}</div>
                <div class="card-body">
                    <input type="date" class="form-control mb-2" wire:model="visitDate">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="time" class="form-control" wire:model="startsAt"></div>
                        <div class="col-6"><input type="time" class="form-control" wire:model="endsAt"></div>
                    </div>
                    <input type="number" class="form-control mb-2" wire:model="maxPerSlot" placeholder="{{ __('Max per slot (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createDay">{{ __('Create') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Book a slot') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="bookVisitingDayId">
                        <option value="">{{ __('Visiting day') }}</option>
                        @foreach ($days as $day)
                            <option value="{{ $day->id }}">{{ $day->name }} ({{ $day->visit_date->toDateString() }})</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="bookStudentId">
                        <option value="">{{ __('Learner') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="bookGuardianId">
                        <option value="">{{ __('Guardian') }}</option>
                        @foreach ($guardians as $guardian)
                            <option value="{{ $guardian->id }}">{{ $guardian->displayName() }}</option>
                        @endforeach
                    </select>
                    <input type="time" class="form-control mb-2" wire:model="slotStartsAt">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="bookSlot">{{ __('Book') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
