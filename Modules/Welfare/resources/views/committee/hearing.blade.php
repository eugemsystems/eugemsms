<div>
    <h4 class="mb-1">{{ __('Disciplinary committee') }}</h4>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Record hearing') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="convenedOn">
                    <input type="text" class="form-control mb-2" wire:model="panelStaffIdsRaw" placeholder="{{ __('Panel staff ids, comma-separated') }}">

                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="learnerDeclined" wire:model.live="learnerDeclined">
                        <label class="form-check-label" for="learnerDeclined">{{ __('Learner declined to give a statement') }}</label>
                    </div>
                    @unless ($learnerDeclined)
                        <textarea class="form-control mb-2" wire:model="learnerStatement" placeholder="{{ __('Learner\'s own statement') }}"></textarea>
                    @endunless
                    <textarea class="form-control mb-2" wire:model="guardianStatement" placeholder="{{ __('Guardian statement (optional)') }}"></textarea>

                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="guardianPresent" wire:model="guardianPresent">
                        <label class="form-check-label" for="guardianPresent">{{ __('Guardian present') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="learnerPresent" wire:model="learnerPresent">
                        <label class="form-check-label" for="learnerPresent">{{ __('Learner present') }}</label>
                    </div>

                    <textarea class="form-control mb-2" wire:model="findings" placeholder="{{ __('Findings') }}"></textarea>
                    <select class="form-select mb-2" wire:model="decision">
                        <option value="no_case">{{ __('No case') }}</option>
                        <option value="warning">{{ __('Warning') }}</option>
                        <option value="sanction">{{ __('Sanction') }}</option>
                        <option value="referred_to_board">{{ __('Referred to board') }}</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Past hearings for this student') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Decision') }}</th></tr></thead>
                        <tbody>
                            @forelse ($hearings as $hearing)
                                <tr>
                                    <td>{{ $hearing->convened_on->toDateString() }}</td>
                                    <td>{{ str_replace('_', ' ', $hearing->decision) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('Select a student.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
