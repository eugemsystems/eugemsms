<div>
    <h4 class="mb-1">{{ __('Invigilation') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A teacher of the subject being examined is excluded from invigilating it unless explicitly overridden.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Venue') }}</th><th>{{ __('Staff') }}</th><th>{{ __('Role') }}</th><th>{{ __('Confirmed') }}</th></tr></thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr wire:key="assignment-{{ $assignment->id }}">
                                    <td>{{ $assignment->venue?->name }}</td>
                                    <td>{{ $assignment->staff?->first_name }} {{ $assignment->staff?->last_name }}</td>
                                    <td>{{ ucfirst($assignment->role) }}</td>
                                    <td>{{ $assignment->confirmed ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No invigilators assigned yet for this paper.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Assign invigilator') }}</div>
                <div class="card-body">
                    <form wire:submit="assign">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model.live="paperId">
                                        <option value="">{{ __('Select paper') }}</option>
                                        @foreach ($papers as $paper)
                                            <option value="{{ $paper->id }}">{{ $paper->subject?->name }} — {{ $paper->paper_name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Paper') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="venueId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($venues as $venue)
                                            <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Venue') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="staffId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($staff as $member)
                                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Staff') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="role">
                                        <option value="chief">{{ __('Chief') }}</option>
                                        <option value="assistant">{{ __('Assistant') }}</option>
                                        <option value="relief">{{ __('Relief') }}</option>
                                        <option value="runner">{{ __('Runner') }}</option>
                                    </select>
                                    <label>{{ __('Role') }}</label>
                                </div>
                            </div>
                            <div class="col-12 form-check">
                                <input type="checkbox" class="form-check-input" wire:model="overrideExclusion" id="overrideExclusion">
                                <label class="form-check-label" for="overrideExclusion">{{ __('Override subject-teacher exclusion (staffing does not permit avoidance)') }}</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Assign') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
