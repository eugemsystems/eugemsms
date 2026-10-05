<div>
    <h4 class="mb-1">{{ __('Utility accounts') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Account #') }}</th><th>{{ __('Billing') }}</th></tr></thead>
                        <tbody>
                            @forelse ($accounts as $account)
                                <tr wire:key="account-{{ $account->id }}">
                                    <td>{{ $account->utility_type }}</td>
                                    <td>{{ $account->provider }}</td>
                                    <td>{{ $account->account_number }}</td>
                                    <td>{{ $account->billing_mode }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No utility accounts.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New utility account') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="utilityType">
                        @foreach (['electricity', 'water', 'refuse', 'sewerage', 'gas', 'telecoms'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="provider" placeholder="{{ __('Provider (e.g. ZESA, City Council)') }}">
                    <input type="text" class="form-control mb-2" wire:model="accountNumber" placeholder="{{ __('Account number') }}">
                    <input type="text" class="form-control mb-2" wire:model="tariffCode" placeholder="{{ __('Tariff code (optional)') }}">
                    <select class="form-select mb-2" wire:model="billingMode">
                        <option value="prepaid">{{ __('Prepaid') }}</option>
                        <option value="postpaid">{{ __('Postpaid') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="expenseAccountId">
                        <option value="">{{ __('Expense account') }}</option>
                        @foreach ($accountsList as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create account') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
