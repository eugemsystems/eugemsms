<div>
    <h4 class="mb-1">{{ __('Supplier contracts') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('An auto-renewing contract renews unless cancelled in time, so it still raises an alert before its notice window closes.') }}</p>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>{{ __('Contract') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Ends') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($contracts as $contract)
                                <tr wire:key="contract-{{ $contract->id }}">
                                    <td>{{ $contract->contract_number }}<div class="small text-body-secondary">{{ $contract->title }}</div></td>
                                    <td>{{ $contract->supplier->name }}</td>
                                    <td>{{ $contract->ends_on?->format('Y-m-d') ?? __('open-ended') }} @if ($contract->auto_renew) <span class="badge text-bg-info">{{ __('auto-renews') }}</span> @endif</td>
                                    <td><span class="badge text-bg-{{ $contract->status === 'active' ? 'success' : ($contract->status === 'expiring' ? 'warning' : 'secondary') }}">{{ $contract->status }}</span></td>
                                    <td class="text-end">
                                        @if (in_array($contract->status, ['active', 'expiring'], true))
                                            <div class="d-flex gap-1 justify-content-end">
                                                <input type="date" class="form-control form-control-sm w-auto" wire:model="renewals.{{ $contract->id }}">
                                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="renew({{ $contract->id }})">{{ __('Renew') }}</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="terminate({{ $contract->id }})" wire:confirm="{{ __('Terminate this contract?') }}">{{ __('Terminate') }}</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No contracts recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Record a contract') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="supplierId">
                        <option value="">{{ __('Supplier') }}</option>
                        @foreach ($suppliers as $supplier) <option value="{{ $supplier->id }}">{{ $supplier->name }}</option> @endforeach
                    </select>
                    @error('supplierId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="contractNumber" placeholder="{{ __('Contract number') }}">
                    @error('contractNumber') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    <select class="form-select mb-2" wire:model="contractType">
                        @foreach ($types as $type) <option value="{{ $type }}">{{ ucfirst($type) }}</option> @endforeach
                    </select>
                    <label class="form-label small mb-0">{{ __('Starts') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="startsOn">
                    <label class="form-label small mb-0">{{ __('Ends (blank = open-ended)') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="endsOn">
                    @error('endsOn') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="number" class="form-control mb-2" wire:model="valueMinor" placeholder="{{ __('Value (minor units, optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="renewalNoticeDays" placeholder="{{ __('Alert this many days before the end') }}">
                    @error('renewalNoticeDays') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="autoRenew" wire:model="autoRenew">
                        <label class="form-check-label" for="autoRenew">{{ __('Renews automatically unless cancelled') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
