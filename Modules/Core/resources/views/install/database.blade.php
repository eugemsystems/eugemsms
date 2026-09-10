<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Database') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Test the connection before continuing.') }}</p>
    </div>

    <form wire:submit="testConnection">
        <div class="form-floating form-floating-outline mb-4">
            <select class="form-select" id="driver" wire:model="driver">
                <option value="mysql">MySQL</option>
                <option value="pgsql">PostgreSQL</option>
                <option value="sqlite">SQLite</option>
            </select>
            <label for="driver">{{ __('Driver') }}</label>
        </div>

        <div class="row">
            <div class="col-sm-8 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control @error('host') is-invalid @enderror" id="host" wire:model="host" placeholder=" ">
                    <label for="host">{{ __('Host') }}</label>
                    @error('host')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-sm-4 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="number" class="form-control @error('port') is-invalid @enderror" id="port" wire:model="port" placeholder=" ">
                    <label for="port">{{ __('Port') }}</label>
                    @error('port')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="form-floating form-floating-outline mb-4">
            <input type="text" class="form-control @error('database') is-invalid @enderror" id="database" wire:model="database" placeholder=" ">
            <label for="database">{{ __('Database name') }}</label>
            @error('database')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row">
            <div class="col-sm-6 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" wire:model="username" placeholder=" ">
                    <label for="username">{{ __('Username') }}</label>
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-sm-6 mb-4">
                <div class="form-floating form-floating-outline">
                    <input type="password" class="form-control" id="password" wire:model="password" placeholder=" ">
                    <label for="password">{{ __('Password') }}</label>
                </div>
            </div>
        </div>

        @if ($tested)
            <div class="alert {{ $canProceed ? 'alert-success' : 'alert-danger' }} d-flex align-items-center gap-2">
                <i class="ri {{ $canProceed ? 'ri-checkbox-circle-line' : 'ri-error-warning-line' }}"></i>
                {{ $testMessage }}
            </div>
        @endif

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
                <i class="ri ri-plug-line"></i> {{ __('Test connection') }}
            </button>
            <button type="button" class="btn btn-primary flex-fill" wire:click="continue" @disabled(! $canProceed)>
                {{ __('Continue') }}
            </button>
        </div>
    </form>
</div>
