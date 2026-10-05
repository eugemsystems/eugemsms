<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Support queue') }}</h4><p class="text-body-secondary small mb-0">{{ __('Tickets from school users to the vendor, soonest SLA first. These never appear in a school’s own complaint queue.') }}</p></div>
    <div class="mb-3"><select class="form-select form-select-sm w-auto" wire:model.live="statusFilter">
        <option value="active">{{ __('Active') }}</option>
        @foreach (array_keys($transitions) as $value) <option value="{{ $value }}">{{ __(ucfirst(str_replace('_', ' ', $value))) }}</option> @endforeach
    </select></div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('From') }}</th><th>{{ __('Priority') }}</th><th>{{ __('SLA due') }}</th><th>{{ __('Status') }}</th><th>{{ __('Assignee') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr wire:key="q-{{ $ticket->id }}">
                        <td>{{ $ticket->subject }}<div class="small text-body-secondary">{{ str_replace('_', ' ', $ticket->category) }}</div></td>
                        <td class="small">{{ $authors[$ticket->raised_by_user_id] ?? '—' }}<br>{{ $tenantNames[$ticket->tenant_id] ?? '' }} · {{ $schoolNames[$ticket->school_id] ?? '' }}</td>
                        <td>{{ __(ucfirst($ticket->priority)) }}</td>
                        <td class="small">{{ $ticket->sla_due_at?->toDayDateTimeString() }} @if ($ticket->isBreached()) <span class="badge text-bg-danger">{{ __('Breached') }}</span> @endif</td>
                        <td>{{ __(ucfirst(str_replace('_', ' ', $ticket->status))) }}</td>
                        <td>
                            <select class="form-select form-select-sm" wire:change="assign({{ $ticket->id }}, $event.target.value)">
                                <option value="">{{ __('Unassigned') }}</option>
                                @foreach ($staff as $member) <option value="{{ $member->id }}" @selected($ticket->assigned_vendor_staff_id === $member->id)>{{ $member->name }}</option> @endforeach
                            </select>
                        </td>
                        <td class="text-end text-nowrap">@foreach ($transitions[$ticket->status] ?? [] as $next) <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="changeStatus({{ $ticket->id }}, '{{ $next }}')">{{ __(ucfirst(str_replace('_', ' ', $next))) }}</button> @endforeach</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No tickets.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
