<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Scholarship applications') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Means data and applications for application-based schemes. Decisions are made by the committee, not here.') }}</p>
    </div>
    <div class="row g-4">
        <div class="{{ $canApply ? 'col-xl-8' : 'col-12' }}">
            <div class="mb-3"><select class="form-select form-select-sm w-auto" wire:model.live="statusFilter"><option value="">{{ __('All statuses') }}</option>@foreach (['submitted', 'under_review', 'committee_review', 'approved', 'rejected', 'waitlisted'] as $s) <option value="{{ $s }}">{{ __(ucfirst(str_replace('_', ' ', $s))) }}</option> @endforeach</select></div>
            <div class="card"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Scheme') }}</th><th>{{ __('Income band') }}</th><th class="text-end">{{ __('Means') }}</th><th class="text-end">{{ __('Average') }}</th><th class="text-end">{{ __('Documents') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($applications as $application)
                            <tr wire:key="ap-{{ $application->id }}">
                                <td>{{ $students[$application->student_id]?->fullName() ?? '—' }}</td>
                                <td>{{ $schemeNames[$application->scheme_id] ?? '—' }}</td>
                                <td>{{ $application->household_income_band ?? '—' }}</td>
                                <td class="text-end">{{ $application->means_assessment_score ?? '—' }}</td>
                                <td class="text-end">{{ $application->academic_average_at_application ?? '—' }}</td>
                                <td class="text-end">{{ count($application->supporting_document_ids ?? []) }}</td>
                                <td>{{ __(ucfirst(str_replace('_', ' ', $application->status))) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No applications.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
        @if ($canApply)
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Submit on a family’s behalf') }}</div><div class="card-body">
            @if ($selectedStudentId) <div class="small mb-2">{{ $selectedStudentLabel }}</div> @else
                <input type="search" class="form-control form-control-sm mb-1" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Find learner…') }}">
                @foreach ($results as $student) <button type="button" class="btn btn-sm btn-link d-block text-start" wire:click="selectStudent({{ $student->id }})">{{ $student->admission_number }} — {{ $student->fullName() }}</button> @endforeach
            @endif
            @error('selectedStudentId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm my-2" wire:model="schemeId"><option value="">{{ __('Scheme…') }}</option>@foreach ($schemes as $scheme) <option value="{{ $scheme->id }}">{{ $scheme->name }}</option> @endforeach</select>
            @error('schemeId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm mb-2" wire:model="academicYearId">@foreach ($years as $year) <option value="{{ $year->id }}">{{ $year->name }}</option> @endforeach</select>
            <input type="text" class="form-control form-control-sm mb-2" wire:model="incomeBand" placeholder="{{ __('Household income band') }}">
            <div class="row g-2 mb-2"><div class="col-6"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="meansScore" placeholder="{{ __('Means score') }}"></div><div class="col-6"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="academicAverage" placeholder="{{ __('Average %') }}"></div></div>
            <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="narrative" placeholder="{{ __('Narrative') }}"></textarea>
            <button type="button" class="btn btn-primary btn-sm" wire:click="submit">{{ __('Submit') }}</button>
        </div></div></div>
        @endif
    </div>
</div>
