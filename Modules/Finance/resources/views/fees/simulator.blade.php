<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Fee simulator') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('What would this learner pay? Simulated against a real learner\'s section/grade/enrolment type — pick any current learner matching the scenario you have in mind.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form wire:submit="preview">
                <div class="mb-3">
                    <label class="form-label" for="studentSearch">{{ __('Learner') }}</label>
                    @if ($selectedStudentId !== null)
                        <div class="input-group">
                            <input type="text" class="form-control" value="{{ $selectedStudentLabel }}" disabled>
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('selectedStudentId', null)">{{ __('Change') }}</button>
                        </div>
                    @else
                        <input type="text" class="form-control @error('selectedStudentId') is-invalid @enderror" id="studentSearch" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Search by admission number or name…') }}">
                        @error('selectedStudentId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @if ($studentSearch !== '')
                            <div class="list-group mt-1">
                                @forelse ($this->studentResults() as $result)
                                    <button type="button" wire:key="result-{{ $result->id }}" class="list-group-item list-group-item-action" wire:click="selectStudent({{ $result->id }})">
                                        {{ $result->admission_number }} — {{ $result->fullName() }}
                                    </button>
                                @empty
                                    <div class="list-group-item text-body-secondary">{{ __('No matches.') }}</div>
                                @endforelse
                            </div>
                        @endif
                    @endif
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('termId') is-invalid @enderror" id="termId" wire:model="termId">
                                @foreach ($terms as $term)
                                    <option value="{{ $term->id }}">{{ $term->academicYear->name }} — {{ $term->name }}</option>
                                @endforeach
                            </select>
                            <label for="termId">{{ __('Term') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="subjectIds" wire:model="subjectIds" multiple size="1">
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                            <label for="subjectIds">{{ __('Proposed subjects (part-time only, optional)') }}</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">{{ __('Simulate') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if ($result !== null)
        <div class="card">
            <div class="card-header"><h6 class="mb-0">{{ __('Result') }}</h6></div>
            <div class="card-body">
                @if ($result->amountMinor === null)
                    <div class="alert alert-warning mb-0">{{ __('No fee structure matches this learner — this would be an exception, not a zero charge.') }}</div>
                @else
                    <p class="fs-4 mb-3">{{ number_format($result->amountMinor / 100, 2) }} {{ $result->currency }}</p>
                @endif
                <h6 class="small text-uppercase text-body-secondary">{{ __('Resolution trace') }}</h6>
                <pre class="small bg-body-tertiary p-3 mb-0" style="white-space: pre-wrap;">{{ json_encode($result->trace, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    @endif
</div>
