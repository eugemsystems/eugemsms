<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Teaching groups') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Sets for a subject, independent of form class.') }}</p>
        </div>
        <a href="{{ route('academic.groups.allocate', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Set allocation') }}</a>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Groups this term') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Level') }}</th><th>{{ __('Set') }}</th><th>{{ __('Count / Capacity') }}</th></tr></thead>
                        <tbody>
                            @forelse ($groups as $group)
                                <tr wire:key="group-{{ $group->id }}">
                                    <td>{{ $group->code }}</td>
                                    <td>{{ $group->subject?->name }}</td>
                                    <td>{{ $group->gradeLevel?->name }}</td>
                                    <td>{{ $group->set_level ? ucfirst($group->set_level) : '—' }}</td>
                                    <td>{{ $group->current_count }} / {{ $group->capacity ?? '∞' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No teaching groups yet this term.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New teaching group') }}</div>
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
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder=" ">
                                    <label>{{ __('Code') }}</label>
                                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="setLevel">
                                        <option value="">{{ __('None') }}</option>
                                        <option value="top">{{ __('Top') }}</option>
                                        <option value="middle">{{ __('Middle') }}</option>
                                        <option value="foundation">{{ __('Foundation') }}</option>
                                    </select>
                                    <label>{{ __('Set level') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="capacity" placeholder=" ">
                                    <label>{{ __('Capacity (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create group') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
