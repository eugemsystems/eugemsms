<div>
    <h4 class="mb-1">{{ __('Subject selection approvals') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Guardian approval, then school approval (which allocates immediately), or reject with a reason.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Level') }}</th><th>{{ __('Pathway') }}</th><th>{{ __('Subjects') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        <tr wire:key="submission-{{ $submission->id }}">
                            <td>{{ $submission->student?->first_name }} {{ $submission->student?->last_name }}</td>
                            <td>{{ $submission->gradeLevel?->name }}</td>
                            <td>{{ $submission->pathway?->code ?? '—' }}</td>
                            <td>{{ count($submission->selected_subject_ids ?? []) }}</td>
                            <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $submission->status) }}</span></td>
                            <td class="text-end">
                                @if ($submission->status === 'submitted')
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="guardianApprove({{ $submission->id }})">{{ __('Guardian approve') }}</button>
                                @endif
                                @if (in_array($submission->status, ['submitted', 'guardian_approved'], true))
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="schoolApprove({{ $submission->id }})" wire:confirm="{{ __('Approve and allocate these subjects now?') }}">{{ __('School approve') }}</button>
                                @endif
                                @if (in_array($submission->status, ['submitted', 'guardian_approved', 'school_approved'], true))
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <input type="text" class="form-control form-control-sm" style="width: 140px" wire:model="rejectionReasons.{{ $submission->id }}" placeholder="{{ __('Reason') }}">
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $submission->id }})">{{ __('Reject') }}</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No submissions awaiting approval.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
