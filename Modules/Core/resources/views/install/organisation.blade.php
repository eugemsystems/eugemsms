<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Tenant & School') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('The trust or group, and its first school.') }}</p>
    </div>

    <form wire:submit="continue">
        <div class="row">
            <div class="col-sm-8 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control @error('tenantName') is-invalid @enderror" id="tenantName" wire:model="tenantName" placeholder=" ">
                    <label for="tenantName">{{ __('Tenant / group name') }}</label>
                    @error('tenantName')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-sm-4 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control @error('tenantSlug') is-invalid @enderror" id="tenantSlug" wire:model="tenantSlug" placeholder=" ">
                    <label for="tenantSlug">{{ __('Slug') }}</label>
                    @error('tenantSlug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="row">
            <div class="col-sm-8 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control @error('schoolName') is-invalid @enderror" id="schoolName" wire:model="schoolName" placeholder=" ">
                    <label for="schoolName">{{ __('School name') }}</label>
                    @error('schoolName')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-sm-4 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control @error('schoolCode') is-invalid @enderror" id="schoolCode" wire:model="schoolCode" placeholder=" ">
                    <label for="schoolCode">{{ __('Code') }}</label>
                    @error('schoolCode')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-4 mb-4">
                <div class="form-floating form-floating-outline">
                    <select class="form-select @error('baseCurrency') is-invalid @enderror" id="baseCurrency" wire:model="baseCurrency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <label for="baseCurrency">{{ __('Base currency') }}</label>
                    @error('baseCurrency')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-sm-4 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control" id="orgTimezone" wire:model="timezone" placeholder=" ">
                    <label for="orgTimezone">{{ __('Timezone') }}</label>
                </div>
            </div>
            <div class="col-sm-4 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control" id="orgLocale" wire:model="locale" placeholder=" ">
                    <label for="orgLocale">{{ __('Locale') }}</label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary d-grid w-100">{{ __('Continue') }}</button>
    </form>
</div>
