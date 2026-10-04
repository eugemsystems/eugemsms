<div>
    <h4 class="mb-1">{{ __('Project rubrics') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-6">
            @forelse ($rubrics as $rubric)
                <div class="card mb-3" wire:key="rubric-{{ $rubric->id }}">
                    <div class="card-header">{{ $rubric->name }} ({{ $rubric->total_mark }} {{ __('marks') }})</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($rubric->criteria as $criterion)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $criterion->criterion }}</span>
                                <span class="text-body-secondary">{{ $criterion->max_mark }} {{ __('marks') }} ({{ $criterion->weight_percent }}%)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="card"><div class="card-body text-center text-body-secondary">{{ __('No rubrics yet.') }}</div></div>
            @endforelse
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('New rubric') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="subjectId">
                                        <option value="">{{ __('Generic') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject') }}</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="totalMark">
                                    <label>{{ __('Total mark') }}</label>
                                </div>
                            </div>
                        </div>

                        <h6>{{ __('Criteria') }} — <span class="{{ abs($this->weightTotal() - 100) > 0.01 ? 'text-danger' : 'text-success' }}">{{ __('Total weight: :total%', ['total' => $this->weightTotal()]) }}</span></h6>
                        @foreach ($criteria as $index => $criterion)
                            <div class="row g-2 mb-2" wire:key="criterion-{{ $index }}">
                                <div class="col-md-5">
                                    <input type="text" class="form-control form-control-sm" wire:model="criteria.{{ $index }}.criterion" placeholder="{{ __('Criterion') }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="criteria.{{ $index }}.maxMark" placeholder="{{ __('Max mark') }}">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="criteria.{{ $index }}.weightPercent" placeholder="{{ __('Weight %') }}">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeCriterion({{ $index }})">&times;</button>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" wire:click="addCriterion">{{ __('+ Add criterion') }}</button>

                        <div>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create rubric') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
