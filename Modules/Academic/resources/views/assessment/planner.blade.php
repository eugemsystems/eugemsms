<div>
    <h4 class="mb-1">{{ __('Assessment planner') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Per subject per term — weights should total 100% before results computation.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Assessments this term') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Max') }}</th><th>{{ __('Weight') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($assessments as $assessment)
                                <tr wire:key="assessment-{{ $assessment->id }}">
                                    <td>
                                        {{ $assessment->subject?->name }}
                                        @if (($weightTotals[$assessment->subject_id] ?? 0) != 100)
                                            <span class="badge text-bg-warning ms-1" title="{{ __('Weights do not total 100%') }}">{{ number_format($weightTotals[$assessment->subject_id] ?? 0, 0) }}%</span>
                                        @endif
                                    </td>
                                    <td>{{ $assessment->title }}</td>
                                    <td>{{ $assessment->assessmentType?->name }}</td>
                                    <td>{{ $assessment->max_mark }}</td>
                                    <td>{{ $assessment->weight_percent }}%</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($assessment->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No assessments planned this term yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New assessment') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('subjectId') is-invalid @enderror" wire:model="subjectId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('assessmentTypeId') is-invalid @enderror" wire:model="assessmentTypeId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($assessmentTypes as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Assessment type') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder=" ">
                                    <label>{{ __('Title') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" step="0.01" class="form-control @error('maxMark') is-invalid @enderror" wire:model="maxMark" placeholder=" ">
                                    <label>{{ __('Max mark') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" step="0.01" class="form-control @error('weightPercent') is-invalid @enderror" wire:model="weightPercent" placeholder=" ">
                                    <label>{{ __('Weight %') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control" wire:model="assessedOn">
                                    <label>{{ __('Assessed on') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create assessment') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
