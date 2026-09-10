<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Environment') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Basic details about this installation.') }}</p>
    </div>

    <form wire:submit="continue">
        <div class="form-floating form-floating-outline mb-4">
            <input type="text" class="form-control @error('appName') is-invalid @enderror" id="appName" wire:model="appName" placeholder=" ">
            <label for="appName">{{ __('School / platform name') }}</label>
            @error('appName')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-floating form-floating-outline mb-4">
            <input type="text" class="form-control @error('appUrl') is-invalid @enderror" id="appUrl" wire:model="appUrl" placeholder=" ">
            <label for="appUrl">{{ __('URL') }}</label>
            @error('appUrl')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row">
            <div class="col-sm-6 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control @error('timezone') is-invalid @enderror" id="timezone" wire:model="timezone" placeholder=" ">
                    <label for="timezone">{{ __('Timezone') }}</label>
                    @error('timezone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-sm-6 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control @error('locale') is-invalid @enderror" id="locale" wire:model="locale" placeholder=" ">
                    <label for="locale">{{ __('Locale') }}</label>
                    @error('locale')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="form-floating form-floating-outline mb-4">
            <select class="form-select @error('deploymentMode') is-invalid @enderror" id="deploymentMode" wire:model="deploymentMode">
                <option value="saas">{{ __('SaaS (shared, vendor-hosted)') }}</option>
                <option value="dedicated">{{ __('Dedicated cloud') }}</option>
                <option value="on_premise">{{ __('On-premise') }}</option>
            </select>
            <label for="deploymentMode">{{ __('Deployment mode') }}</label>
            @error('deploymentMode')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary d-grid w-100">{{ __('Continue') }}</button>
    </form>
</div>
