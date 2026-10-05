<div>
    <h4 class="mb-1">{{ __('ZIMSEC candidate validation') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Bio-data errors are fixed at source and re-derived — never edited here. Subject-count/pathway errors reuse ACA-01 rules directly.') }}</p>

    <div class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <select class="form-select" wire:model="registrationId">
                <option value="0">{{ __('Select registration') }}</option>
                @foreach ($registrations as $registration)
                    <option value="{{ $registration->id }}">{{ $registration->exam_level }} — {{ $registration->exam_series }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary btn-sm" wire:click="validateCandidates">{{ __('Run validation') }}</button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Status') }}</th><th>{{ __('Errors / Warnings') }}</th></tr></thead>
                        <tbody>
                            @forelse ($candidates as $candidate)
                                <tr wire:key="cand-{{ $candidate->id }}">
                                    <td>{{ $candidate->surname }}, {{ $candidate->forenames }}</td>
                                    <td>
                                        <span class="badge {{ match ($candidate->validation_status) { 'valid' => 'bg-success', 'warnings' => 'bg-warning text-dark', 'errors' => 'bg-danger', default => 'bg-light text-dark border' } }}">
                                            {{ $candidate->validation_status }}
                                        </span>
                                    </td>
                                    <td class="small">
                                        @foreach ($candidate->validation_errors ?? [] as $error)
                                            <div class="{{ $error['severity'] === 'error' ? 'text-danger' : 'text-warning-emphasis' }}">{{ $error['field'] }}: {{ $error['message'] }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No candidates for this registration.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('Add validation rule') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="ruleField" placeholder="{{ __('Field, e.g. national_registration_no') }}">
                    <select class="form-select mb-2" wire:model="ruleType">
                        <option value="required">{{ __('Required') }}</option>
                        <option value="format">{{ __('Format (regex)') }}</option>
                        <option value="min_count">{{ __('Minimum count') }}</option>
                        <option value="max_count">{{ __('Maximum count') }}</option>
                        <option value="allowed_values">{{ __('Allowed values') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="ruleValue" placeholder="{{ __('Rule value (optional)') }}">
                    <select class="form-select mb-2" wire:model="ruleSeverity">
                        <option value="error">{{ __('Error — blocks export') }}</option>
                        <option value="warning">{{ __('Warning — flags only') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="ruleExamLevel" placeholder="{{ __('Exam level (optional, blank = all)') }}">
                    <input type="text" class="form-control mb-2" wire:model="ruleMessage" placeholder="{{ __('Message shown to the user') }}">
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="createRule">{{ __('Save rule') }}</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Active rules') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($rules as $rule)
                        <li class="list-group-item small">{{ $rule->field }} — {{ $rule->rule_type }} ({{ $rule->severity }}){{ $rule->exam_level ? ' · '.$rule->exam_level : '' }}</li>
                    @empty
                        <li class="list-group-item text-body-secondary small">{{ __('No rules configured yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
