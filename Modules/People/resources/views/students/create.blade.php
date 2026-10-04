<div>
    <h4 class="mb-4">{{ __('New student') }}</h4>

    <div class="card">
        <div class="card-body">
            @if (! $hasCheckedDuplicates)
                <form wire:submit="check">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('firstName') is-invalid @enderror" wire:model="firstName" placeholder=" ">
                                <label>{{ __('First name') }}</label>
                                @error('firstName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" wire:model="nationalRegistrationNo" placeholder=" ">
                                <label>{{ __('National registration number (optional)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" wire:model="birthCertificateNo" placeholder=" ">
                                <label>{{ __('Birth certificate number (optional)') }}</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4">{{ __('Check for duplicates') }}</button>
                </form>
            @else
                @if ($duplicates !== [])
                    <div class="alert alert-warning">
                        <strong>{{ __('Possible duplicate learner(s) found:') }}</strong>
                        <ul class="mb-0">
                            @foreach ($duplicates as $candidate)
                                <li>{{ __('Admission #:number — matched on :on', ['number' => $candidate['admissionNumber'], 'on' => str_replace('_', ' ', $candidate['matchedOn'])]) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="alert alert-success">{{ __('No possible duplicates found.') }}</div>
                @endif

                <form wire:submit="save">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" wire:model="middleNames" placeholder=" ">
                                <label>{{ __('Middle names (optional)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" wire:model="preferredName" placeholder=" ">
                                <label>{{ __('Preferred name (optional)') }}</label>
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
                                <select class="form-select" wire:model="enrolmentType">
                                    <option value="FULL_TIME">{{ __('Full time') }}</option>
                                    <option value="PART_TIME">{{ __('Part time') }}</option>
                                </select>
                                <label>{{ __('Enrolment type') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" wire:model="residency">
                                    <option value="DAY">{{ __('Day') }}</option>
                                    <option value="BOARDER">{{ __('Boarder') }}</option>
                                    <option value="WEEKLY_BOARDER">{{ __('Weekly boarder') }}</option>
                                </select>
                                <label>{{ __('Residency') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" wire:model="nationality" placeholder=" ">
                                <label>{{ __('Nationality (ISO2)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('sectionId') is-invalid @enderror" wire:model="sectionId">
                                    <option value="">{{ __('Select a section') }}</option>
                                    @foreach ($sections as $section)
                                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Section') }}</label>
                                @error('sectionId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('gradeLevelId') is-invalid @enderror" wire:model="gradeLevelId">
                                    <option value="">{{ __('Select a grade level') }}</option>
                                    @foreach ($gradeLevels as $gradeLevel)
                                        <option value="{{ $gradeLevel->id }}">{{ $gradeLevel->name }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Grade level') }}</label>
                                @error('gradeLevelId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('hasCheckedDuplicates', false)">{{ __('Back') }}</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Enrol student') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
