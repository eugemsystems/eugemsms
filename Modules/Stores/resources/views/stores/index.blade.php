<div>
    <h4 class="mb-1">{{ __('Stores') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Multi-store structure — main, kitchen, boarding, laboratory, workshop, farm, clinic, uniform, textbook, fuel, maintenance.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Costing') }}</th><th>{{ __('Active') }}</th></tr></thead>
                        <tbody>
                            @forelse ($stores as $store)
                                <tr wire:key="store-{{ $store->id }}">
                                    <td>{{ $store->code }}</td>
                                    <td>{{ $store->name }}</td>
                                    <td><span class="badge text-bg-secondary">{{ $store->store_type }}</span></td>
                                    <td>{{ $store->costing_method }}</td>
                                    <td>{{ $store->is_active ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No stores yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New store') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code (e.g. MAIN, KITCHEN)') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="storeType">
                        @foreach (['main','kitchen','boarding','laboratory','workshop','farm','clinic','uniform','textbook','fuel','maintenance'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }} — {{ $cc->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="inventoryAccountId">
                        <option value="">{{ __('Inventory (asset) account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="defaultExpenseAccountId">
                        <option value="">{{ __('Default expense account (on issue)') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="costingMethod">
                        <option value="fifo">{{ __('FIFO') }}</option>
                        <option value="weighted_average">{{ __('Weighted average') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location (optional)') }}">
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="requiresIssueApproval" wire:model="requiresIssueApproval">
                        <label class="form-check-label" for="requiresIssueApproval">{{ __('Requires issue approval') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="allowsNegativeStock" wire:model="allowsNegativeStock">
                        <label class="form-check-label" for="allowsNegativeStock">{{ __('Allows negative stock') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create store') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
