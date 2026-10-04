<div>
    <h4 class="mb-1">{{ __('Generate timetable') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Runs synchronously — a working, deliberately simplified greedy + local-search pass, not the full queued/annealing engine the spec describes.') }}</p>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('New draft timetable') }}</div>
                <div class="card-body">
                    <form wire:submit="createDraft">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('structureId') is-invalid @enderror" wire:model="structureId">
                                        <option value="">{{ __('Select structure') }}</option>
                                        @foreach ($structures as $structure)
                                            <option value="{{ $structure->id }}">{{ $structure->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Period structure') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary mt-3" wire:loading.attr="disabled">{{ __('Create draft') }}</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Run generation') }}</div>
                <div class="card-body">
                    <div class="form-floating form-floating-outline mb-3">
                        <select class="form-select" wire:model="selectedTimetableId">
                            <option value="">{{ __('Select timetable') }}</option>
                            @foreach ($timetables as $timetable)
                                <option value="{{ $timetable->id }}">{{ $timetable->name }} ({{ $timetable->status }})</option>
                            @endforeach
                        </select>
                        <label>{{ __('Timetable') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary" wire:click="generate" wire:loading.attr="disabled">{{ __('Generate') }}</button>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            @if ($lastRun)
                <div class="card">
                    <div class="card-header">{{ __('Last run result') }}</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ __('Status') }}</dt><dd class="col-sm-8">{{ $lastRun->status }}</dd>
                            <dt class="col-sm-4">{{ __('Iterations') }}</dt><dd class="col-sm-8">{{ $lastRun->iterations }}</dd>
                            <dt class="col-sm-4">{{ __('Hard violations') }}</dt><dd class="col-sm-8">{{ $lastRun->hard_violations }}</dd>
                        </dl>

                        @if (is_array($lastRun->unplaced_requirements) && count($lastRun->unplaced_requirements) > 0)
                            <div class="alert alert-danger mt-3 mb-0">
                                <strong>{{ __('Unplaced requirements (BR-ACA-03-005):') }}</strong>
                                <ul class="mb-0 mt-1">
                                    @foreach ($lastRun->unplaced_requirements as $unplaced)
                                        <li>{{ $unplaced['label'] }} — {{ $unplaced['periods_placed'] }}/{{ $unplaced['periods_requested'] }} placed. {{ $unplaced['blocking_constraint'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <div class="alert alert-success mt-3 mb-0">{{ __('Every requirement was placed with zero hard violations.') }}</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
