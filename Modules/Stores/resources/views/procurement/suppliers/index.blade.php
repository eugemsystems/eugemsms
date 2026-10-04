<div>
    <h4 class="mb-1">{{ __('Suppliers') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A new supplier cannot receive an order until a different user approves it.') }}</p>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <input type="text" class="form-control form-control-sm" style="max-width: 20rem" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search...') }}">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Status') }}</th><th>{{ __('VAT') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($suppliers as $supplier)
                                <tr wire:key="supplier-{{ $supplier->id }}">
                                    <td><a href="{{ route('stores.procurement.suppliers.show', ['school' => $school, 'supplier' => $supplier]) }}" wire:navigate>{{ $supplier->code }}</a></td>
                                    <td>{{ $supplier->name }}</td>
                                    <td><span class="badge text-bg-{{ $supplier->status === 'active' ? 'success' : ($supplier->status === 'blacklisted' ? 'danger' : 'secondary') }}">{{ $supplier->status }}</span></td>
                                    <td>{{ $supplier->is_vat_registered ? __('Yes') : __('No') }}</td>
                                    <td class="d-flex gap-1">
                                        @if ($supplier->status === 'pending_approval')
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $supplier->id }})">{{ __('Approve') }}</button>
                                        @endif
                                        @if ($supplier->status !== 'blacklisted')
                                            <input type="text" class="form-control form-control-sm" style="max-width: 10rem" wire:model="blacklistReasons.{{ $supplier->id }}" placeholder="{{ __('Reason') }}">
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="blacklist({{ $supplier->id }})">{{ __('Blacklist') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No suppliers.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('New supplier') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="supplierType">
                        <option value="company">{{ __('Company') }}</option>
                        <option value="sole_trader">{{ __('Sole trader') }}</option>
                        <option value="individual">{{ __('Individual') }}</option>
                        <option value="government">{{ __('Government') }}</option>
                        <option value="ngo">{{ __('NGO') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="preferredCurrency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <select class="form-select mb-2" wire:model="controlAccountId">
                        <option value="">{{ __('Creditors control account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="isVatRegistered" wire:model.live="isVatRegistered">
                        <label class="form-check-label" for="isVatRegistered">🇿🇼 {{ __('VAT registered') }}</label>
                    </div>
                    @if ($isVatRegistered)
                        <input type="text" class="form-control mb-2" wire:model="vatNumber" placeholder="{{ __('VAT number') }}">
                    @endif
                    <input type="text" class="form-control mb-2" wire:model="bpNumber" placeholder="{{ __('ZIMRA Business Partner number (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
