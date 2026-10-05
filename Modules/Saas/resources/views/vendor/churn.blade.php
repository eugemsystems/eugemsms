<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Churn risk') }}</h4><p class="text-body-secondary small mb-0">{{ __('Every flag shows the specific, sourced signals behind it. Advisory only — a person decides what to do.') }}</p></div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="statusFilter"><option value="">{{ __('All') }}</option>@foreach (['open', 'intervention_logged', 'resolved', 'churned'] as $s) <option value="{{ $s }}">{{ __(ucfirst(str_replace('_', ' ', $s))) }}</option> @endforeach</select>
        <select class="form-select form-select-sm w-auto ms-auto" wire:model="tenantId"><option value="">{{ __('Tenant…') }}</option>@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select>
        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="evaluate">{{ __('Evaluate') }}</button>
    </div>
    @forelse ($flags as $flag)
        <div class="card mb-3" wire:key="cf-{{ $flag->id }}">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <strong>{{ $tenantNames[$flag->tenant_id] ?? '—' }}</strong>
                <span class="badge text-bg-secondary">{{ __(ucfirst(str_replace('_', ' ', $flag->status))) }}</span>
                <span class="small text-body-secondary">{{ $flag->flagged_at?->toDayDateTimeString() }}</span>
                <select class="form-select form-select-sm w-auto ms-auto" wire:change="assign({{ $flag->id }}, $event.target.value)"><option value="">{{ __('Unassigned') }}</option>@foreach ($staff as $member) <option value="{{ $member->id }}" @selected($flag->assigned_to === $member->id)>{{ $member->name }}</option> @endforeach</select>
            </div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Signal') }}</th><th class="text-end">{{ __('Weight') }}</th><th class="text-end">{{ __('Contribution') }}</th><th>{{ __('Source') }}</th></tr></thead>
                <tbody>@foreach ($flag->contributing_factors as $factor)<tr><td>{{ $factor['plain_language'] }}</td><td class="text-end">{{ $factor['weight'] }}</td><td class="text-end">{{ $factor['contribution'] }}</td><td class="small text-body-secondary">{{ $factor['source'] }}</td></tr>@endforeach</tbody>
            </table></div>
            @if (in_array($flag->status, ['open', 'intervention_logged']))
                <div class="card-footer d-flex gap-2">
                    @if ($flag->status === 'open') <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="move({{ $flag->id }}, 'intervention_logged')">{{ __('Intervention logged') }}</button> @endif
                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="move({{ $flag->id }}, 'resolved')">{{ __('Resolved') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="move({{ $flag->id }}, 'churned')" wire:confirm="{{ __('Mark this tenant as churned?') }}">{{ __('Churned') }}</button>
                </div>
            @endif
        </div>
    @empty
        <div class="text-body-secondary">{{ __('No flags.') }}</div>
    @endforelse
</div>
