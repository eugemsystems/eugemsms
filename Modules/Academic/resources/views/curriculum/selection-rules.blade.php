<div>
    <h4 class="mb-1">{{ __('Subject selection rules') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Every subject-count limit is a row here — nothing is hard-coded.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Rules') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Level') }}</th><th>{{ __('Pathway') }}</th><th>{{ __('Min/Max') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Confirm') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rules as $rule)
                                <tr wire:key="rule-{{ $rule->id }}">
                                    <td>{{ str_replace('_', ' ', $rule->rule_type) }}</td>
                                    <td>{{ $rule->grade_level_id ? optional($gradeLevels->firstWhere('id', $rule->grade_level_id))->name : __('All') }}</td>
                                    <td>{{ $rule->pathway ?? __('Any') }}</td>
                                    <td>{{ $rule->min_count ?? '—' }} / {{ $rule->max_count ?? '—' }}</td>
                                    <td><span class="badge text-bg-{{ $rule->severity === 'block' ? 'danger' : 'warning' }}">{{ ucfirst($rule->severity) }}</span></td>
                                    <td>{{ $rule->requires_confirmation ? '⚠' : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No rules yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Live tester') }}</div>
                <div class="card-body">
                    <p class="text-body-secondary small">{{ __('Pick a level, a pathway, and a subject set — runs through the exact same engine EnrolSubjectAction enforces.') }}</p>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <select class="form-select form-select-sm" wire:model="testerGradeLevelId">
                                <option value="">{{ __('Any level') }}</option>
                                @foreach ($gradeLevels as $gradeLevel)
                                    <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control form-control-sm" wire:model="testerPathway" placeholder="{{ __('Pathway code (optional)') }}">
                        </div>
                    </div>
                    <div class="row g-1 mb-2" style="max-height: 180px; overflow-y: auto;">
                        @foreach ($subjects as $subject)
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" wire:model="testerSubjectIds" value="{{ $subject->id }}" id="tester-subject-{{ $subject->id }}">
                                    <label class="form-check-label small" for="tester-subject-{{ $subject->id }}">{{ $subject->name }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="runTest">{{ __('Run test') }}</button>

                    @if ($testResult !== null)
                        <div class="alert alert-{{ $testResult['isValid'] ? 'success' : 'danger' }} mt-3 mb-0">
                            <strong>{{ $testResult['isValid'] ? __('Selection passes.') : __('Selection blocked.') }}</strong>
                            @foreach ($testResult['blocks'] as $blockMessage)
                                <div>⛔ {{ $blockMessage }}</div>
                            @endforeach
                            @foreach ($testResult['warnings'] as $warningMessage)
                                <div>⚠ {{ $warningMessage }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New rule') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="frameworkId">
                                        @foreach ($frameworks as $framework)
                                            <option value="{{ $framework->id }}">{{ $framework->code }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Framework') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="ruleType">
                                        <option value="min_total">{{ __('Min total') }}</option>
                                        <option value="max_total">{{ __('Max total') }}</option>
                                        <option value="min_from_group">{{ __('Min from group') }}</option>
                                        <option value="max_from_group">{{ __('Max from group') }}</option>
                                        <option value="required_subject">{{ __('Required subject') }}</option>
                                        <option value="mutually_exclusive">{{ __('Mutually exclusive') }}</option>
                                        <option value="min_compulsory">{{ __('Min compulsory') }}</option>
                                        <option value="one_per_option_block">{{ __('One per option block') }}</option>
                                        <option value="prerequisite">{{ __('Prerequisite') }}</option>
                                    </select>
                                    <label>{{ __('Rule type') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="gradeLevelId">
                                        <option value="">{{ __('All levels') }}</option>
                                        @foreach ($gradeLevels as $gradeLevel)
                                            <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Grade level (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="pathway" placeholder=" ">
                                    <label>{{ __('Pathway code (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="subjectGroupId">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($groups as $group)
                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject group (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="severity">
                                        <option value="block">{{ __('Block') }}</option>
                                        <option value="warn">{{ __('Warn') }}</option>
                                    </select>
                                    <label>{{ __('Severity') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="minCount" placeholder=" ">
                                    <label>{{ __('Min count (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="maxCount" placeholder=" ">
                                    <label>{{ __('Max count (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('message') is-invalid @enderror" wire:model="message" placeholder=" ">
                                    <label>{{ __('Message shown to the user') }}</label>
                                    @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="sourceReference" placeholder=" ">
                                    <label>{{ __('Source reference (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" wire:model="requiresConfirmation">
                                    <label class="form-check-label">{{ __('Requires confirmation (⚠ unconfirmed figure)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create rule') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
