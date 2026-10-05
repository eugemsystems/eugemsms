<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Tenants') }}</h4><p class="text-body-secondary small mb-0">{{ __('Health is shown with the signals behind it — never as a bare score.') }}</p></div>
    <div class="mb-3"><input type="search" class="form-control form-control-sm w-auto" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search tenants') }}"></div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Tenant') }}</th><th>{{ __('Subscription') }}</th><th class="text-end">{{ __('Health') }}</th><th class="text-end">{{ __('Last login (days)') }}</th><th class="text-end">{{ __('Adoption %') }}</th><th class="text-end">{{ __('Open tickets') }}</th><th class="text-end">{{ __('Integrity fails') }}</th><th class="text-end">{{ __('Learners') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    @php($snap = $latest[$tenant->id] ?? null)
                    <tr wire:key="t-{{ $tenant->id }}">
                        <td><a href="{{ route('vendor.tenants.show', $tenant->id) }}" wire:navigate>{{ $tenant->name }}</a></td>
                        <td>{{ __(ucfirst(str_replace('_', ' ', $snap?->subscription_status ?? $tenant->status))) }}</td>
                        <td class="text-end">@if ($snap?->health_score !== null)<span class="badge text-bg-{{ $snap->health_score >= 75 ? 'success' : ($snap->health_score >= 50 ? 'warning' : 'danger') }}">{{ number_format($snap->health_score, 0) }}</span>@else — @endif</td>
                        <td class="text-end">{{ $snap?->last_login_days_ago ?? '—' }}</td>
                        <td class="text-end">{{ $snap?->module_adoption_percent !== null ? number_format($snap->module_adoption_percent, 0) : '—' }}</td>
                        <td class="text-end">{{ $snap?->open_support_tickets ?? '—' }}</td>
                        <td class="text-end">{{ $snap?->integrity_check_failures ?? '—' }}</td>
                        <td class="text-end">{{ $snap?->active_learners ?? '—' }}</td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="recompute({{ $tenant->id }})">{{ __('Recompute') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-body-secondary py-3">{{ __('No tenants.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
