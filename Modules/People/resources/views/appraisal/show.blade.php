<div>
    <h4 class="mb-1">{{ __('Appraisal') }}</h4>
    <p class="text-body-secondary mb-4">
        {{ $appraisal->staff?->fullName() }} — {{ ucfirst($appraisal->cycle) }} ·
        <span class="badge text-bg-secondary">{{ str_replace('_', ' ', ucfirst($appraisal->status)) }}</span>
    </p>

    <div class="card mb-4">
        <div class="card-header">{{ __('Self-assessment') }}</div>
        <div class="card-body">
            @if ($appraisal->self_assessment)
                <p class="mb-0">{{ $appraisal->self_assessment['notes'] ?? '' }}</p>
            @elseif ($appraisal->status === 'draft')
                <textarea class="form-control @error('selfAssessmentNotes') is-invalid @enderror" wire:model="selfAssessmentNotes" rows="3" placeholder="{{ __('Self-assessment notes') }}"></textarea>
                <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="submitSelfAssessment">{{ __('Submit self-assessment') }}</button>
            @else
                <p class="text-body-secondary mb-0">{{ __('Not submitted.') }}</p>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ __('Appraiser assessment') }}</div>
        <div class="card-body">
            @if ($appraisal->appraiser_assessment)
                <p>{{ $appraisal->appraiser_assessment['notes'] ?? '' }}</p>
                <dl class="row mb-0">
                    <dt class="col-3">{{ __('Overall rating') }}</dt><dd class="col-9">{{ $appraisal->overall_rating ?? '—' }}</dd>
                    <dt class="col-3">{{ __('Development plan') }}</dt><dd class="col-9">{{ $appraisal->development_plan ?? '—' }}</dd>
                </dl>
            @elseif ($appraisal->status === 'self_assessment')
                <textarea class="form-control @error('appraiserAssessmentNotes') is-invalid @enderror" wire:model="appraiserAssessmentNotes" rows="3" placeholder="{{ __('Appraiser assessment notes') }}"></textarea>
                <div class="row g-2 mt-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" wire:model="overallRating" placeholder="{{ __('Overall rating') }}">
                    </div>
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-sm" wire:model="developmentPlan" placeholder="{{ __('Development plan') }}">
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="submitAppraiserAssessment">{{ __('Submit appraiser assessment') }}</button>
            @else
                <p class="text-body-secondary mb-0">{{ __('Awaiting the self-assessment first.') }}</p>
            @endif
        </div>
    </div>

    @if ($appraisal->status === 'appraiser_review')
        <div class="card mb-4">
            <div class="card-body">
                <button type="button" class="btn btn-primary" wire:click="recordMeeting">{{ __('Record appraisal meeting held') }}</button>
            </div>
        </div>
    @endif

    @if ($appraisal->status === 'meeting_held')
        <div class="card">
            <div class="card-header">{{ __('Sign off') }}</div>
            <div class="card-body">
                <textarea class="form-control" wire:model="staffComments" rows="2" placeholder="{{ __('Staff comments (optional)') }}"></textarea>
                <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="signOff" wire:confirm="{{ __('Sign off this appraisal? This is final.') }}">{{ __('Sign off') }}</button>
            </div>
        </div>
    @endif

    @if ($appraisal->status === 'signed_off')
        <div class="alert alert-success">{{ __('Signed off on :date.', ['date' => $appraisal->signed_off_at?->format('d M Y')]) }}</div>
    @endif
</div>
