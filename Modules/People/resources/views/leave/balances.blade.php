<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Leave balances') }}</h4>
            <p class="text-body-secondary mb-0">{{ $school->name }}</p>
        </div>
        <a href="{{ route('people.leave.request', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Request leave') }}</a>
        <a href="{{ route('people.leave.approvals', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Approvals') }}</a>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Balances') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('Type') }}</th><th>{{ __('Available') }}</th><th>{{ __('Pending') }}</th><th>{{ __('Taken') }}</th></tr></thead>
                        <tbody>
                            @forelse ($balances as $balance)
                                <tr>
                                    <td>{{ $balance->staff?->fullName() }}</td>
                                    <td>{{ $balance->leaveType?->name }}</td>
                                    <td>{{ $balance->available_days }}</td>
                                    <td>{{ $balance->pending_days }}</td>
                                    <td>{{ $balance->taken_days }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No leave balances recorded yet — a balance is created the first time a staff member requests that leave type.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">{{ __('Leave types') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($leaveTypes as $leaveType)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $leaveType->name }} ({{ $leaveType->code }})</span>
                            <span class="text-body-secondary small">{{ $leaveType->annual_entitlement_days ?? '—' }} {{ __('days/yr') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No leave types yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="card">
                <div class="card-header">{{ __('New leave type') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-5">
                            <input type="text" class="form-control form-control-sm @error('code') is-invalid @enderror" wire:model="code" placeholder="{{ __('Code') }}">
                        </div>
                        <div class="col-7">
                            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" wire:model="name" placeholder="{{ __('Name') }}">
                        </div>
                        <div class="col-7">
                            <select class="form-select form-select-sm" wire:model="accrualMethod">
                                <option value="annual">{{ __('Annual') }}</option>
                                <option value="monthly">{{ __('Monthly') }}</option>
                                <option value="none">{{ __('None') }}</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <input type="text" class="form-control form-control-sm" wire:model="annualEntitlementDays" placeholder="{{ __('Days/yr') }}">
                        </div>
                        <div class="col-12 form-check">
                            <input type="checkbox" class="form-check-input" id="isPaid" wire:model="isPaid">
                            <label class="form-check-label small" for="isPaid">{{ __('Paid') }}</label>
                        </div>
                        <div class="col-12 form-check">
                            <input type="checkbox" class="form-check-input" id="requiresDocument" wire:model="requiresDocument">
                            <label class="form-check-label small" for="requiresDocument">{{ __('Requires supporting document') }}</label>
                        </div>
                        <div class="col-12 form-check">
                            <input type="checkbox" class="form-check-input" id="requiresCover" wire:model="requiresCover">
                            <label class="form-check-label small" for="requiresCover">{{ __('Requires cover') }}</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="createLeaveType">{{ __('Create leave type') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
