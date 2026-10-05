<div>
    <h4 class="mb-1">{{ __('Prepaid tokens') }}</h4>

    <div class="row g-2 mb-3 align-items-end" style="max-width:40rem">
        <div class="col-6"><button type="button" class="btn btn-outline-warning btn-sm w-100" wire:click="checkUncredited">{{ __('Check uncredited tokens') }}</button></div>
        <div class="col-6"><button type="button" class="btn btn-outline-info btn-sm w-100" wire:click="checkReconciliation">{{ __('Run monthly reconciliation') }}</button></div>
    </div>

    @if ($uncreditedMeters !== [])
        <div class="alert alert-warning py-2">{{ __('Uncredited past the window:') }} {{ count($uncreditedMeters) }}</div>
    @endif

    @if ($reconciliationChecked)
        <div class="alert {{ $reconciliationFlaggedCount > 0 ? 'alert-danger' : 'alert-success' }} py-2">
            {{ __('Meters with a variance beyond tolerance this month:') }} {{ $reconciliationFlaggedCount }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Token #') }}</th><th>{{ __('Meter') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Units') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($purchases as $purchase)
                                <tr wire:key="purchase-{{ $purchase->id }}">
                                    <td>{{ $purchase->token_number }}</td>
                                    <td>{{ $purchase->meter->meter_number }}</td>
                                    <td>{{ number_format($purchase->amount_paid_minor / 100, 2) }} {{ $purchase->currency }}</td>
                                    <td>{{ number_format((float) $purchase->units_purchased, 1) }}</td>
                                    <td>
                                        <span class="badge {{ $purchase->credit_confirmed ? 'bg-success' : 'bg-warning text-dark' }}">{{ $purchase->status }}</span>
                                    </td>
                                    <td>
                                        @unless ($purchase->credit_confirmed)
                                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="confirmCredit({{ $purchase->id }})">{{ __('Confirm credit') }}</button>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No token purchases.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Purchase token') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="meterId">
                        <option value="">{{ __('Prepaid meter') }}</option>
                        @foreach ($meters as $meter)
                            <option value="{{ $meter->id }}">{{ $meter->meter_number }} — {{ $meter->location }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="tokenNumber" placeholder="{{ __('Token number (20 digits)') }}">
                    <input type="number" class="form-control mb-2" wire:model="amountPaidMinor" placeholder="{{ __('Amount paid (minor units)') }}">
                    <input type="number" step="0.001" class="form-control mb-2" wire:model="unitsPurchased" placeholder="{{ __('Units purchased (kWh)') }}">
                    <input type="number" class="form-control mb-2" wire:model="leviesMinor" placeholder="{{ __('Levies (minor units)') }}">
                    <input type="text" class="form-control mb-2" wire:model="vendor" placeholder="{{ __('Vendor (optional)') }}">
                    <select class="form-select mb-2" wire:model="prepaidAssetAccountId">
                        <option value="">{{ __('Prepaid electricity asset account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="contraAccountId">
                        <option value="">{{ __('Contra account (bank/cash)') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="purchase">{{ __('Record purchase') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
