<div>
    <h4 class="mb-1">{{ __('Cost reconciliation') }} 🇿🇼 ⭐</h4>
    <p class="text-body-secondary small">{{ __('Compares system-recorded spend with the provider’s monthly statement. A variance beyond the configured tolerance is flagged for investigation, never absorbed. Amounts are in :currency.', ['currency' => $currency->value]) }}</p>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Gateway') }}</th><th class="text-end">{{ __('System') }}</th><th class="text-end">{{ __('Provider') }}</th><th class="text-end">{{ __('Variance') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($reconciliations as $row)
                                <tr wire:key="rec-{{ $row->id }}">
                                    <td>{{ $row->period_month }}</td>
                                    <td>{{ $row->gateway?->name }}</td>
                                    <td class="text-end">{{ \Modules\Core\Domain\Support\Money::of((int) $row->system_recorded_minor, $currency)->format() }}</td>
                                    <td class="text-end">{{ $row->provider_invoiced_minor !== null ? \Modules\Core\Domain\Support\Money::of((int) $row->provider_invoiced_minor, $currency)->format() : '—' }}</td>
                                    <td class="text-end">{{ $row->variance_minor !== null ? \Modules\Core\Domain\Support\Money::of((int) $row->variance_minor, $currency)->format() : '—' }}</td>
                                    <td><span class="badge {{ $row->status === 'variance' ? 'bg-label-danger' : ($row->status === 'reconciled' ? 'bg-label-success' : 'bg-label-secondary') }}">{{ $row->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('Nothing reconciled yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Reconcile a month') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="gatewayId">
                        <option value="">{{ __('Gateway…') }}</option>
                        @foreach ($gateways as $gateway)
                            <option value="{{ $gateway->id }}">{{ $gateway->name }} ({{ $gateway->channel }})</option>
                        @endforeach
                    </select>
                    @error('gatewayId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="month" class="form-control mb-2" wire:model="periodMonth">
                    @error('periodMonth') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" inputmode="decimal" class="form-control mb-2" wire:model="providerInvoiced" placeholder="{{ __('Provider invoiced total (optional)') }}">
                    @error('providerInvoiced') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="reconcile">{{ __('Reconcile') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
