<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('insights.reports.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Report execution log') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Every run, saved or not. Shows who ran what and how big it was — never the results. Latest 100.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Report') }}</th><th class="text-end">{{ __('Rows') }}</th><th class="text-end">{{ __('ms') }}</th><th>{{ __('Scope') }}</th></tr></thead>
                <tbody>
                    @forelse ($executions as $execution)
                        <tr wire:key="ex-{{ $execution->id }}">
                            <td class="small">{{ $execution->executed_at?->toDateTimeString() }}</td>
                            <td>{{ $execution->executor?->name }}</td>
                            <td>{{ $execution->report?->name ?? __('ad hoc') }}</td>
                            <td class="text-end">{{ $execution->row_count }}</td>
                            <td class="text-end">{{ $execution->duration_ms }}</td>
                            <td>@if ($execution->isConsolidated()) <span class="badge bg-label-info">{{ __(':n schools', ['n' => count($execution->schools_included)]) }}</span> @else {{ __('this school') }} @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('Nothing has been run yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
