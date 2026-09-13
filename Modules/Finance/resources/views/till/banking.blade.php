<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Daily banking') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Total receipted by tender and currency for one day, across every till.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <input type="date" class="form-control" id="date" wire:model.live="date">
                        <label for="date">{{ __('Date') }}</label>
                    </div>
                </div>
                <div class="col-md-9 text-body-secondary">
                    {{ __(':count receipts posted on this date.', ['count' => $receiptCount]) }}
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Tender') }}</th>
                        <th>{{ __('Currency') }}</th>
                        <th class="text-end">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenderTotals as $row)
                        <tr wire:key="{{ $row->tenderType }}-{{ $row->currency }}">
                            <td>{{ ucfirst(str_replace('_', ' ', $row->tenderType)) }}</td>
                            <td>{{ $row->currency }}</td>
                            <td class="text-end">{{ number_format($row->totalMinor / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-body-secondary py-4">{{ __('No receipts posted on this date.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
