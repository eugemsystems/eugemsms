<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Bank accounts') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Real-world bank accounts, each linked to its own GL account.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New bank account') }}
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Bank') }}</th>
                        <th>{{ __('Account') }}</th>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Currency') }}</th>
                        <th>{{ __('GL account') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bankAccounts as $bankAccount)
                        <tr wire:key="bank-account-{{ $bankAccount->id }}">
                            <td>{{ $bankAccount->bank_name }}</td>
                            <td>
                                {{ $bankAccount->account_name }}
                                @if ($bankAccount->branch)
                                    <div class="text-body-secondary small">{{ $bankAccount->branch }}</div>
                                @endif
                            </td>
                            <td>{{ $bankAccount->account_number }}</td>
                            <td>{{ ucfirst($bankAccount->account_type) }}</td>
                            <td>{{ $bankAccount->currency }}</td>
                            <td>{{ $bankAccount->glAccount->code }} — {{ $bankAccount->glAccount->name }}</td>
                            <td>
                                <span class="badge {{ $bankAccount->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $bankAccount->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEditModal({{ $bankAccount->id }})">{{ __('Edit') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-body-secondary py-4">{{ __('No bank accounts registered yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showFormModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="save">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $editingBankAccountId === null ? __('New bank account') : __('Edit bank account') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showFormModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('bankName') is-invalid @enderror" wire:model="bankName" placeholder=" ">
                                        <label>{{ __('Bank name') }}</label>
                                        @error('bankName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control" wire:model="branch" placeholder=" ">
                                        <label>{{ __('Branch (optional)') }}</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('accountName') is-invalid @enderror" wire:model="accountName" placeholder=" ">
                                        <label>{{ __('Account name') }}</label>
                                        @error('accountName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('accountNumber') is-invalid @enderror" wire:model="accountNumber" placeholder=" ">
                                        <label>{{ __('Account number') }}</label>
                                        @error('accountNumber') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('currency') is-invalid @enderror" wire:model="currency" placeholder=" ">
                                        <label>{{ __('Currency') }}</label>
                                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" wire:model="accountType">
                                            <option value="current">{{ __('Current') }}</option>
                                            <option value="nostro">{{ __('Nostro') }}</option>
                                            <option value="fca">{{ __('FCA') }}</option>
                                            <option value="savings">{{ __('Savings') }}</option>
                                        </select>
                                        <label>{{ __('Account type') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" role="switch" wire:model="isActive">
                                        <label class="form-check-label">{{ __('Active') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select @error('glAccountId') is-invalid @enderror" wire:model="glAccountId">
                                            <option value="">{{ __('Select the GL account') }}</option>
                                            @foreach ($glAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <label>{{ __('GL account') }}</label>
                                        @error('glAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save bank account') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
