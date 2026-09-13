<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Collections') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Receipted totals by day, tender, currency, and cashier.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <input type="date" class="form-control" id="fromDate" wire:model.live="fromDate">
                        <label for="fromDate">{{ __('From') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <input type="date" class="form-control" id="toDate" wire:model.live="toDate">
                        <label for="toDate">{{ __('To') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">{{ __('By day') }}</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @forelse ($byDay as $row)
                                <tr>
                                    <td>{{ $row->date }}</td>
                                    <td>{{ $row->currency }}</td>
                                    <td class="text-end">{{ number_format($row->totalMinor / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No receipts in range.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">{{ __('By tender') }}</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Tender') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @forelse ($byTender as $row)
                                <tr>
                                    <td>{{ ucfirst(str_replace('_', ' ', $row->tenderType)) }}</td>
                                    <td>{{ $row->currency }}</td>
                                    <td class="text-end">{{ number_format($row->totalMinor / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No receipts in range.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">{{ __('By cashier') }}</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Cashier') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @forelse ($byCashier as $row)
                                <tr>
                                    <td>{{ $row->cashierName }}</td>
                                    <td>{{ $row->currency }}</td>
                                    <td class="text-end">{{ number_format($row->totalMinor / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No receipts in range.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
