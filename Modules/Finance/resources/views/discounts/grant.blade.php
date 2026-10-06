<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Grant award') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('The scheme’s budget envelope is checked before you submit. An award above the approval threshold waits for approval before it takes effect.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-7"><div class="card"><div class="card-body">
            @if ($selectedStudentId) <div class="mb-2"><strong>{{ $selectedStudentLabel }}</strong></div> @else
                <input type="search" class="form-control form-control-sm mb-1" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Find learner…') }}">
                @foreach ($results as $student) <button type="button" class="btn btn-sm btn-link d-block text-start" wire:click="selectStudent({{ $student->id }})">{{ $student->admission_number }} — {{ $student->fullName() }}</button> @endforeach
            @endif
            @error('selectedStudentId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="row g-2 my-1">
                <div class="col-md-6"><select class="form-select form-select-sm" wire:model.live="schemeId"><option value="">{{ __('Scheme…') }}</option>@foreach ($schemes as $s) <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option> @endforeach</select></div>
                <div class="col-md-3"><select class="form-select form-select-sm" wire:model.live="academicYearId">@foreach ($years as $year) <option value="{{ $year->id }}">{{ $year->name }}</option> @endforeach</select></div>
                <div class="col-md-3"><select class="form-select form-select-sm" wire:model="termId"><option value="">{{ __('Whole year') }}</option>@foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach</select></div>
            </div>
            @error('schemeId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            @if ($scheme && $scheme->scheme_type === 'application_based')
                <select class="form-select form-select-sm mb-2" wire:model="applicationId"><option value="">{{ __('Approved application…') }}</option>@foreach ($applications as $application) <option value="{{ $application->id }}">{{ __('Application') }} #{{ $application->id }}</option> @endforeach</select>
                @if ($selectedStudentId && $applications->isEmpty()) <div class="small text-warning mb-2">{{ __('This learner has no approved application for the scheme.') }}</div> @endif
            @endif
            <div class="row g-2 mb-2">
                <div class="col-md-4"><select class="form-select form-select-sm" wire:model.live="awardMethod"><option value="percentage">{{ __('Percentage') }}</option><option value="fixed_amount">{{ __('Fixed amount') }}</option></select></div>
                @if ($awardMethod === 'percentage') <div class="col-md-4"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="percent" placeholder="%"></div>
                @else <div class="col-md-5"><input type="number" step="0.01" class="form-control form-control-sm" wire:model.live.debounce.400ms="amount" placeholder="{{ __('Amount') }}"></div><div class="col-md-3"><input type="text" maxlength="3" class="form-control form-control-sm text-uppercase" wire:model="currency"></div> @endif
            </div>
            @error('percent') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="small mb-1">{{ __('Components (none = the scheme’s default / all)') }}</div>
            <select multiple class="form-select form-select-sm mb-2" size="4" wire:model="componentIds">@foreach ($components as $component) <option value="{{ $component->id }}">{{ $component->name }}</option> @endforeach</select>
            @if ($scheme && $scheme->is_sponsor_funded)
                <div class="mb-2">@if ($sponsor) <strong>{{ trim($sponsor->first_name.' '.$sponsor->last_name) ?: $sponsor->organisation_name }}</strong> @else
                    <input type="search" class="form-control form-control-sm" wire:model.live.debounce.300ms="sponsorSearch" placeholder="{{ __('Find the sponsoring guardian or organisation…') }}">
                    @foreach ($sponsors as $guardian) <button type="button" class="btn btn-sm btn-link d-block text-start" wire:click="selectSponsor({{ $guardian->id }})">{{ trim($guardian->first_name.' '.$guardian->last_name) ?: $guardian->organisation_name }}</button> @endforeach
                @endif</div>
            @endif
            <div class="row g-2 mb-2"><div class="col-md-5"><input type="date" class="form-control form-control-sm" wire:model="effectiveFrom">@error('effectiveFrom') <div class="text-danger small">{{ $message }}</div> @enderror</div><div class="col-md-7"><input type="text" class="form-control form-control-sm" wire:model="conditionNote" placeholder="{{ __('Condition, e.g. maintain 60% average') }}"></div></div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="grant" wire:confirm="{{ __('Grant this award?') }}">{{ __('Grant award') }}</button>
        </div></div></div>
        <div class="col-xl-5"><div class="card"><div class="card-header">{{ __('Budget envelope') }}</div><div class="card-body small">
            @if (! $preview) <span class="text-body-secondary">{{ __('Choose a scheme and year.') }}</span>
            @elseif (! $preview['capped']) {{ __('Uncapped — no budget limit applies at grant time.') }}
            @else
                <div class="d-flex justify-content-between"><span>{{ __('Budget') }}</span><strong>{{ number_format($preview['budget_minor'] / 100, 2) }} {{ $preview['currency'] }}</strong></div>
                <div class="d-flex justify-content-between"><span>{{ __('Remaining') }}</span><strong>{{ number_format($preview['remaining_minor'] / 100, 2) }}</strong></div>
                @if ($preview['shortfall_minor'] > 0) <div class="alert alert-danger mt-2 mb-0">{{ __('This award exceeds the envelope by :amount. It will be refused — raise the budget or choose another scheme.', ['amount' => number_format($preview['shortfall_minor'] / 100, 2)]) }}</div>
                @elseif ($awardMethod === 'percentage') <div class="text-body-secondary mt-2">{{ __('A percentage award is checked against the envelope when it is billed.') }}</div> @endif
            @endif
        </div></div></div>
    </div>
</div>
