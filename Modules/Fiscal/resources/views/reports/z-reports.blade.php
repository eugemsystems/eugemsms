<div>
    <h4 class="mb-3">{{ __('Z-reports') }}</h4>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Receipts') }}</th><th>{{ __('Totals by currency') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr wire:key="zreport-{{ $report->id }}">
                            <td>{{ $report->report_date->toDateString() }}</td>
                            <td>{{ $report->receipt_count }}</td>
                            <td class="small">
                                @foreach ((array) $report->totals_by_currency as $currency => $amount)
                                    {{ $currency }}: {{ number_format($amount / 100, 2) }}&nbsp;
                                @endforeach
                            </td>
                            <td><span class="badge {{ $report->status === 'submitted' ? 'bg-success' : 'bg-secondary' }}">{{ $report->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No Z-reports yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
