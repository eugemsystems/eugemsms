<div>
    <h4 class="mb-1">{{ __('Timetable editor') }}</h4>
    <p class="text-body-secondary mb-4">{{ $timetable->name }} — {{ ucfirst($timetable->status) }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Slots') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Day') }}</th><th>{{ __('Period') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Class') }}</th><th>{{ __('Venue') }}</th><th>{{ __('Locked') }}</th></tr></thead>
                        <tbody>
                            @forelse ($slots as $slot)
                                <tr wire:key="slot-{{ $slot->id }}">
                                    <td>{{ $slot->cycle_day }}</td>
                                    <td>{{ $slot->period_number }}</td>
                                    <td>{{ $slot->subject?->name }}</td>
                                    <td>{{ $slot->staff?->first_name }} {{ $slot->staff?->last_name }}</td>
                                    <td>{{ $slot->schoolClass?->name }}</td>
                                    <td>{{ $slot->venue?->name }}</td>
                                    <td>{{ $slot->is_locked ? __('Yes') : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No slots placed yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Place a lesson') }}</div>
                <div class="card-body">
                    <form wire:submit="addSlot">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="cycleDay" min="1">
                                    <label>{{ __('Cycle day') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="periodNumber" min="1">
                                    <label>{{ __('Period number') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="subjectId">
                                        <option value="">{{ __('Select subject') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="staffId">
                                        <option value="">{{ __('Select teacher') }}</option>
                                        @foreach ($staff as $member)
                                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Teacher') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="classId">
                                        <option value="">{{ __('No whole class') }}</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Class (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="venueId">
                                        <option value="">{{ __('No venue') }}</option>
                                        @foreach ($venues as $venue)
                                            <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Venue (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12 form-check">
                                <input type="checkbox" class="form-check-input" wire:model="isLocked" id="isLocked">
                                <label class="form-check-label" for="isLocked">{{ __('Lock this slot (pin — generator will not move it)') }}</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Place lesson') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
