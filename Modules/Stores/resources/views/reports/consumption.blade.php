<div>
    <h4 class="mb-1">{{ __('Consumption report') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Issued cost per requesting cost centre over the selected window.') }}</p>

    <div class="card">
        <div class="card-header d-flex gap-2">
            <input type="date" class="form-control form-control-sm" style="max-width: 10rem" wire:model.live="from">
            <input type="date" class="form-control form-control-sm" style="max-width: 10rem" wire:model.live="to">
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Cost centre') }}</th><th>{{ __('Issues') }}</th><th>{{ __('Total cost') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $costCentres[$row->cost_centre_id]->name ?? __('Unassigned') }}</td>
                            <td>{{ $row->movement_count }}</td>
                            <td>{{ number_format($row->total_minor / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No issues in this window.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
