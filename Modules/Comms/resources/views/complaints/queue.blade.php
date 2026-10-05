<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Complaint queue') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Soonest deadline first. A complaint referred to safeguarding is shown by number only — its content is held in the safeguarding case.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('comms.complaints.submit', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Raise a complaint') }}</a>
            <a href="{{ route('comms.complaints.categories', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Categories') }}</a>
            <a href="{{ route('comms.exit-interviews', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Exit interviews') }}</a>
        </div>
    </div>

    <select class="form-select form-select-sm mb-3 w-auto" wire:model.live="statusFilter">
        <option value="open">{{ __('Open') }}</option>
        <option value="overdue">{{ __('Overdue') }}</option>
        <option value="received">{{ __('Received') }}</option>
        <option value="acknowledged">{{ __('Acknowledged') }}</option>
        <option value="investigating">{{ __('Investigating') }}</option>
        <option value="escalated">{{ __('Escalated') }}</option>
        <option value="resolved">{{ __('Resolved') }}</option>
        <option value="closed">{{ __('Closed') }}</option>
        <option value="all">{{ __('All') }}</option>
    </select>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Category') }}</th><th>{{ __('Assigned') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($complaints as $complaint)
                        @php $routed = $complaint->isRoutedToSafeguarding(); @endphp
                        <tr wire:key="cq-{{ $complaint->id }}" class="{{ ! $routed && $complaint->isBreached() ? 'table-danger' : '' }}">
                            <td><a href="{{ route('comms.complaints.show', [$school, $complaint->ulid]) }}" wire:navigate>{{ $complaint->complaint_number }}</a></td>
                            <td>{{ $routed ? __('Referred to safeguarding') : $subjects[$complaint->id] ?? '' }}</td>
                            <td class="small">{{ $complaint->category?->name }}</td>
                            <td class="small">{{ $assignees->get($complaint->assigned_to_staff_id)?->fullName() ?? ($routed ? '—' : __('unassigned')) }}</td>
                            <td class="small">
                                @if ($routed) — @else
                                    {{ $complaint->sla_due_at->diffForHumans() }}
                                    @if ($complaint->isBreached()) <span class="badge bg-label-danger">{{ __('overdue') }}</span> @endif
                                @endif
                            </td>
                            <td><span class="badge {{ $complaint->status === 'escalated' ? 'bg-label-warning' : (in_array($complaint->status, ['resolved', 'closed'], true) ? 'bg-label-success' : 'bg-label-secondary') }}">{{ $complaint->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No complaints.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
