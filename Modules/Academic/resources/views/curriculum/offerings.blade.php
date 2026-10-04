<div>
    <h4 class="mb-1">{{ __('Level subject offerings') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Which subjects exist at which grade level, for the current academic year.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Offerings') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Level') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Pathway') }}</th><th>{{ __('Compulsory') }}</th><th>{{ __('Block') }}</th></tr></thead>
                        <tbody>
                            @forelse ($offerings as $offering)
                                <tr wire:key="offering-{{ $offering->id }}">
                                    <td>{{ $offering->gradeLevel?->name }}</td>
                                    <td>{{ $offering->subject?->name }}</td>
                                    <td>{{ $offering->pathway?->code ?? __('Both') }}</td>
                                    <td>{{ $offering->is_compulsory ? __('Yes') : __('No') }}</td>
                                    <td>{{ $offering->option_block ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No offerings for the current year yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New offering') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('gradeLevelId') is-invalid @enderror" wire:model="gradeLevelId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($gradeLevels as $gradeLevel)
                                            <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Grade level') }}</label>
                                </div>
                            </div>
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
                                    <select class="form-select" wire:model="pathwayId">
                                        <option value="">{{ __('Both pathways') }}</option>
                                        @foreach ($pathways as $pathway)
                                            <option value="{{ $pathway->id }}">{{ $pathway->code }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Pathway') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="optionBlock" placeholder=" ">
                                    <label>{{ __('Option block (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="periodsPerWeek" placeholder=" ">
                                    <label>{{ __('Periods per week (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="maxLearners" placeholder=" ">
                                    <label>{{ __('Max learners (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="isCompulsory">
                                    <label class="form-check-label">{{ __('Compulsory') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="isAvailable">
                                    <label class="form-check-label">{{ __('Available') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create offering') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
