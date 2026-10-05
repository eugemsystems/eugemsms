<div>
    <h4 class="mb-1">{{ __('MoPSE / EMIS returns') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Data is frozen the moment a return is first generated — a later regeneration shows the frozen snapshot plus any divergence, never a silently replaced figure.') }}</p>

    <button type="button" class="btn btn-outline-info btn-sm mb-3" wire:click="runQualityChecks">{{ __('Run data quality checks') }}</button>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Return') }}</th><th>{{ __('Period') }}</th><th>{{ __('Due') }}</th><th>{{ __('Quality issues') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($returns as $return)
                                <tr wire:key="return-{{ $return->id }}">
                                    <td>{{ str_replace('_', ' ', $return->return_type) }}</td>
                                    <td>{{ $return->period_reference }}</td>
                                    <td>{{ $return->due_date->toDateString() }}</td>
                                    <td>{{ $return->quality_issues }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $return->status }}</span></td>
                                    <td class="text-end">
                                        @if ($return->status === 'validated' || $return->status === 'pending')
                                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export({{ $return->id }})">{{ __('Export') }}</button>
                                        @endif
                                        @if ($return->export_file_id && $return->submitted_at === null)
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="recordSubmission({{ $return->id }})">{{ __('Record submission') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No returns generated yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Data quality checks (last run)') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Check') }}</th><th>{{ __('Affected') }}</th><th>{{ __('Severity') }}</th></tr></thead>
                        <tbody>
                            @forelse ($qualityChecks as $check)
                                <tr><td>{{ $check->check_key }}</td><td>{{ $check->affected_count }}</td><td><span class="badge {{ $check->severity === 'error' ? 'bg-danger' : 'bg-warning text-dark' }}">{{ $check->severity }}</span></td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No checks run yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Generate return') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="returnType">
                        <option value="annual_schools_census">{{ __('Annual Schools Census') }}</option>
                        <option value="term_enrolment">{{ __('Term enrolment') }}</option>
                        <option value="staff_establishment">{{ __('Staff establishment') }}</option>
                        <option value="infrastructure">{{ __('Infrastructure') }}</option>
                        <option value="inspection_pack">{{ __('Inspection pack') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="periodReference" placeholder="{{ __('Period reference, e.g. 2026-T1') }}">
                    <input type="date" class="form-control mb-2" wire:model="dueDate">
                    <input type="text" class="form-control mb-2" wire:model="authority" placeholder="{{ __('Authority') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="generate">{{ __('Generate') }}</button>

                    <hr>
                    <label class="form-label small mb-0">{{ __('Acknowledgement reference (for submission)') }}</label>
                    <input type="text" class="form-control" wire:model="acknowledgementRef" placeholder="{{ __('Optional') }}">
                </div>
            </div>
        </div>
    </div>
</div>
