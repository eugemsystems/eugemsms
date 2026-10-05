<div>
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <h4 class="mb-1">{{ __('Messaging gateways') }} 🇿🇼 ⚠⚠</h4>
            <p class="text-body-secondary small mb-0">{{ __('Lowest priority number is tried first within a channel. A gateway marked down or inactive is skipped. Credentials are never shown after saving.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('comms.gateways.webhooks', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Webhook log') }}</a>
            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="checkHealth">{{ __('Run health check') }}</button>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Driver') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Health') }}</th><th>{{ __('Last check') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($gateways as $gateway)
                                <tr wire:key="gw-{{ $gateway->id }}">
                                    <td>
                                        {{ $gateway->name }}
                                        @if ($gateway->is_default) <span class="badge bg-label-primary">{{ __('default') }}</span> @endif
                                        @if ($gateway->is_sandbox) <span class="badge bg-label-warning">{{ __('sandbox') }}</span> @endif
                                    </td>
                                    <td>{{ $gateway->channel }}</td>
                                    <td>{{ $gateway->driver }}</td>
                                    <td>{{ $gateway->priority }}</td>
                                    <td>
                                        @if (! $gateway->is_active)
                                            <span class="badge bg-label-secondary">{{ __('inactive') }}</span>
                                        @else
                                            <span class="badge {{ $gateway->health_status === 'down' ? 'bg-label-danger' : 'bg-label-success' }}">{{ $gateway->health_status ?? 'unchecked' }}</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $gateway->last_health_check_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleActive({{ $gateway->id }})">{{ $gateway->is_active ? __('Deactivate') : __('Activate') }}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No gateways registered yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Register gateway') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="channel">
                        <option value="sms">{{ __('SMS') }}</option>
                        <option value="whatsapp">{{ __('WhatsApp') }}</option>
                        <option value="email">{{ __('Email') }}</option>
                        <option value="push">{{ __('Push') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="driver" placeholder="{{ __('Driver, e.g. bulksms_zw') }}">
                    @error('driver') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Display name') }}">
                    @error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <textarea class="form-control mb-2" rows="3" wire:model="credentials" placeholder='{"api_key": "…"}' autocomplete="off"></textarea>
                    @error('credentials') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="password" class="form-control mb-2" wire:model="webhookSecret" placeholder="{{ __('Webhook secret (optional)') }}" autocomplete="new-password">
                    <label class="form-label small mb-0">{{ __('Priority') }}</label>
                    <input type="number" class="form-control mb-2" wire:model="priority" min="0" max="99">
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="gw-default" wire:model="isDefault"><label class="form-check-label small" for="gw-default">{{ __('Default for this channel') }}</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="gw-sandbox" wire:model="isSandbox"><label class="form-check-label small" for="gw-sandbox">{{ __('Sandbox') }}</label></div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="register">{{ __('Register gateway') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
