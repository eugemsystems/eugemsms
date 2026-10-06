<div>
    <h4 class="mb-1">{{ __('Prior schooling') }}</h4>
    <p class="text-body-secondary mb-4">{{ $student->fullName() }} — {{ $student->admission_number }} · <a href="{{ route('people.students.show', [$school, $student]) }}" wire:navigate>{{ __('Back to profile') }}</a></p>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('School') }}</th><th>{{ __('Attended') }}</th><th>{{ __('Last grade') }}</th><th>{{ __('Left because') }}</th></tr></thead>
            <tbody>@forelse ($schools as $row) <tr wire:key="p-{{ $row->id }}"><td>{{ $row->school_name }} <span class="small text-body-secondary">{{ $row->school_type }} {{ $row->country }}</span> @if ($row->had_outstanding_fees) <span class="badge text-bg-warning">{{ __('fees owing') }}</span> @endif</td><td class="small">{{ $row->attended_from?->format('M Y') }} – {{ $row->attended_to?->format('M Y') }}</td><td>{{ $row->last_grade_completed }}</td><td class="small">{{ $row->reason_for_leaving }}</td></tr> @empty <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Nothing recorded.') }}</td></tr> @endforelse</tbody>
        </table></div></div></div>
        @if ($canRecord)
            <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Add a previous school') }}</div><div class="card-body">
                <input type="text" class="form-control form-control-sm mb-2" wire:model="schoolName" placeholder="{{ __('School name') }}">
                @error('schoolName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <div class="row g-2 mb-2"><div class="col-8"><select class="form-select form-select-sm" wire:model="schoolType"><option value="">{{ __('Type…') }}</option>@foreach (['government', 'council', 'mission', 'private', 'home_school', 'foreign'] as $t) <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option> @endforeach</select></div><div class="col-4"><input type="text" class="form-control form-control-sm" maxlength="2" wire:model="country"></div></div>
                <div class="row g-2 mb-2"><div class="col-6"><input type="date" class="form-control form-control-sm" wire:model="attendedFrom"></div><div class="col-6"><input type="date" class="form-control form-control-sm" wire:model="attendedTo"></div></div>
                <input type="text" class="form-control form-control-sm mb-2" wire:model="lastGrade" placeholder="{{ __('Last grade completed') }}">
                <input type="text" class="form-control form-control-sm mb-2" wire:model="reasonForLeaving" placeholder="{{ __('Reason for leaving') }}">
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="owing" wire:model="hadOutstandingFees"><label class="form-check-label small" for="owing">{{ __('Fees were left owing there') }}</label></div>
                <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Add') }}</button>
            </div></div></div>
        @endif
    </div>
</div>
