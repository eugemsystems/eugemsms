<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('insights.reports.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Shared with me') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Each report runs against your own permissions, so you may see fewer columns than its author does.') }}</p>
        </div>
    </div>

    @if ($error) <div class="alert alert-danger small">{{ $error }}</div> @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Report') }}</th><th>{{ __('Shared by') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr wire:key="sh-{{ $report->id }}">
                            <td>{{ $report->name }}</td>
                            <td class="small">{{ $report->creator?->name }}</td>
                            <td class="text-end"><button type="button" class="btn btn-xs btn-outline-primary" wire:click="run({{ $report->id }})">{{ __('Run') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('Nothing has been shared with you.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('intelligence::insights.reports.partials.result', ['result' => $result])
</div>
