<div>
    <h4 class="mb-1">{{ __('National submission export') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Run the validation report first. The exported document uses your school\'s own configurable template — there is no hard-coded Ministry layout.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="instrumentId">
                <option value="">{{ __('Select instrument') }}</option>
                @foreach ($instruments as $instrument)
                    <option value="{{ $instrument->id }}">{{ $instrument->code }} — {{ $instrument->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-select" wire:model.live="academicYearId">
                <option value="">{{ __('Select academic year') }}</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <button type="button" class="btn btn-outline-primary" wire:click="validateSubmission">{{ __('Run validation report') }}</button>
        </div>
    </div>

    @if ($issues !== null)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ __('Validation report') }}</span>
                <span class="badge text-bg-{{ $errorCount > 0 ? 'danger' : 'success' }}">{{ $errorCount }} {{ __('error(s)') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Admission no') }}</th><th>{{ __('Field') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Message') }}</th></tr></thead>
                    <tbody>
                        @forelse ($issues as $issue)
                            <tr>
                                <td>{{ $issue['student_name'] }}</td>
                                <td>{{ $issue['admission_number'] }}</td>
                                <td>{{ $issue['field'] }}</td>
                                <td><span class="badge text-bg-{{ $issue['severity'] === 'error' ? 'danger' : 'warning' }}">{{ ucfirst($issue['severity']) }}</span></td>
                                <td>{{ $issue['message'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No issues — the candidate set is clean.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-danger btn-sm" wire:click="export" @disabled(! $canExport)>{{ __('Export') }}</button>
                @if (! $canExport)
                    <span class="text-body-secondary small ms-2">{{ __('Resolve every error above before exporting.') }}</span>
                @endif
            </div>
        </div>
    @endif

    @if ($submissions->isNotEmpty())
        <div class="card">
            <div class="card-header">{{ __('Previous exports') }}</div>
            <ul class="list-group list-group-flush small">
                @foreach ($submissions as $submission)
                    <li class="list-group-item" wire:key="sub-{{ $submission->id }}">
                        {{ $submission->exported_at->format('d M Y H:i') }} — {{ $submission->candidate_count }} {{ __('candidate(s)') }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
