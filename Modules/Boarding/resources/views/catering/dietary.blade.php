<div>
    <h4 class="mb-1">{{ __('Dietary register') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Safeguarding, not preference. A medical-sourced requirement needs nurse verification before it is treated as clinical.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Type') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Verified') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($requirements as $req)
                                <tr class="{{ $req->severity === 'life_threatening' ? 'table-danger' : '' }}">
                                    <td>{{ $req->student->first_name }} {{ $req->student->last_name }}</td>
                                    <td>{{ ucfirst($req->requirement_type) }}</td>
                                    <td><span class="badge text-bg-{{ $req->severity === 'life_threatening' ? 'danger' : 'secondary' }}">{{ str_replace('_', ' ', $req->severity) }}</span></td>
                                    <td>{{ $req->verified_by_nurse ? __('Yes') : __('No') }}</td>
                                    <td class="text-end">
                                        @if (! $req->verified_by_nurse)
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="verify({{ $req->id }})">{{ __('Verify') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No requirements recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Record requirement') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Learner') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="requirementType">
                        <option value="allergy">{{ __('Allergy') }}</option>
                        <option value="intolerance">{{ __('Intolerance') }}</option>
                        <option value="medical">{{ __('Medical') }}</option>
                        <option value="religious">{{ __('Religious') }}</option>
                        <option value="ethical">{{ __('Ethical') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="severity">
                        <option value="preference">{{ __('Preference') }}</option>
                        <option value="moderate">{{ __('Moderate') }}</option>
                        <option value="severe">{{ __('Severe') }}</option>
                        <option value="life_threatening">{{ __('Life-threatening') }}</option>
                    </select>
                    <textarea class="form-control mb-2" wire:model="description" placeholder="{{ __('Description') }}"></textarea>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="requiresEpipen" id="requiresEpipen">
                        <label class="form-check-label" for="requiresEpipen">{{ __('Requires EpiPen') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
