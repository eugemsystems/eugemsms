<div>
    <h4 class="mb-4">{{ __('New staff member') }}</h4>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="title" placeholder=" ">
                            <label>{{ __('Title') }}</label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('firstName') is-invalid @enderror" wire:model="firstName" placeholder=" ">
                            <label>{{ __('First name') }}</label>
                            @error('firstName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('lastName') is-invalid @enderror" wire:model="lastName" placeholder=" ">
                            <label>{{ __('Last name') }}</label>
                            @error('lastName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('dateOfBirth') is-invalid @enderror" wire:model="dateOfBirth">
                            <label>{{ __('Date of birth') }}</label>
                            @error('dateOfBirth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model="gender">
                                <option value="male">{{ __('Male') }}</option>
                                <option value="female">{{ __('Female') }}</option>
                            </select>
                            <label>{{ __('Gender') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('primaryPhone') is-invalid @enderror" wire:model="primaryPhone" placeholder=" ">
                            <label>{{ __('Primary phone') }}</label>
                            @error('primaryPhone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model="staffCategory">
                                <option value="teaching">{{ __('Teaching') }}</option>
                                <option value="administration">{{ __('Administration') }}</option>
                                <option value="boarding">{{ __('Boarding') }}</option>
                                <option value="catering">{{ __('Catering') }}</option>
                                <option value="maintenance">{{ __('Maintenance') }}</option>
                                <option value="transport">{{ __('Transport') }}</option>
                                <option value="security">{{ __('Security') }}</option>
                                <option value="health">{{ __('Health') }}</option>
                                <option value="farm">{{ __('Farm') }}</option>
                                <option value="ancillary">{{ __('Ancillary') }}</option>
                            </select>
                            <label>{{ __('Category') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('joinedOn') is-invalid @enderror" wire:model="joinedOn">
                            <label>{{ __('Joined on') }}</label>
                            @error('joinedOn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model="departmentId">
                                <option value="">{{ __('No department') }}</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <label>{{ __('Department') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model="postId">
                                <option value="">{{ __('No post') }}</option>
                                @foreach ($posts as $post)
                                    <option value="{{ $post->id }}">{{ $post->title }} ({{ $post->filled_count }}/{{ $post->approved_count }})</option>
                                @endforeach
                            </select>
                            <label>{{ __('Establishment post') }}</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="isTeaching" wire:model.live="isTeaching">
                            <label class="form-check-label" for="isTeaching">{{ __('Teaching staff') }}</label>
                        </div>
                    </div>

                    @if ($isTeaching)
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" wire:model="teacherRegistrationNo" placeholder=" ">
                                <label>{{ __('Teacher registration number (optional)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="number" class="form-control" wire:model="maxWeeklyPeriods" placeholder=" ">
                                <label>{{ __('Max weekly periods (optional — falls back to the school default)') }}</label>
                            </div>
                        </div>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary mt-4" wire:loading.attr="disabled">{{ __('Create staff member') }}</button>
            </form>
        </div>
    </div>
</div>
