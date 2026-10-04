<div>
    <h4 class="mb-1">{{ __('Subjects') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('The learning-area catalogue.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Subject catalogue') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Group') }}</th><th>{{ __('Type') }}</th><th>{{ __('SBP') }}</th></tr></thead>
                        <tbody>
                            @forelse ($subjects as $subject)
                                <tr wire:key="subject-{{ $subject->id }}">
                                    <td>{{ $subject->code }}</td>
                                    <td>{{ $subject->name }}</td>
                                    <td>{{ $subject->subjectGroup?->name ?? '—' }}</td>
                                    <td>{{ ucfirst($subject->subject_type) }}</td>
                                    <td>{{ $subject->requires_sbp ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No subjects yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New subject') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('frameworkId') is-invalid @enderror" wire:model="frameworkId">
                                        @foreach ($frameworks as $framework)
                                            <option value="{{ $framework->id }}">{{ $framework->code }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Framework') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="subjectGroupId">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($groups as $group)
                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject group') }}</label>
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
                                    <input type="text" class="form-control @error('shortName') is-invalid @enderror" wire:model="shortName" placeholder=" ">
                                    <label>{{ __('Short name') }}</label>
                                    @error('shortName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="subjectType">
                                        <option value="core">{{ __('Core') }}</option>
                                        <option value="elective">{{ __('Elective') }}</option>
                                        <option value="practical">{{ __('Practical') }}</option>
                                        <option value="vocational">{{ __('Vocational') }}</option>
                                        <option value="co_curricular">{{ __('Co-curricular') }}</option>
                                    </select>
                                    <label>{{ __('Subject type') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="zimsecSubjectCode" placeholder=" ">
                                    <label>{{ __('ZIMSEC code (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="isExaminable">
                                    <label class="form-check-label">{{ __('Examinable') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="requiresSbp">
                                    <label class="form-check-label">{{ __('Requires SBP') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create subject') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
