<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Messaging cost & segmentation') }} 🇿🇼</h4>
            <p class="text-body-secondary small mb-0">{{ __('Recorded spend by channel, SMS encoding breakdown, and messages that cost an extra segment unnecessarily.') }}</p>
        </div>
        <input type="month" class="form-control form-control-sm w-auto" wire:model.live="month">
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Spend by channel') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Channel') }}</th><th class="text-end">{{ __('Messages') }}</th><th class="text-end">{{ __('Spend') }}</th></tr></thead>
                        <tbody>
                            @forelse ($spendByChannel as $row)
                                <tr><td>{{ $row['channel'] }}</td><td class="text-end">{{ $row['messages'] }}</td><td class="text-end">{{ $row['total'] }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No costed messages this month.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('SMS encoding') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Encoding') }}</th><th class="text-end">{{ __('Messages') }}</th><th class="text-end">{{ __('Segments') }}</th></tr></thead>
                        <tbody>
                            @forelse ($encodingBreakdown as $row)
                                <tr><td>{{ $row->encoding }}</td><td class="text-end">{{ $row->messages }}</td><td class="text-end">{{ $row->segments }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No SMS segments this month.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="fw-semibold">{{ __('Segment waste') }}</div>
                    <p class="mb-0 small">{{ trans_choice(':count message hit UCS-2 and was split into extra segments although it fits in one GSM-7 segment.|:count messages hit UCS-2 and were split into extra segments although each fits in one GSM-7 segment.', $wastedMessages, ['count' => $wastedMessages]) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
