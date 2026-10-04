<div>
    <h4 class="mb-1">{{ __('Duty rosters') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }} — {{ __('current term') }}</p>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('New roster') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <input type="text" class="form-control form-control-sm @error('rosterName') is-invalid @enderror" wire:model="rosterName" placeholder="{{ __('Name') }}">
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" wire:model="dutyType">
                                <option value="teacher_on_duty">{{ __('Teacher on duty') }}</option>
                                <option value="boarding_master">{{ __('Boarding master') }}</option>
                                <option value="weekend_duty">{{ __('Weekend duty') }}</option>
                                <option value="prep_supervision">{{ __('Prep supervision') }}</option>
                                <option value="dining_hall">{{ __('Dining hall') }}</option>
                                <option value="assembly">{{ __('Assembly') }}</option>
                                <option value="invigilation">{{ __('Invigilation') }}</option>
                                <option value="transport_escort">{{ __('Transport escort') }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" wire:model="rotationPattern">
                                <option value="daily">{{ __('Daily') }}</option>
                                <option value="weekly">{{ __('Weekly') }}</option>
                                <option value="weekend">{{ __('Weekend') }}</option>
                                <option value="custom">{{ __('Custom') }}</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="createRoster">{{ __('Create roster') }}</button>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">{{ __('Generate assignments') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <select class="form-select form-select-sm @error('selectedRosterId') is-invalid @enderror" wire:model.live="selectedRosterId">
                                <option value="">{{ __('Select a roster') }}</option>
                                @foreach ($rosters as $roster)
                                    <option value="{{ $roster->id }}">{{ $roster->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control form-control-sm" wire:model="generateFrom">
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control form-control-sm" wire:model="generateTo">
                        </div>
                        <div class="col-6">
                            <input type="time" class="form-control form-control-sm" wire:model="dailyStartTime">
                        </div>
                        <div class="col-6">
                            <input type="time" class="form-control form-control-sm" wire:model="dailyEndTime">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="generate">{{ __('Generate (one slot per day)') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Swap a duty') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <select class="form-select form-select-sm @error('swapAssignmentId') is-invalid @enderror" wire:model="swapAssignmentId">
                                <option value="">{{ __('Select the assignment to swap') }}</option>
                                @foreach ($assignments as $assignment)
                                    @if ($assignment->status === 'assigned')
                                        <option value="{{ $assignment->id }}">{{ $assignment->staff?->fullName() }} — {{ $assignment->starts_at->format('d M Y H:i') }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <select class="form-select form-select-sm @error('swapNewStaffId') is-invalid @enderror" wire:model="swapNewStaffId">
                                <option value="">{{ __('Taking it over') }}</option>
                                @foreach ($staffList as $member)
                                    <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 form-check">
                            <input type="checkbox" class="form-check-input" id="swapConsented" wire:model="swapConsented">
                            <label class="form-check-label small" for="swapConsented">{{ __('Both parties have consented (BR-PPL-04-016)') }}</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="swap">{{ __('Swap') }}</button>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Assignments for the selected roster') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->staff?->fullName() }}</td>
                                    <td>{{ $assignment->starts_at->format('d M Y H:i') }}</td>
                                    <td>{{ $assignment->ends_at->format('d M Y H:i') }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($assignment->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('Select a roster to see its assignments.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
