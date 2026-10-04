<div>
    <h4 class="mb-1">{{ __('Timetable constraints') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            @forelse ($constraintsByType as $type => $constraints)
                <div class="card mb-3">
                    <div class="card-header">{{ ucfirst(str_replace('_', ' ', $type)) }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Severity') }}</th><th>{{ __('Weight') }}</th><th>{{ __('Value') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                            <tbody>
                                @foreach ($constraints as $constraint)
                                    <tr wire:key="constraint-{{ $constraint->id }}">
                                        <td><span class="badge text-bg-{{ $constraint->severity === 'hard' ? 'danger' : 'warning' }}">{{ ucfirst($constraint->severity) }}</span></td>
                                        <td>{{ $constraint->weight }}</td>
                                        <td>{{ $constraint->value ?? '—' }}</td>
                                        <td>{{ $constraint->reason ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="card"><div class="card-body text-center text-body-secondary">{{ __('No constraints defined yet.') }}</div></div>
            @endforelse
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New constraint') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="constraintType">
                                        <option value="teacher_unavailable">{{ __('Teacher unavailable') }}</option>
                                        <option value="venue_unavailable">{{ __('Venue unavailable') }}</option>
                                        <option value="subject_requires_venue_type">{{ __('Subject requires venue type') }}</option>
                                        <option value="subject_max_per_day">{{ __('Subject max per day') }}</option>
                                        <option value="subject_not_after_period">{{ __('Subject not after period') }}</option>
                                        <option value="subject_prefers_morning">{{ __('Subject prefers morning') }}</option>
                                        <option value="subject_requires_double">{{ __('Subject requires double') }}</option>
                                        <option value="teacher_max_consecutive">{{ __('Teacher max consecutive') }}</option>
                                        <option value="teacher_max_per_day">{{ __('Teacher max per day') }}</option>
                                        <option value="teacher_no_first_period">{{ __('Teacher no first period') }}</option>
                                        <option value="class_no_free_periods">{{ __('Class no free periods') }}</option>
                                        <option value="games_afternoon">{{ __('Games afternoon') }}</option>
                                        <option value="subject_spread_across_week">{{ __('Subject spread across week') }}</option>
                                        <option value="consecutive_same_subject_forbidden">{{ __('Consecutive same subject forbidden') }}</option>
                                    </select>
                                    <label>{{ __('Constraint type') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="severity">
                                        <option value="hard">{{ __('Hard') }}</option>
                                        <option value="soft">{{ __('Soft') }}</option>
                                    </select>
                                    <label>{{ __('Severity') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="weight" min="1">
                                    <label>{{ __('Weight') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="value">
                                    <label>{{ __('Value (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="reason" placeholder=" ">
                                    <label>{{ __('Reason (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create constraint') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
