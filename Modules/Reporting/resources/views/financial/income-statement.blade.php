<div>
    <h4 class="mb-1">{{ __('Income statement') }} ⭐</h4>
    <p class="text-body-secondary small">{{ __('Give "as known on" for a point-in-time view — the difference against today is itemised as prior-period adjustments, never blended in.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:46rem">
        <div class="col-4">
            <label class="form-label small mb-0">{{ __('Period start') }}</label>
            <input type="date" class="form-control" wire:model="periodStart">
        </div>
        <div class="col-4">
            <label class="form-label small mb-0">{{ __('Period end') }}</label>
            <input type="date" class="form-control" wire:model="periodEnd">
        </div>
        <div class="col-3">
            <label class="form-label small mb-0">{{ __('As known on') }}</label>
            <input type="date" class="form-control" wire:model="asKnownOn">
        </div>
        <div class="col-1"><button type="button" class="btn btn-primary w-100" wire:click="generate">{{ __('Run') }}</button></div>
    </div>

    @if ($generated)
        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Account') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($lines as $line)
                            <tr><td>{{ $line['code'] }} — {{ $line['name'] }}</td><td class="text-end">{{ number_format($line['amount_minor'] / 100, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('No journal lines in range.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="table-light"><td><strong>{{ __('Net') }}</strong></td><td class="text-end"><strong>{{ number_format($netMinor / 100, 2) }}</strong></td></tr></tfoot>
                </table>
            </div>
        </div>

        @if ($asKnownOn !== '')
            <div class="card">
                <div class="card-header">{{ __('Prior-period adjustments (posted after') }} {{ $asKnownOn }})</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($reconcilingItems as $item)
                                <tr><td>{{ $item['code'] }} — {{ $item['name'] }}</td><td class="text-end">{{ number_format($item['amount_minor'] / 100, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('None — the current view matches what was known then.') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot><tr class="table-light"><td><strong>{{ __('Reconciling total') }}</strong></td><td class="text-end"><strong>{{ number_format($reconcilingTotalMinor / 100, 2) }}</strong></td></tr></tfoot>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
