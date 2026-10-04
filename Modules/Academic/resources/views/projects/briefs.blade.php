<div>
    <h4 class="mb-1">{{ __('Project briefs') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Briefs') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Level') }}</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($briefs as $brief)
                                <tr wire:key="brief-{{ $brief->id }}">
                                    <td>{{ $brief->title }}</td>
                                    <td>{{ $brief->subject?->name }}</td>
                                    <td>{{ $brief->gradeLevel?->name }}</td>
                                    <td>{{ $brief->due_on->toFormattedDateString() }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($brief->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No briefs yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('New brief') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="instrumentId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($instruments as $instrument)
                                            <option value="{{ $instrument->id }}">{{ $instrument->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Instrument') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="subjectId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject (requires SBP)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="gradeLevelId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($gradeLevels as $level)
                                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Grade level') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="rubricId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($rubrics as $rubric)
                                            <option value="{{ $rubric->id }}">{{ $rubric->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Rubric') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder=" ">
                                    <label>{{ __('Title') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <textarea class="form-control" wire:model="description" style="height: 80px"></textarea>
                                    <label>{{ __('Description') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="heritageLink" placeholder=" ">
                                    <label>{{ __('Heritage link (optional, HBC)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control" wire:model="startsOn">
                                    <label>{{ __('Starts on') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control" wire:model="dueOn">
                                    <label>{{ __('Due on') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="maxMark">
                                    <label>{{ __('Max mark') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Deliverables') }}</label>
                                @foreach ($deliverables as $index => $deliverable)
                                    <input type="text" class="form-control form-control-sm mb-1" wire:model="deliverables.{{ $index }}" placeholder="{{ __('Deliverable') }}">
                                @endforeach
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addDeliverable">{{ __('+ Add deliverable') }}</button>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Milestones (optional)') }}</label>
                                @foreach ($milestones as $index => $milestone)
                                    <div class="row g-2 mb-1" wire:key="milestone-{{ $index }}">
                                        <div class="col-5"><input type="text" class="form-control form-control-sm" wire:model="milestones.{{ $index }}.title" placeholder="{{ __('Title') }}"></div>
                                        <div class="col-4"><input type="date" class="form-control form-control-sm" wire:model="milestones.{{ $index }}.dueOn"></div>
                                        <div class="col-2"><input type="number" class="form-control form-control-sm" wire:model="milestones.{{ $index }}.weightPercent" placeholder="%"></div>
                                        <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeMilestone({{ $index }})">&times;</button></div>
                                    </div>
                                @endforeach
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addMilestone">{{ __('+ Add milestone') }}</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create brief') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
