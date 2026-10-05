<div>
    <h4 class="mb-1">{{ __('Trial balance') }} ⭐</h4>
    <p class="text-body-secondary small">{{ __('Generated straight from journal lines — never a cached balance.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:36rem">
        <div class="col-5">
            <label class="form-label small mb-0">{{ __('As at') }}</label>
            <input type="date" class="form-control" wire:model="asAt">
        </div>
        <div class="col-5">
            <label class="form-label small mb-0">{{ __('As known on (optional)') }}</label>
            <input type="date" class="form-control" wire:model="asKnownOn">
        </div>
        <div class="col-2"><button type="button" class="btn btn-primary w-100" wire:click="generate">{{ __('Run') }}</button></div>
    </div>

    @if ($generated)
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['code'] }} — {{ $row['name'] }}</td>
                                <td>{{ $row['currency'] }}</td>
                                <td class="text-end">{{ number_format($row['debit_minor'] / 100, 2) }}</td>
                                <td class="text-end">{{ number_format($row['credit_minor'] / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No journal lines in range.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
