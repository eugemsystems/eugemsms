<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Trial balance') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Always computed from source journal lines, per currency, consolidated in :base.', ['base' => $school->base_currency]) }}</p>
        </div>
        <span class="badge fs-6 {{ $trialBalance->isBalanced() ? 'text-bg-success' : 'text-bg-danger' }}">
            {{ $trialBalance->isBalanced() ? __('Balanced') : __('Out of balance') }}
        </span>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <input type="date" class="form-control" id="asAt" wire:model.live="asAt">
                        <label for="asAt">{{ __('As at') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" id="termId" wire:model.live="termId">
                            <option value="">{{ __('All terms') }}</option>
                            @foreach ($terms as $term)
                                <option value="{{ $term->id }}">{{ $term->name }}</option>
                            @endforeach
                        </select>
                        <label for="termId">{{ __('Term (optional)') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($trialBalance->linesByCurrency as $currency => $lines)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ $currency }}</h6>
                @php $totals = $trialBalance->totalsByCurrency[$currency]; @endphp
                <span class="badge {{ $totals['debit_minor'] === $totals['credit_minor'] ? 'text-bg-success' : 'text-bg-danger' }}">
                    {{ $totals['debit_minor'] === $totals['credit_minor'] ? __('Balanced') : __('Out of balance') }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Account') }}</th>
                            <th class="text-end">{{ __('Debit') }}</th>
                            <th class="text-end">{{ __('Credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr wire:key="tb-{{ $currency }}-{{ $line->accountId }}">
                                <td>{{ $line->accountCode }}</td>
                                <td>{{ $line->accountName }}</td>
                                <td class="text-end">{{ $line->debitMinor > 0 ? number_format($line->debitMinor / 100, 2) : '' }}</td>
                                <td class="text-end">{{ $line->creditMinor > 0 ? number_format($line->creditMinor / 100, 2) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="2">{{ __('Total') }}</td>
                            <td class="text-end">{{ number_format($totals['debit_minor'] / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($totals['credit_minor'] / 100, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endforeach

    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Consolidated (base currency)') }}</h6></div>
        <div class="card-body">
            <p class="mb-1"><strong>{{ __('Currency') }}:</strong> {{ $trialBalance->consolidatedBase['currency'] }}</p>
            <p class="mb-1"><strong>{{ __('Debit') }}:</strong> {{ number_format($trialBalance->consolidatedBase['debit_minor'] / 100, 2) }}</p>
            <p class="mb-0"><strong>{{ __('Credit') }}:</strong> {{ number_format($trialBalance->consolidatedBase['credit_minor'] / 100, 2) }}</p>
        </div>
    </div>
</div>
