<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('vendor.tenants.index') }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="ri ri-arrow-left-line"></i></a>
        <div><h4 class="mb-0">{{ $tenant->name }}</h4><p class="text-body-secondary small mb-0">{{ __('Every view of this page and every action against this tenant is audited.') }}</p></div>
        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" wire:click="recompute">{{ __('Recompute health') }}</button>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header">{{ __('Health — components') }} @if ($snapshot) <span class="text-body-secondary small">{{ $snapshot->snapshot_date->toFormattedDateString() }}</span> @endif</div>
            @if ($snapshot)
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Health score') }}</span><strong>{{ $snapshot->health_score !== null ? number_format($snapshot->health_score, 0) : '—' }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Subscription') }}</span><span>{{ __(ucfirst(str_replace('_', ' ', $snapshot->subscription_status))) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Days since last login') }}</span><span>{{ $snapshot->last_login_days_ago ?? '—' }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Module adoption') }}</span><span>{{ $snapshot->module_adoption_percent !== null ? number_format($snapshot->module_adoption_percent, 0).'%' : '—' }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Open support tickets') }}</span><span>{{ $snapshot->open_support_tickets }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Integrity check failures (24h)') }}</span><span>{{ $snapshot->integrity_check_failures }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Active schools / learners') }}</span><span>{{ $snapshot->active_schools }} / {{ $snapshot->active_learners }}</span></li>
                </ul>
            @else
                <div class="card-body text-body-secondary small">{{ __('No health snapshot yet — recompute to create one.') }}</div>
            @endif
        </div></div>
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header">{{ __('Subscription & schools') }}</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item">@if ($subscription){{ $subscription->plan?->name }} · {{ __(ucfirst(str_replace('_', ' ', $subscription->status))) }} · {{ __('renews') }} {{ $subscription->current_period_end?->toFormattedDateString() }}@else {{ __('No subscription.') }} @endif</li>
                @foreach ($schools as $school) <li class="list-group-item">{{ $school->name }} <span class="text-body-secondary">({{ $school->status }})</span></li> @endforeach
            </ul>
        </div></div>
    </div>
    <div class="row g-4">
        <div class="col-lg-6"><div class="card">
            <div class="card-header">{{ __('Support history') }}</div>
            <div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                @forelse ($tickets as $ticket)
                    <tr wire:key="tk-{{ $ticket->id }}"><td>{{ $ticket->subject }}</td><td>{{ __(ucfirst($ticket->priority)) }}</td><td>{{ __(ucfirst(str_replace('_', ' ', $ticket->status))) }}</td><td class="small">{{ $ticket->created_at?->toFormattedDateString() }}</td></tr>
                @empty
                    <tr><td class="text-body-secondary text-center py-3">{{ __('No tickets.') }}</td></tr>
                @endforelse
            </tbody></table></div>
        </div></div>
        <div class="col-lg-6"><div class="card">
            <div class="card-header">{{ __('Vendor-console audit trail') }}</div>
            <div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                @forelse ($audit as $entry)
                    <tr wire:key="au-{{ $entry->id }}"><td class="small">{{ $entry->created_at?->toDayDateTimeString() }}</td><td>{{ $entry->description }}</td><td class="small text-body-secondary">{{ $entry->ip_address }}</td></tr>
                @empty
                    <tr><td class="text-body-secondary text-center py-3">{{ __('Nothing recorded.') }}</td></tr>
                @endforelse
            </tbody></table></div>
        </div></div>
    </div>
    <div class="card mt-4">
        <div class="card-header">{{ __('Health history') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Date') }}</th><th class="text-end">{{ __('Score') }}</th><th class="text-end">{{ __('Last login') }}</th><th class="text-end">{{ __('Adoption') }}</th><th class="text-end">{{ __('Tickets') }}</th><th class="text-end">{{ __('Integrity') }}</th></tr></thead>
            <tbody>@foreach ($history as $row)<tr wire:key="hh-{{ $row->id }}"><td>{{ $row->snapshot_date->toFormattedDateString() }}</td><td class="text-end">{{ $row->health_score !== null ? number_format($row->health_score, 0) : '—' }}</td><td class="text-end">{{ $row->last_login_days_ago ?? '—' }}</td><td class="text-end">{{ $row->module_adoption_percent !== null ? number_format($row->module_adoption_percent, 0) : '—' }}</td><td class="text-end">{{ $row->open_support_tickets }}</td><td class="text-end">{{ $row->integrity_check_failures }}</td></tr>@endforeach</tbody>
        </table></div>
    </div>
</div>
