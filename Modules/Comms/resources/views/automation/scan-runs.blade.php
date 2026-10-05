<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.automation.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Scan history') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('One row per scheduled-scan run, whatever its outcome. Showing the latest 100.') }}</p>
        </div>
    </div>

    @foreach ($zeroMatchRuleIds as $ruleId)
        <div class="alert alert-warning small">{{ __('“:name” matched nothing in its last three runs — check its conditions.', ['name' => $runs->firstWhere('rule_id', $ruleId)?->rule?->name]) }} ⚠</div>
    @endforeach

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Ran') }}</th><th>{{ __('Rule') }}</th><th class="text-end">{{ __('Scanned') }}</th><th class="text-end">{{ __('Matched') }}</th><th class="text-end">{{ __('Dispatched') }}</th><th class="text-end">{{ __('ms') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr wire:key="run-{{ $run->id }}">
                            <td class="small">{{ $run->ran_at?->toDateTimeString() }}</td>
                            <td>{{ $run->rule?->name }}</td>
                            <td class="text-end">{{ $run->records_scanned ?? '—' }}</td>
                            <td class="text-end">{{ $run->records_matched ?? '—' }}</td>
                            <td class="text-end">{{ $run->notifications_dispatched ?? '—' }}</td>
                            <td class="text-end">{{ $run->duration_ms }}</td>
                            <td>
                                <span class="badge {{ $run->status === 'completed' ? 'bg-label-success' : 'bg-label-danger' }}">{{ $run->status }}</span>
                                @if ($run->error) <div class="small text-danger">{{ $run->error }}</div> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No scans have run yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
