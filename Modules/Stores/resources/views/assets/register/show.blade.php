<div>
    <h4 class="mb-1">{{ $asset->name }} ({{ $asset->asset_tag }})</h4>
    <p class="text-body-secondary mb-4">
        <span class="badge text-bg-{{ $asset->status === 'active' ? 'success' : 'secondary' }}">{{ $asset->status }}</span>
        {{ __('NBV') }}: {{ number_format($asset->net_book_value_minor / 100, 2) }} {{ $asset->currency }}
    </p>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card card-body">
                <div class="fw-semibold mb-2">{{ __('Status / condition') }}</div>
                <select class="form-select form-select-sm mb-1" wire:model="newStatus">
                    <option value="">{{ __('No change') }}</option>
                    @foreach (['active','in_maintenance','idle','impaired','lost','stolen'] as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
                <select class="form-select form-select-sm mb-1" wire:model="newCondition">
                    <option value="">{{ __('No change') }}</option>
                    @foreach (['good','fair','poor'] as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control form-control-sm mb-2" wire:model="statusReason" placeholder="{{ __('Reason') }}">
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="changeStatus">{{ __('Apply') }}</button>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-body">
                <div class="fw-semibold mb-2">{{ __('Custodian') }}</div>
                <select class="form-select form-select-sm mb-2" wire:model="newCustodianStaffId">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="changeCustodian">{{ __('Apply') }}</button>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-body">
                <div class="fw-semibold mb-2">{{ __('Transfer cost centre') }}</div>
                <select class="form-select form-select-sm mb-1" wire:model="newCostCentreId">
                    <option value="">{{ __('Select cost centre') }}</option>
                    @foreach ($costCentres as $cc)
                        <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control form-control-sm mb-2" wire:model="transferReason" placeholder="{{ __('Reason') }}">
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="transfer">{{ __('Transfer') }}</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Movement history') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr wire:key="mv-{{ $movement->id }}">
                            <td>{{ $movement->occurred_at->format('Y-m-d') }}</td>
                            <td>{{ $movement->movement_type }}</td>
                            <td>{{ $movement->from_value }}</td>
                            <td>{{ $movement->to_value }}</td>
                            <td>{{ $movement->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No movements.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
