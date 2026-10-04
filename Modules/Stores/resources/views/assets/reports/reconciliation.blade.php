<div>
    <h4 class="mb-1">{{ __('Asset reconciliation') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Register vs. ledger, by category — run nightly in production; shown live here.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Register cost') }}</th><th>{{ __('Ledger asset') }}</th><th>{{ __('Register accum. dep.') }}</th><th>{{ __('Ledger accum. dep.') }}</th></tr></thead>
                <tbody>
                    @forelse ($divergences as $row)
                        <tr class="table-danger">
                            <td>{{ $categories[$row['categoryId']]->name ?? $row['categoryId'] }}</td>
                            <td>{{ number_format($row['registerCostMinor'] / 100, 2) }}</td>
                            <td>{{ number_format($row['ledgerAssetMinor'] / 100, 2) }}</td>
                            <td>{{ number_format($row['registerAccumDepMinor'] / 100, 2) }}</td>
                            <td>{{ number_format($row['ledgerAccumDepMinor'] / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Register and ledger agree for every category.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
