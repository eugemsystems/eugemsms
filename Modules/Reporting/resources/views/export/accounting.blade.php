<div>
    <h4 class="mb-1">{{ __('Accounting export') }} ⚠</h4>
    <p class="text-body-secondary small">{{ __('Produces journal-level detail as a generic CSV. An overlapping range is flagged, not refused, so re-export stays possible.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:40rem">
        <div class="col-4">
            <select class="form-select" wire:model="targetSystem">
                <option value="generic_csv">{{ __('Generic CSV') }}</option>
                <option value="quickbooks">{{ __('QuickBooks') }}</option>
                <option value="sage">{{ __('Sage') }}</option>
                <option value="pastel">{{ __('Pastel') }}</option>
            </select>
        </div>
        <div class="col-3"><input type="date" class="form-control" wire:model="periodFrom"></div>
        <div class="col-3"><input type="date" class="form-control" wire:model="periodTo"></div>
        <div class="col-2"><button type="button" class="btn btn-primary w-100" wire:click="export">{{ __('Export') }}</button></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Target') }}</th><th>{{ __('Period') }}</th><th>{{ __('Journals') }}</th><th>{{ __('Exported') }}</th></tr></thead>
                <tbody>
                    @forelse ($exports as $export)
                        <tr wire:key="export-{{ $export->id }}">
                            <td>{{ $export->target_system }}</td>
                            <td>{{ $export->period_from->toDateString() }} – {{ $export->period_to->toDateString() }}</td>
                            <td>{{ $export->journal_count }}</td>
                            <td>{{ $export->exported_at->toDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No exports yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
