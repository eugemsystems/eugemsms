<div>
    <h4 class="mb-1">{{ __('Timetable exceptions') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Exceptions') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Suppresses attendance') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @forelse ($exceptions as $exception)
                                <tr wire:key="exception-{{ $exception->id }}">
                                    <td>{{ $exception->exception_date->toFormattedDateString() }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $exception->exception_type)) }}</td>
                                    <td>{{ ucfirst($exception->affected_scope) }}</td>
                                    <td>{{ $exception->suppresses_attendance ? __('Yes') : __('No') }}</td>
                                    <td>{{ $exception->reason }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No exceptions recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New exception') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control @error('exceptionDate') is-invalid @enderror" wire:model="exceptionDate">
                                    <label>{{ __('Date') }}</label>
                                    @error('exceptionDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="exceptionType">
                                        <option value="no_lessons">{{ __('No lessons') }}</option>
                                        <option value="special_timetable">{{ __('Special timetable') }}</option>
                                        <option value="exam_timetable">{{ __('Exam timetable') }}</option>
                                        <option value="half_day">{{ __('Half day') }}</option>
                                        <option value="assembly_extended">{{ __('Assembly extended') }}</option>
                                        <option value="sports_day">{{ __('Sports day') }}</option>
                                    </select>
                                    <label>{{ __('Type') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="affectedScope">
                                        <option value="whole_school">{{ __('Whole school') }}</option>
                                        <option value="section">{{ __('Section') }}</option>
                                        <option value="level">{{ __('Level') }}</option>
                                        <option value="class">{{ __('Class') }}</option>
                                    </select>
                                    <label>{{ __('Scope') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="scopeId">
                                    <label>{{ __('Scope ID (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('reason') is-invalid @enderror" wire:model="reason" placeholder=" ">
                                    <label>{{ __('Reason') }}</label>
                                    @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12 form-check">
                                <input type="checkbox" class="form-check-input" wire:model="suppressesAttendance" id="suppressesAttendance">
                                <label class="form-check-label" for="suppressesAttendance">{{ __('Suppresses attendance session generation') }}</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Record exception') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
