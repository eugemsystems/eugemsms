<div>
    <h4 class="mb-4">{{ __('Edit :name', ['name' => $student->fullName()]) }}</h4>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
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
                            <input type="text" class="form-control" wire:model="middleNames" placeholder=" ">
                            <label>{{ __('Middle names') }}</label>
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
                            <input type="text" class="form-control" wire:model="preferredName" placeholder=" ">
                            <label>{{ __('Preferred name') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="homeLanguage" placeholder=" ">
                            <label>{{ __('Home language') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="religion" placeholder=" ">
                            <label>{{ __('Religion') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="addressLine1" placeholder=" ">
                            <label>{{ __('Address line 1') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="addressLine2" placeholder=" ">
                            <label>{{ __('Address line 2') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="suburb" placeholder=" ">
                            <label>{{ __('Suburb') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="city" placeholder=" ">
                            <label>{{ __('City') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control" wire:model="province" placeholder=" ">
                            <label>{{ __('Province') }}</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating form-floating-outline">
                            <textarea class="form-control" wire:model="notes" style="height: 100px" placeholder=" "></textarea>
                            <label>{{ __('Notes') }}</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <a href="{{ route('people.students.show', [$school, $student]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
