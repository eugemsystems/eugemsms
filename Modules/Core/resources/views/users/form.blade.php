<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ $editingUserId !== null ? route('users.show', $editingUserUlid) : route('users.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ $editingUserId !== null ? __('Edit user') : __('New user') }}</h4>
            <p class="text-body-secondary mb-0">
                {{ __('A user must have at least one of email, phone, or username.') }}
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('firstName') is-invalid @enderror" id="firstName" wire:model="firstName" placeholder=" ">
                            <label for="firstName">{{ __('First name') }}</label>
                            @error('firstName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('lastName') is-invalid @enderror" id="lastName" wire:model="lastName" placeholder=" ">
                            <label for="lastName">{{ __('Last name') }}</label>
                            @error('lastName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('otherNames') is-invalid @enderror" id="otherNames" wire:model="otherNames" placeholder=" ">
                            <label for="otherNames">{{ __('Other names') }}</label>
                            @error('otherNames') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" wire:model="phone" placeholder=" ">
                            <label for="phone">{{ __('Phone') }}</label>
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" wire:model="username" placeholder=" ">
                            <label for="username">{{ __('Username') }}</label>
                            @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('userType') is-invalid @enderror" id="userType" wire:model="userType">
                                @foreach (\Modules\Core\Domain\Support\Auth\UserType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                                @endforeach
                            </select>
                            <label for="userType">{{ __('User type') }}</label>
                            @error('userType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('locale') is-invalid @enderror" id="locale" wire:model="locale" placeholder=" ">
                            <label for="locale">{{ __('Locale') }}</label>
                            @error('locale') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    @if ($editingUserId === null)
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" wire:model="password" placeholder=" ">
                                <label for="password">{{ __('Password (optional)') }}</label>
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text">{{ __('Leave blank for a phone-OTP-only or password-less account.') }}</div>
                        </div>
                    @endif
                </div>

                @if ($editingUserId === null)
                    <hr class="my-4">
                    <h6 class="mb-1">{{ __('Role (optional)') }}</h6>
                    <p class="text-body-secondary small mb-3">{{ __('Grant a role now so this user can do something as soon as they log in — or skip this and assign one later from their profile.') }}</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('roleSchoolId') is-invalid @enderror" id="roleSchoolId" wire:model="roleSchoolId">
                                    <option value="">{{ __('Select a school') }}</option>
                                    @foreach ($availableSchools as $availableSchool)
                                        <option value="{{ $availableSchool->id }}">{{ $availableSchool->name }}</option>
                                    @endforeach
                                </select>
                                <label for="roleSchoolId">{{ __('School') }}</label>
                                @error('roleSchoolId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('roleId') is-invalid @enderror" id="roleId" wire:model="roleId">
                                    <option value="">{{ __('Select a role') }}</option>
                                    @foreach ($availableRoles as $availableRole)
                                        <option value="{{ $availableRole->id }}">{{ $availableRole->display_name }}</option>
                                    @endforeach
                                </select>
                                <label for="roleId">{{ __('Role') }}</label>
                                @error('roleId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        {{ $editingUserId !== null ? __('Save changes') : __('Create user') }}
                    </button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
