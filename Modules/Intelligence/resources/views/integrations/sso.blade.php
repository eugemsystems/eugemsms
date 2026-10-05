<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('SSO configuration') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Domain and credentials for staff sign-in provisioning. Credentials are write-only. Provisioned staff still get their roles through the normal permission screens.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Configure a provider') }}</div>
                <div class="card-body">
                    <select class="form-select form-select-sm mb-2" wire:model.live="provider">
                        @foreach ($providers as $value) <option value="{{ $value }}">{{ ['google_workspace' => 'Google Workspace', 'microsoft_365' => 'Microsoft 365'][$value] }}</option> @endforeach
                    </select>
                    <input type="text" class="form-control form-control-sm mb-2" wire:model="domain" placeholder="school.ac.zw">
                    @error('domain') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="credentials" placeholder="{{ __('Credentials (leave blank to keep the stored ones)') }}" autocomplete="off"></textarea>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="sso-auto" wire:model="autoProvision"><label class="form-check-label small" for="sso-auto">{{ __('Auto-provision staff accounts') }}</label></div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="save">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Configured providers') }}</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($configs as $config)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ ['google_workspace' => 'Google Workspace', 'microsoft_365' => 'Microsoft 365'][$config->provider] ?? $config->provider }} · {{ $config->domain }}</span><span>{{ $config->auto_provision_staff ? __('Auto-provision on') : __('Auto-provision off') }} · {{ __(ucfirst($config->sync_status)) }}</span></li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('Nothing configured.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
