<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Leave approvals') }}</h4>
            <p class="text-body-secondary mb-0">{{ $school->name }}</p>
        </div>
        <a href="{{ route('people.leave.balances', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Balances') }}</a>
        <a href="{{ route('people.leave.request', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('New request') }}</a>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Pending requests') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Days') }}</th><th>{{ __('Cover') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pending as $request)
                        <tr wire:key="pending-{{ $request->id }}">
                            <td>{{ $request->staff?->fullName() }}</td>
                            <td>{{ $request->leaveType?->name }}</td>
                            <td>{{ $request->starts_on->format('d M') }} – {{ $request->ends_on->format('d M Y') }}</td>
                            <td>{{ $request->working_days }}</td>
                            <td>{{ $request->coverStaff?->fullName() ?? '—' }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $request->id }})">{{ __('Approve') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $request->id }})" wire:confirm="{{ __('Reject this leave request?') }}">{{ __('Reject') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No pending leave requests.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <input type="text" class="form-control form-control-sm" wire:model="rejectReason" placeholder="{{ __('Rejection reason (optional, used by the Reject button above)') }}">
        </div>
    </div>
</div>
