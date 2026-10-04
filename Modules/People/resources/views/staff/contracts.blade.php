<div>
    <h4 class="mb-1">{{ __('Contracts') }}</h4>
    <p class="text-body-secondary mb-4">{{ $staff->fullName() }} — {{ $staff->staff_number }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Contract history') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($contracts as $contract)
                                <tr wire:key="contract-{{ $contract->id }}">
                                    <td>{{ ucfirst($contract->contract_type) }}</td>
                                    <td>{{ $contract->starts_on->format('d M Y') }}</td>
                                    <td>{{ $contract->ends_on?->format('d M Y') ?? '—' }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($contract->status) }}</span></td>
                                    <td class="text-end">
                                        @if ($contract->status === 'active')
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="renew({{ $contract->id }})">{{ __('Renew') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="terminate({{ $contract->id }})" wire:confirm="{{ __('Terminate this contract?') }}">{{ __('Terminate') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No contracts yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New / renewal details') }}</div>
                <div class="card-body">
                    <p class="text-body-secondary small">{{ __('These fields apply to "Create" (when there is no active contract) and to "Renew" on an existing row above.') }}</p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <select class="form-select form-select-sm" wire:model="contractType">
                                <option value="permanent">{{ __('Permanent') }}</option>
                                <option value="fixed_term">{{ __('Fixed term') }}</option>
                                <option value="relief">{{ __('Relief') }}</option>
                                <option value="part_time">{{ __('Part time') }}</option>
                                <option value="attachment">{{ __('Attachment') }}</option>
                                <option value="volunteer">{{ __('Volunteer') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="number" class="form-control form-control-sm" wire:model="noticePeriodDays" placeholder="{{ __('Notice period (days)') }}">
                        </div>
                        <div class="col-md-6">
                            <input type="date" class="form-control form-control-sm @error('startsOn') is-invalid @enderror" wire:model="startsOn">
                        </div>
                        <div class="col-md-6">
                            <input type="date" class="form-control form-control-sm" wire:model="endsOn" placeholder="{{ __('Ends on (optional)') }}">
                        </div>
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-sm @error('basicSalaryAmount') is-invalid @enderror" wire:model="basicSalaryAmount" placeholder="{{ __('Basic salary (optional)') }}">
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" wire:model="salaryCurrency">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="create">{{ __('Create contract') }}</button>

                    <hr>
                    <h6>{{ __('Termination details') }}</h6>
                    <p class="text-body-secondary small">{{ __('Used by the "Terminate" button above.') }}</p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="date" class="form-control form-control-sm" wire:model="terminatedOn">
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control form-control-sm @error('terminationReason') is-invalid @enderror" wire:model="terminationReason" placeholder="{{ __('Reason') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
