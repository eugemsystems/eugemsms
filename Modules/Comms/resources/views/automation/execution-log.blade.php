<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.automation.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Execution log') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Every attempt is recorded — sent, throttled, or considered and not matched. Showing the latest 100.') }}</p>
        </div>
    </div>

    <select class="form-select form-select-sm mb-3 w-auto" wire:model.live="ruleId">
        <option value="">{{ __('All rules') }}</option>
        @foreach ($rules as $rule) <option value="{{ $rule->id }}">{{ $rule->name }}</option> @endforeach
    </select>

    <div class="row g-3 mb-3">
        <div class="col-4"><div class="card"><div class="card-body py-2"><div class="small text-body-secondary">{{ __('Sent') }}</div><div class="h5 mb-0">{{ $sent }}</div></div></div></div>
        <div class="col-4"><div class="card"><div class="card-body py-2"><div class="small text-body-secondary">{{ __('Throttled') }}</div><div class="h5 mb-0">{{ $throttled }}</div></div></div></div>
        <div class="col-4"><div class="card"><div class="card-body py-2"><div class="small text-body-secondary">{{ __('Not matched') }}</div><div class="h5 mb-0">{{ $notMatched }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Source') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Outcome') }}</th><th>{{ __('Variant') }}</th></tr></thead>
                <tbody>
                    @forelse ($executions as $execution)
                        <tr wire:key="exec-{{ $execution->id }}">
                            <td class="small">{{ $execution->executed_at?->toDateTimeString() }}</td>
                            <td>{{ $execution->trigger_source }}</td>
                            <td>{{ $execution->subject_type }} #{{ $execution->subject_id }}</td>
                            <td>
                                @if ($execution->matched && $execution->notification_id) <span class="badge bg-label-success">{{ __('sent') }}</span>
                                @elseif ($execution->skip_reason === 'throttled') <span class="badge bg-label-warning">{{ __('throttled') }}</span>
                                @elseif (! $execution->matched) <span class="badge bg-label-secondary">{{ __('not matched') }}</span>
                                @else <span class="badge bg-label-info">{{ $execution->skip_reason ?? __('matched') }}</span> @endif
                            </td>
                            <td>{{ $execution->variant_key ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Nothing recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
