<div>
    <h4 class="mb-1">{{ __('Stock valuation') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('By category, as at now — read from the verified stock_balances cache.') }}</p>

    <div class="card">
        <div class="card-header">
            <select class="form-select form-select-sm" style="max-width: 20rem" wire:model.live="storeId">
                <option value="">{{ __('All stores') }}</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Items') }}</th><th>{{ __('Value') }}</th></tr></thead>
                <tbody>
                    @forelse ($byCategory as $category => $row)
                        <tr>
                            <td>{{ $category }}</td>
                            <td>{{ $row['count'] }}</td>
                            <td>{{ number_format($row['value_minor'] / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No stock.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr class="fw-semibold"><td colspan="2">{{ __('Total') }}</td><td>{{ number_format($totalMinor / 100, 2) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
