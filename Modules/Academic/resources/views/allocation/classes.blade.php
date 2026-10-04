<div>
    <h4 class="mb-1">{{ __('Class allocation') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Allocating a student to a class auto-enrols their compulsory subjects, if enabled.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Recent allocations this term') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Type') }}</th><th>{{ __('From') }}</th></tr></thead>
                        <tbody>
                            @forelse ($allocations as $allocation)
                                <tr wire:key="allocation-{{ $allocation->id }}">
                                    <td>{{ $allocation->student?->first_name }} {{ $allocation->student?->last_name }}</td>
                                    <td>{{ $allocation->schoolClass?->name }}</td>
                                    <td>{{ ucfirst($allocation->allocation_type) }}</td>
                                    <td>{{ $allocation->effective_from->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No allocations yet this term.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Allocate a student') }}</div>
                <div class="card-body">
                    <form wire:submit="allocate">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('studentId') is-invalid @enderror" wire:model="studentId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($students as $student)
                                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Student') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('classId') is-invalid @enderror" wire:model="classId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Class') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="allocationType">
                                        <option value="initial">{{ __('Initial') }}</option>
                                        <option value="promoted">{{ __('Promoted') }}</option>
                                        <option value="transferred">{{ __('Transferred') }}</option>
                                        <option value="repeated">{{ __('Repeated') }}</option>
                                        <option value="manual">{{ __('Manual') }}</option>
                                    </select>
                                    <label>{{ __('Allocation type') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="notes" placeholder=" ">
                                    <label>{{ __('Notes (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Allocate') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
