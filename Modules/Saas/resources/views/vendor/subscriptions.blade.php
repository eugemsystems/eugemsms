<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Subscriptions') }}</h4><p class="text-body-secondary small mb-0">{{ __('Lifecycle across tenants. Every move is audited. Safeguarding is never gated by a subscription.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                    <select class="form-select form-select-sm w-auto" wire:model.live="statusFilter">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (array_keys($moves) as $value) <option value="{{ $value }}">{{ __(ucfirst(str_replace('_', ' ', $value))) }}</option> @endforeach
                    </select>
                    <input type="text" class="form-control form-control-sm w-auto ms-auto" wire:model="cancelReason" placeholder="{{ __('Reason (needed to cancel)') }}">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Tenant') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Status') }}</th><th>{{ __('Period ends') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($subscriptions as $subscription)
                                <tr wire:key="sub-{{ $subscription->id }}">
                                    <td>{{ $subscription->tenant?->name }}</td><td>{{ $subscription->plan?->name }}</td>
                                    <td><span class="badge text-bg-{{ ['active' => 'success', 'trial' => 'info', 'cancelled' => 'secondary'][$subscription->status] ?? 'warning' }}">{{ __(ucfirst(str_replace('_', ' ', $subscription->status))) }}</span></td>
                                    <td class="small">{{ $subscription->current_period_end?->toFormattedDateString() }}</td>
                                    <td class="text-end text-nowrap">
                                        @foreach ($moves[$subscription->status] ?? [] as $move)
                                            <button type="button" class="btn btn-sm btn-outline-{{ in_array($move, ['cancel', 'suspend']) ? 'danger' : 'secondary' }}" wire:click="move({{ $subscription->id }}, '{{ $move }}')" wire:confirm="{{ __('Apply “:move” to this subscription?', ['move' => str_replace('_', ' ', $move)]) }}">{{ __(ucfirst(str_replace('_', ' ', $move))) }}</button>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No subscriptions.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">{{ __('Open a subscription') }}</div>
                <div class="card-body">
                    <select class="form-select form-select-sm mb-2" wire:model.live="tenantId"><option value="">{{ __('Tenant…') }}</option>@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select>
                    @error('tenantId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <select class="form-select form-select-sm mb-2" wire:model="planId"><option value="">{{ __('Plan…') }}</option>@foreach ($plans as $plan) <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->code }})</option> @endforeach</select>
                    @error('planId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="small mb-1">{{ __('Covered schools') }}</div>
                    @forelse ($schools as $school)
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="sch-{{ $school->id }}" value="{{ $school->id }}" wire:model="schoolIds"><label class="form-check-label small" for="sch-{{ $school->id }}">{{ $school->name }}</label></div>
                    @empty
                        <div class="small text-body-secondary mb-2">{{ __('Choose a tenant first.') }}</div>
                    @endforelse
                    @error('schoolIds') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="row g-2 my-1">
                        <div class="col-6"><input type="date" class="form-control form-control-sm" wire:model="periodStart"></div>
                        <div class="col-6"><input type="date" class="form-control form-control-sm" wire:model="periodEnd"></div>
                    </div>
                    @error('periodEnd') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="row g-2 mb-2">
                        <div class="col-4"><input type="text" maxlength="3" class="form-control form-control-sm text-uppercase" wire:model="currency"></div>
                        <div class="col-4"><select class="form-select form-select-sm" wire:model="status"><option value="trial">{{ __('Trial') }}</option><option value="active">{{ __('Active') }}</option></select></div>
                        <div class="col-4"><input type="number" min="0" class="form-control form-control-sm" wire:model="learnerCount" placeholder="{{ __('Learners') }}"></div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Open') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
