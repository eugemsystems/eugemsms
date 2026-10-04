<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Leave requests') }}</h4>
            <p class="text-body-secondary mb-0">{{ $school->name }}</p>
        </div>
        <a href="{{ route('people.leave.balances', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Balances') }}</a>
        <a href="{{ route('people.leave.approvals', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Approvals') }}</a>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New request') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <select class="form-select form-select-sm @error('staffId') is-invalid @enderror" wire:model="staffId">
                                <option value="">{{ __('Select staff member') }}</option>
                                @foreach ($staffList as $member)
                                    <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <select class="form-select form-select-sm @error('leaveTypeId') is-invalid @enderror" wire:model="leaveTypeId">
                                <option value="">{{ __('Select leave type') }}</option>
                                @foreach ($leaveTypes as $leaveType)
                                    <option value="{{ $leaveType->id }}">{{ $leaveType->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control form-control-sm @error('startsOn') is-invalid @enderror" wire:model="startsOn">
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control form-control-sm @error('endsOn') is-invalid @enderror" wire:model="endsOn">
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm @error('workingDays') is-invalid @enderror" wire:model="workingDays" placeholder="{{ __('Working days') }}">
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" wire:model="coverStaffId">
                                <option value="">{{ __('No cover') }}</option>
                                @foreach ($staffList as $member)
                                    <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <input type="text" class="form-control form-control-sm" wire:model="reason" placeholder="{{ __('Reason (optional)') }}">
                        </div>
                        <div class="col-12 form-check">
                            <input type="checkbox" class="form-check-input" id="approveOverdraft" wire:model="approveOverdraft">
                            <label class="form-check-label small" for="approveOverdraft">{{ __('Approve overdraft beyond available balance (BR-PPL-04-010)') }}</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="submit">{{ __('Submit request') }}</button>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Recent requests') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Staff') }}</th><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($requests as $request)
                                <tr wire:key="leave-request-{{ $request->id }}">
                                    <td>{{ $request->staff?->fullName() }}</td>
                                    <td>{{ $request->leaveType?->name }}</td>
                                    <td>{{ $request->starts_on->format('d M') }} – {{ $request->ends_on->format('d M Y') }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($request->status) }}</span></td>
                                    <td class="text-end">
                                        @if (in_array($request->status, ['pending', 'approved']))
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="cancel({{ $request->id }})" wire:confirm="{{ __('Cancel this leave request?') }}">{{ __('Cancel') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No leave requests yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
