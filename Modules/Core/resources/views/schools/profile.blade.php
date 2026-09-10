<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'profile'])

    <form wire:submit="save" class="card">
        <div class="card-body">
            <h5 class="mb-3">{{ __('Identity') }}</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" value="{{ $school->code }}" disabled>
                        <label>{{ __('Code (immutable)') }}</label>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model="name" placeholder=" ">
                        <label for="name">{{ __('Name') }}</label>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="shortName" wire:model="shortName" placeholder=" ">
                        <label for="shortName">{{ __('Short name') }}</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control @error('centreNumber') is-invalid @enderror" id="centreNumber" wire:model="centreNumber" placeholder=" ">
                        <label for="centreNumber">{{ __('ZIMSEC centre number') }}</label>
                        @error('centreNumber') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="emisCode" wire:model="emisCode" placeholder=" ">
                        <label for="emisCode">{{ __('MoPSE EMIS code') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select @error('category') is-invalid @enderror" id="category" wire:model="category">
                            <option value="government">{{ __('Government') }}</option>
                            <option value="council">{{ __('Council') }}</option>
                            <option value="mission">{{ __('Mission') }}</option>
                            <option value="trust">{{ __('Trust') }}</option>
                            <option value="private">{{ __('Private') }}</option>
                        </select>
                        <label for="category">{{ __('Category') }}</label>
                        @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="responsibleAuthority" wire:model="responsibleAuthority" placeholder=" ">
                        <label for="responsibleAuthority">{{ __('Responsible authority') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="band" wire:model="band" placeholder=" ">
                        <label for="band">{{ __('MoPSE band') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" value="{{ $school->base_currency }}" disabled>
                        <label>{{ __('Base currency (immutable once transactions exist)') }}</label>
                    </div>
                </div>
            </div>

            <h5 class="mb-3">{{ __('Location') }}</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="province" wire:model="province" placeholder=" ">
                        <label for="province">{{ __('Province') }}</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="district" wire:model="district" placeholder=" ">
                        <label for="district">{{ __('District') }}</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="addressLine1" wire:model="addressLine1" placeholder=" ">
                        <label for="addressLine1">{{ __('Address line 1') }}</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="addressLine2" wire:model="addressLine2" placeholder=" ">
                        <label for="addressLine2">{{ __('Address line 2') }}</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="city" wire:model="city" placeholder=" ">
                        <label for="city">{{ __('City') }}</label>
                    </div>
                </div>
            </div>

            <h5 class="mb-3">{{ __('Contact') }}</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="phone" wire:model="phone" placeholder=" ">
                        <label for="phone">{{ __('Phone') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" wire:model="email" placeholder=" ">
                        <label for="email">{{ __('Email') }}</label>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control @error('website') is-invalid @enderror" id="website" wire:model="website" placeholder=" ">
                        <label for="website">{{ __('Website') }}</label>
                        @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="motto" wire:model="motto" placeholder=" ">
                        <label for="motto">{{ __('Motto') }}</label>
                    </div>
                </div>
            </div>

            <h5 class="mb-3">{{ __('Locale') }}</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="timezone" wire:model="timezone" placeholder=" ">
                        <label for="timezone">{{ __('Timezone') }}</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <input type="text" class="form-control" id="locale" wire:model="locale" placeholder=" ">
                        <label for="locale">{{ __('Locale') }}</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save changes') }}</button>
        </div>
    </form>
</div>
