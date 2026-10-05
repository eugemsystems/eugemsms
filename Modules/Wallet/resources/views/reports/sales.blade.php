<div>
    <h4 class="mb-3">{{ __('Wallet sales analytics') }}</h4>

    <div class="row g-2 mb-3">
        <div class="col-auto"><div class="card p-3">{{ __('Total sales') }}: <strong>{{ $totalSales }}</strong></div></div>
        <div class="col-auto"><div class="card p-3">{{ __('Total value') }}: <strong>{{ number_format($totalMinor / 100, 2) }}</strong></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('By spend point') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Spend point') }}</th><th>{{ __('Count') }}</th><th>{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @forelse ($byPoint as $name => $row)
                                <tr><td>{{ $name }}</td><td>{{ $row['count'] }}</td><td>{{ number_format($row['total_minor'] / 100, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No sales yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('By time of day') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Hour') }}</th><th>{{ __('Count') }}</th></tr></thead>
                        <tbody>
                            @forelse ($byHour as $hour => $count)
                                <tr><td>{{ $hour }}</td><td>{{ $count }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('No sales yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
