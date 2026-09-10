<section class="mt-5 pt-4 border-top">
    <h5 class="mb-1">{{ __('Delete account') }}</h5>
    <p class="text-body-secondary">{{ __('Delete your account and all of its resources') }}</p>

    <button type="button" class="btn btn-danger" wire:click="$set('confirmingDeletion', true)">
        {{ __('Delete account') }}
    </button>

    @if ($confirmingDeletion)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="POST" wire:submit="deleteUser">
                        <div class="modal-body p-4">
                            <h5 class="mb-2">{{ __('Are you sure you want to delete your account?') }}</h5>
                            <p class="text-body-secondary">
                                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                            </p>

                            <div class="input-group input-group-merge">
                                <div class="form-floating">
                                    <input wire:model="password" type="password" class="form-control @error('password') is-invalid @enderror" id="delete-password" placeholder="{{ __('Password') }}">
                                    <label for="delete-password">{{ __('Password') }}</label>
                                </div>
                                <span class="input-group-text cursor-pointer" data-password-toggle="#delete-password"><i class="ri ri-eye-off-line"></i></span>
                            </div>
                            @error('password')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('confirmingDeletion', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-danger">{{ __('Delete account') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</section>
