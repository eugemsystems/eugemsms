<div>
    <h4 class="mb-1">{{ __('Learner transport assignment') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Assigning a pickup stop sets the zone, which drives the termly transport fee.') }}</p>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New assignment') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model.live="routeId">
                        <option value="">{{ __('Route') }}</option>
                        @foreach ($routes as $route)
                            <option value="{{ $route->id }}">{{ $route->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model.live="pickupStopId">
                        <option value="">{{ __('Pickup stop') }}</option>
                        @foreach ($stops as $stop)
                            <option value="{{ $stop->id }}">{{ $stop->name }}</option>
                        @endforeach
                    </select>

                    @if ($previewFeeMinor !== null)
                        <div class="alert alert-info py-2">{{ __('Resulting termly transport fee:') }} <strong>{{ number_format($previewFeeMinor / 100, 2) }}</strong></div>
                    @endif

                    <select class="form-select mb-2" wire:model="direction">
                        <option value="morning">{{ __('Morning') }}</option>
                        <option value="afternoon">{{ __('Afternoon') }}</option>
                        <option value="both">{{ __('Both') }}</option>
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="effectiveFrom">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="authorisedByGuardian" wire:model="authorisedByGuardian">
                        <label class="form-check-label" for="authorisedByGuardian">{{ __('Guardian has authorised this placement') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="overrideCapacity" wire:model="overrideCapacity">
                        <label class="form-check-label" for="overrideCapacity">{{ __('Override route capacity') }}</label>
                    </div>
                    @if ($overrideCapacity)
                        <input type="text" class="form-control mb-2" wire:model="overrideReason" placeholder="{{ __('Override reason') }}">
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="assign">{{ __('Assign') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">{{ __('Active assignments') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Route') }}</th><th>{{ __('Zone') }}</th><th>{{ __('Since') }}</th></tr></thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr wire:key="assign-{{ $assignment->id }}">
                                    <td>#{{ $assignment->student_id }}</td>
                                    <td>{{ $assignment->route->name }}</td>
                                    <td>{{ $assignment->zone->code }}</td>
                                    <td>{{ $assignment->effective_from->toFormattedDateString() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No active assignments.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
