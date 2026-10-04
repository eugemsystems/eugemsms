<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Payment gateways') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Only the Fake test driver is wired up — no live payment provider has sandbox credentials configured yet.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New gateway') }}
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Driver') }}</th>
                        <th>{{ __('Methods') }}</th>
                        <th>{{ __('Mode') }}</th>
                        <th>{{ __('Default') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Health') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gateways as $gateway)
                        <tr wire:key="gateway-{{ $gateway->id }}">
                            <td>{{ $gateway->name }}</td>
                            <td><span class="badge bg-label-secondary">{{ $gateway->driver }}</span></td>
                            <td>{{ implode(', ', $gateway->supported_methods) }}</td>
                            <td>
                                <span class="badge {{ $gateway->is_sandbox ? 'bg-label-warning' : 'bg-label-success' }}">
                                    {{ $gateway->is_sandbox ? __('Sandbox') : __('Production') }}
                                </span>
                            </td>
                            <td>{{ $gateway->is_default ? __('Yes') : __('No') }}</td>
                            <td>
                                <span class="badge {{ $gateway->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $gateway->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td>
                                @if ($gateway->health_status)
                                    <span class="badge {{ $gateway->health_status === 'up' ? 'text-bg-success' : ($gateway->health_status === 'degraded' ? 'text-bg-warning' : 'text-bg-danger') }}">
                                        {{ ucfirst($gateway->health_status) }}
                                    </span>
                                @else
                                    <span class="text-body-secondary">{{ __('Never checked') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="testConnection({{ $gateway->id }})">{{ __('Test connection') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEditModal({{ $gateway->id }})">{{ __('Edit') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-body-secondary py-4">{{ __('No payment gateways registered yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showFormModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form wire:submit="save">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $editingGatewayId === null ? __('New gateway') : __('Edit gateway') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showFormModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" wire:model="driver" @if ($editingGatewayId !== null) disabled @endif>
                                            <option value="fake">{{ __('Fake (test driver, no live integration)') }}</option>
                                        </select>
                                        <label>{{ __('Driver') }}</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                        <label>{{ __('Name') }}</label>
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating form-floating-outline">
                                        <input type="password" class="form-control @error('credentials') is-invalid @enderror" wire:model="credentials" placeholder=" " autocomplete="new-password">
                                        <label>{{ $editingGatewayId === null ? __('Credentials (JSON)') : __('Rotate credentials (leave blank to keep the current ones)') }}</label>
                                        @error('credentials') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Supported methods') }}</label>
                                    <div class="d-flex gap-3">
                                        @foreach ($methodOptions as $method)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="{{ $method }}" wire:model="supportedMethods" id="method-{{ $method }}">
                                                <label class="form-check-label" for="method-{{ $method }}">{{ ucfirst($method) }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('supportedMethods') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('Supported currencies') }}</label>
                                    <div class="d-flex gap-3">
                                        @foreach ($currencyOptions as $currency)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="{{ $currency }}" wire:model="supportedCurrencies" id="currency-{{ $currency }}">
                                                <label class="form-check-label" for="currency-{{ $currency }}">{{ $currency }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('supportedCurrencies') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select @error('settlementAccountId') is-invalid @enderror" wire:model="settlementAccountId">
                                            <option value="">{{ __('Select an account') }}</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <label>{{ __('Settlement account') }}</label>
                                        @error('settlementAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select @error('feeAccountId') is-invalid @enderror" wire:model="feeAccountId">
                                            <option value="">{{ __('Select an account') }}</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <label>{{ __('Fee expense account') }}</label>
                                        @error('feeAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" wire:model="feeType">
                                            <option value="percentage">{{ __('Percentage') }}</option>
                                            <option value="flat">{{ __('Flat') }}</option>
                                        </select>
                                        <label>{{ __('Fee type') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control" wire:model="feeValue" placeholder=" ">
                                        <label>{{ $feeType === 'percentage' ? __('Fee %') : __('Flat fee') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control" wire:model="feeCap" placeholder=" ">
                                        <label>{{ __('Fee cap (optional)') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" wire:model="isSandbox">
                                        <label class="form-check-label">{{ __('Sandbox mode') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" wire:model="isActive">
                                        <label class="form-check-label">{{ __('Active') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" wire:model="isDefault">
                                        <label class="form-check-label">{{ __('Default gateway') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save gateway') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
