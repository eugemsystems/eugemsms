<div>
    <h4 class="mb-4">{{ __('New guardian') }}</h4>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" wire:model.live="guardianType">
                                <option value="individual">{{ __('Individual') }}</option>
                                <option value="organisation">{{ __('Organisation') }}</option>
                            </select>
                            <label>{{ __('Guardian type') }}</label>
                        </div>
                    </div>

                    @if ($guardianType === 'individual')
                        <div class="col-md-2">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" wire:model="title" placeholder=" ">
                                <label>{{ __('Title (optional)') }}</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('firstName') is-invalid @enderror" wire:model="firstName" placeholder=" ">
                                <label>{{ __('First name') }}</label>
                                @error('firstName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('lastName') is-invalid @enderror" wire:model="lastName" placeholder=" ">
                                <label>{{ __('Last name') }}</label>
                                @error('lastName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @else
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('organisationName') is-invalid @enderror" wire:model="organisationName" placeholder=" ">
                                <label>{{ __('Organisation name') }}</label>
                                @error('organisationName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('organisationType') is-invalid @enderror" wire:model="organisationType">
                                    <option value="">{{ __('Select') }}</option>
                                    <option value="employer">{{ __('Employer') }}</option>
                                    <option value="sponsor">{{ __('Sponsor') }}</option>
                                    <option value="ngo">{{ __('NGO') }}</option>
                                    <option value="government">{{ __('Government') }}</option>
                                    <option value="other">{{ __('Other') }}</option>
                                </select>
                                <label>{{ __('Organisation type') }}</label>
                                @error('organisationType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="primaryPhone" placeholder=" ">
                            <label>{{ __('Primary phone (optional)') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email" placeholder=" ">
                            <label>{{ __('Email (optional)') }}</label>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <a href="{{ route('people.guardians.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create guardian') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
