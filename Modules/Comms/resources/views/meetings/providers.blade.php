<div>
    <h4 class="mb-1">{{ __('Meeting providers') }} ⚠⚠</h4>
    <p class="text-body-secondary small">{{ __('Connect the school’s video-conferencing account. Credentials are encrypted and never shown again after saving. Saving a provider that already exists replaces its credentials.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Provider') }}</th><th>{{ __('Account') }}</th><th>{{ __('Status') }}</th><th>{{ __('Updated') }}</th></tr></thead>
                        <tbody>
                            @forelse ($providers as $row)
                                <tr wire:key="prov-{{ $row->id }}">
                                    <td>{{ str_replace('_', ' ', $row->provider) }}</td>
                                    <td>{{ $row->account_email ?? '—' }}</td>
                                    <td><span class="badge {{ $row->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $row->is_active ? __('active') : __('inactive') }}</span></td>
                                    <td class="small">{{ $row->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No provider connected yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Connect a provider') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="provider">
                        <option value="zoom">{{ __('Zoom') }}</option>
                        <option value="google_meet">{{ __('Google Meet') }}</option>
                        <option value="teams">{{ __('Microsoft Teams') }}</option>
                    </select>
                    <textarea class="form-control mb-2" rows="4" wire:model="credentials" placeholder='{"account_id": "…", "client_id": "…", "client_secret": "…"}' autocomplete="off"></textarea>
                    @error('credentials') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="email" class="form-control mb-2" wire:model="accountEmail" placeholder="{{ __('Account email (optional)') }}">
                    @error('accountEmail') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="register">{{ __('Save provider') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
