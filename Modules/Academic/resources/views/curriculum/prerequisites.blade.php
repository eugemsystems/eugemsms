<div>
    <h4 class="mb-1">{{ __('Subject prerequisites') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Checked against internal enrolment history only — a missing prerequisite warns by default.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Prerequisites') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Requires') }}</th><th>{{ __('Severity') }}</th></tr></thead>
                        <tbody>
                            @forelse ($prerequisites as $prerequisite)
                                <tr wire:key="prerequisite-{{ $prerequisite->id }}">
                                    <td>{{ $prerequisite->subject?->name }}</td>
                                    <td>{{ $prerequisite->prerequisiteSubject?->name }}</td>
                                    <td><span class="badge text-bg-{{ $prerequisite->severity === 'block' ? 'danger' : 'warning' }}">{{ ucfirst($prerequisite->severity) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-4">{{ __('No prerequisites yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New prerequisite') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-12">
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
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('prerequisiteSubjectId') is-invalid @enderror" wire:model="prerequisiteSubjectId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Must have studied') }}</label>
                                    @error('prerequisiteSubjectId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="severity">
                                        <option value="warn">{{ __('Warn') }}</option>
                                        <option value="block">{{ __('Block') }}</option>
                                    </select>
                                    <label>{{ __('Severity') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="minimumGrade" placeholder=" ">
                                    <label>{{ __('Minimum grade (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="examination" placeholder=" ">
                                    <label>{{ __('Examination (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create prerequisite') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
