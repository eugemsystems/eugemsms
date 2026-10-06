<div>
    <h4 class="mb-1">{{ __('Balance sheet') }}</h4>
    <p class="text-body-secondary small">{{ __('Built from the journal for the chosen date. Income and expense to date appear as current earnings.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:46rem">
        <div class="col-4">
            <label class="form-label small mb-0">{{ __('As at') }}</label>
            <input type="date" class="form-control" wire:model="asAt">
        </div>
        <div class="col-3">
            <label class="form-label small mb-0">{{ __('As known on') }}</label>
            <input type="date" class="form-control" wire:model="asKnownOn">
        </div>
        <div class="col-3">
            <label class="form-label small mb-0">{{ __('Currency') }}</label>
            <input type="text" class="form-control" maxlength="3" wire:model="currency">
        </div>
        <div class="col-2"><button type="button" class="btn btn-primary w-100" wire:click="generate">{{ __('Run') }}</button></div>
    </div>
    @error('asAt') <div class="text-danger small">{{ $message }}</div> @enderror
    @error('currency') <div class="text-danger small">{{ $message }}</div> @enderror

    @if ($generated)
        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    @foreach (['ASSET' => __('Assets'), 'LIABILITY' => __('Liabilities'), 'EQUITY' => __('Equity')] as $type => $label)
                        <thead><tr class="table-light"><th colspan="2">{{ $label }}</th></tr></thead>
                        <tbody>
                            @forelse ($sections[$type]['lines'] ?? [] as $line)
                                <tr><td>{{ $line['code'] }} — {{ $line['name'] }}</td><td class="text-end">{{ number_format($line['amount_minor'] / 100, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-body-secondary">{{ __('None') }}</td></tr>
                            @endforelse
                            @if ($type === 'EQUITY')
                                <tr><td>{{ __('Current earnings') }}</td><td class="text-end">{{ number_format($currentEarningsMinor / 100, 2) }}</td></tr>
                            @endif
                            <tr><td><strong>{{ __('Total') }} {{ strtolower($label) }}</strong></td><td class="text-end"><strong>{{ number_format((($sections[$type]['total_minor'] ?? 0) + ($type === 'EQUITY' ? $currentEarningsMinor : 0)) / 100, 2) }}</strong></td></tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="alert {{ $isBalanced ? 'alert-success' : 'alert-danger' }}">
            {{ $isBalanced ? __('Balanced') : __('Out of balance') }}:
            {{ __('assets') }} {{ number_format($totalAssetsMinor / 100, 2) }} / {{ __('liabilities and equity') }} {{ number_format($totalLiabilitiesAndEquityMinor / 100, 2) }}
        </div>
    @endif
</div>
