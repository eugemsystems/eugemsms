<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.accounts.tree', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ $editingAccountId !== null ? __('Edit account') : __('New account') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('A system account\'s identity (code, type, key) never changes once created.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="row g-3">
                    @if ($editingAccountId === null)
                        <div class="col-md-3">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('accountTypeCode') is-invalid @enderror" id="accountTypeCode" wire:model="accountTypeCode">
                                    @foreach ($accountTypes as $type)
                                        <option value="{{ $type->code }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                <label for="accountTypeCode">{{ __('Type') }}</label>
                                @error('accountTypeCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" wire:model="code" placeholder=" ">
                                <label for="code">{{ __('Code') }}</label>
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @else
                        <div class="col-12">
                            <div class="alert alert-secondary mb-0">
                                {{ __('Code and type are fixed once an account is created.') }}
                                <strong>{{ $code }}</strong> — {{ $accountTypeCode }}
                            </div>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model="name" placeholder=" ">
                            <label for="name">{{ __('Name') }}</label>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" wire:model="description" placeholder=" ">
                            <label for="description">{{ __('Description (optional)') }}</label>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="parentId" wire:model="parentId">
                                <option value="">{{ __('No parent (top level)') }}</option>
                                @foreach ($parentCandidates as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->code }} — {{ $parent->name }}</option>
                                @endforeach
                            </select>
                            <label for="parentId">{{ __('Parent account (heading)') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('subledgerType') is-invalid @enderror" id="subledgerType" wire:model="subledgerType">
                                <option value="">{{ __('None') }}</option>
                                <option value="learner">{{ __('Learner') }}</option>
                                <option value="guardian">{{ __('Guardian') }}</option>
                                <option value="supplier">{{ __('Supplier') }}</option>
                                <option value="staff">{{ __('Staff') }}</option>
                            </select>
                            <label for="subledgerType">{{ __('Subledger type') }}</label>
                            @error('subledgerType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('currency') is-invalid @enderror" id="currency" wire:model="currency">
                                <option value="">{{ __('Any currency') }}</option>
                                <option value="USD">USD</option>
                                <option value="ZWG">ZWG</option>
                            </select>
                            <label for="currency">{{ __('Restrict to currency') }}</label>
                            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="isPostable" wire:model="isPostable">
                            <label class="form-check-label" for="isPostable">{{ __('Postable (can receive journal lines directly)') }}</label>
                        </div>
                        @if ($editingAccountId === null)
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="isControlAccount" wire:model="isControlAccount">
                                <label class="form-check-label" for="isControlAccount">{{ __('Control account (requires a subledger on every line)') }}</label>
                            </div>
                        @endif
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="requiresCostCentre" wire:model="requiresCostCentre">
                            <label class="form-check-label" for="requiresCostCentre">{{ __('Requires a cost centre on every line') }}</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        {{ $editingAccountId !== null ? __('Save changes') : __('Create account') }}
                    </button>
                    <a href="{{ route('finance.accounts.tree', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
