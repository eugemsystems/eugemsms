<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Reconciliation dashboard') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Runs the gateway-vs-receipts and bank-vs-receipts checks. Nothing auto-resolves — every exception is worked through by hand in the Exception workbench.') }}</p>
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ __('Run a reconciliation') }}</div>
        <div class="card-body">
            <form wire:submit="run">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model.live="scope">
                                <option value="bank">{{ __('Bank only') }}</option>
                                <option value="gateway">{{ __('Gateway only') }}</option>
                                <option value="full">{{ __('Full (gateway + bank)') }}</option>
                            </select>
                            <label>{{ __('Scope') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('runDate') is-invalid @enderror" wire:model="runDate">
                            <label>{{ __('Run date') }}</label>
                            @error('runDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('currency') is-invalid @enderror" wire:model="currency" placeholder=" ">
                            <label>{{ __('Currency') }}</label>
                            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    @if (in_array($scope, ['gateway', 'full']))
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('gatewayId') is-invalid @enderror" wire:model="gatewayId">
                                    <option value="">{{ __('Select a gateway') }}</option>
                                    @foreach ($gateways as $gateway)
                                        <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Gateway') }}</label>
                                @error('gatewayId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endif
                    @if (in_array($scope, ['bank', 'full']))
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('bankAccountId') is-invalid @enderror" wire:model="bankAccountId">
                                    <option value="">{{ __('Select a bank account') }}</option>
                                    @foreach ($bankAccounts as $bankAccount)
                                        <option value="{{ $bankAccount->id }}">{{ $bankAccount->bank_name }} — {{ $bankAccount->account_name }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Bank account') }}</label>
                                @error('bankAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endif
                </div>

                @if (in_array($scope, ['gateway', 'full']))
                    <div class="mt-4">
                        <label class="form-label">{{ __('Gateway settlements for this run') }}</label>
                        <p class="text-body-secondary small">{{ __('No live settlement-report connection exists yet — copy the rows the gateway itself reports settled from its own portal.') }}</p>
                        @foreach ($settlementRows as $index => $row)
                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-md-5">
                                    <input type="text" class="form-control form-control-sm" wire:model="settlementRows.{{ $index }}.reference" placeholder="{{ __('Gateway reference') }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control form-control-sm" wire:model="settlementRows.{{ $index }}.amount" placeholder="{{ __('Amount') }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control form-control-sm" wire:model="settlementRows.{{ $index }}.fee" placeholder="{{ __('Fee (optional)') }}">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeSettlementRow({{ $index }})"><i class="ri ri-close-line"></i></button>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addSettlementRow">{{ __('Add settlement row') }}</button>
                    </div>
                @endif

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Run reconciliation') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Recent runs') }}</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Run date') }}</th>
                        <th>{{ __('Scope') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Exceptions') }}</th>
                        <th>{{ __('Reviewed') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr wire:key="run-{{ $run->id }}">
                            <td>{{ $run->run_date->format('d M Y') }}</td>
                            <td>{{ ucfirst($run->scope) }}</td>
                            <td>
                                <span class="badge {{ $run->status === 'clean' ? 'text-bg-success' : ($run->status === 'exceptions' ? 'text-bg-warning' : 'text-bg-secondary') }}">
                                    {{ ucfirst($run->status) }}
                                </span>
                            </td>
                            <td>
                                @if ($run->exception_count > 0)
                                    <a href="{{ route('finance.reconciliation.exceptions', $school) }}" wire:navigate>{{ $run->exception_count }}</a>
                                @else
                                    0
                                @endif
                            </td>
                            <td>{{ $run->reviewed_at ? $run->reviewed_at->format('d M Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No reconciliation runs yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
