<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Committee review') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Decide each application and record why. A decision is permanent; approving does not grant an award — that is a separate step.') }}</p>
    </div>
    @forelse ($applications as $application)
        <div class="card mb-3" wire:key="cm-{{ $application->id }}">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center"><strong>{{ $students[$application->student_id]?->fullName() ?? '—' }}</strong><span class="text-body-secondary small">{{ $schemes[$application->scheme_id]?->name }}</span><span class="badge text-bg-secondary">{{ __(ucfirst(str_replace('_', ' ', $application->status))) }}</span><button type="button" class="btn btn-sm btn-outline-primary ms-auto" wire:click="begin({{ $application->id }})">{{ __('Decide') }}</button></div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-3">{{ __('Income band') }}: <strong>{{ $application->household_income_band ?? '—' }}</strong></div>
                    <div class="col-md-3">{{ __('Means score') }}: <strong>{{ $application->means_assessment_score ?? '—' }}</strong></div>
                    <div class="col-md-3">{{ __('Average at application') }}: <strong>{{ $application->academic_average_at_application ?? '—' }}</strong></div>
                    <div class="col-md-3">{{ __('Documents') }}: <strong>{{ count($application->supporting_document_ids ?? []) }}</strong></div>
                </div>
                @if ($application->narrative) <p class="mt-2 mb-0" style="white-space: pre-line">{{ $application->narrative }}</p> @endif
                @if ($decidingId === $application->id)
                    <div class="row g-2 mt-2">
                        <div class="col-md-3"><select class="form-select form-select-sm" wire:model.live="status">@foreach (['under_review', 'committee_review', 'approved', 'waitlisted', 'rejected'] as $s) <option value="{{ $s }}">{{ __(ucfirst(str_replace('_', ' ', $s))) }}</option> @endforeach</select></div>
                        <div class="col-md-5"><textarea class="form-control form-control-sm" rows="2" wire:model="notes" placeholder="{{ __('Committee rationale (kept permanently)') }}"></textarea>@error('notes') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                        <div class="col-md-4">@if ($status === 'rejected') <input type="text" class="form-control form-control-sm mb-2" wire:model="rejectionReason" placeholder="{{ __('Reason for rejection') }}"> @endif<button type="button" class="btn btn-primary btn-sm" wire:click="decide" wire:confirm="{{ __('Record this decision?') }}">{{ __('Record') }}</button></div>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-body-secondary">{{ __('Nothing waiting for the committee.') }}</div>
    @endforelse
</div>
