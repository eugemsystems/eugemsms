<div>
    <h4 class="mb-1">{{ __('Retention schedules') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Governs which tables hold personal data and how long. Safeguarding, medical and financial records carry longer retention and are excluded from routine disposal.') }}</p>

    <button type="button" class="btn btn-outline-info btn-sm mb-3" wire:click="checkCoverage">{{ __('Check table coverage') }}</button>

    @if ($coverageResult)
        <div class="alert {{ $coverageResult['uncovered'] === [] ? 'alert-success' : 'alert-warning' }} small">
            <strong>{{ __('Covered:') }}</strong> {{ implode(', ', $coverageResult['covered']) ?: '—' }}<br>
            <strong>{{ __('Uncovered:') }}</strong> {{ implode(', ', $coverageResult['uncovered']) ?: __('none') }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Record class') }}</th><th>{{ __('Tables') }}</th><th>{{ __('Years') }}</th><th>{{ __('Trigger') }}</th><th>{{ __('Disposal') }}</th></tr></thead>
                        <tbody>
                            @forelse ($schedules as $schedule)
                                <tr wire:key="sched-{{ $schedule->id }}">
                                    <td>{{ $schedule->record_class }}</td>
                                    <td class="small">{{ implode(', ', $schedule->table_names) }}</td>
                                    <td>{{ $schedule->retention_years }}</td>
                                    <td>{{ $schedule->retention_trigger }}</td>
                                    <td>{{ $schedule->disposal_method }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No retention schedules yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New retention schedule') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="recordClass" placeholder="{{ __('Record class, e.g. academic_record') }}">
                    <input type="text" class="form-control mb-2" wire:model="tableNamesText" placeholder="{{ __('Table names, comma-separated') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="retentionYears" placeholder="{{ __('Retention years') }}">
                    <select class="form-select mb-2" wire:model="retentionTrigger">
                        <option value="record_created">{{ __('Record created') }}</option>
                        <option value="learner_exit">{{ __('Learner exit') }}</option>
                        <option value="staff_exit">{{ __('Staff exit') }}</option>
                        <option value="case_closed">{{ __('Case closed') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="disposalMethod">
                        <option value="delete">{{ __('Delete') }}</option>
                        <option value="anonymise">{{ __('Anonymise') }}</option>
                        <option value="archive">{{ __('Archive') }}</option>
                    </select>
                    <textarea class="form-control mb-2" wire:model="legalBasis" placeholder="{{ __('Legal basis') }}"></textarea>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="requiresReview" id="retRequiresReview">
                        <label class="form-check-label small" for="retRequiresReview">{{ __('Requires human review before disposal') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
