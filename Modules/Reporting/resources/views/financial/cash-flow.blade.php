<div>
    <h4 class="mb-1">{{ __('Cash flow') }}</h4>
    <p class="text-body-secondary small">{{ __('Bank movements by type of journal, between the opening and closing bank position. Cash in tills counts once banked.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:46rem">
        <div class="col-4">
            <label class="form-label small mb-0">{{ __('Period start') }}</label>
            <input type="date" class="form-control" wire:model="periodStart">
        </div>
        <div class="col-4">
            <label class="form-label small mb-0">{{ __('Period end') }}</label>
            <input type="date" class="form-control" wire:model="periodEnd">
        </div>
        <div class="col-2">
            <label class="form-label small mb-0">{{ __('Currency') }}</label>
            <input type="text" class="form-control" maxlength="3" wire:model="currency">
        </div>
        <div class="col-2"><button type="button" class="btn btn-primary w-100" wire:click="generate">{{ __('Run') }}</button></div>
    </div>
    @error('periodEnd') <div class="text-danger small">{{ $message }}</div> @enderror

    @if ($result !== null)
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr class="table-light"><td><strong>{{ __('Opening bank position') }}</strong></td><td class="text-end"><strong>{{ number_format($result['opening_minor'] / 100, 2) }}</strong></td></tr>
                        <tr><th colspan="2">{{ __('Money in') }}</th></tr>
                        @forelse ($result['inflows'] as $row)
                            <tr><td>{{ $row['journal_type'] }}</td><td class="text-end">{{ number_format($row['amount_minor'] / 100, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-body-secondary">{{ __('None') }}</td></tr>
                        @endforelse
                        <tr><th colspan="2">{{ __('Money out') }}</th></tr>
                        @forelse ($result['outflows'] as $row)
                            <tr><td>{{ $row['journal_type'] }}</td><td class="text-end">({{ number_format($row['amount_minor'] / 100, 2) }})</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-body-secondary">{{ __('None') }}</td></tr>
                        @endforelse
                        <tr><td>{{ __('Net movement') }}</td><td class="text-end">{{ number_format($result['net_minor'] / 100, 2) }}</td></tr>
                        <tr class="table-light"><td><strong>{{ __('Closing bank position') }}</strong></td><td class="text-end"><strong>{{ number_format($result['closing_minor'] / 100, 2) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
