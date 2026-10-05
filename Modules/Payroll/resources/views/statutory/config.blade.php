<div>
    <h4 class="mb-1">{{ __('Statutory configuration') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Every PAYE band, AIDS Levy rate, NSSA rate, ZIMDEF rate and NEC due lives here as versioned, effective-dated configuration — never hard-coded.') }}</p>

    @if ($unconfirmed->isNotEmpty())
        <div class="alert alert-danger">
            <strong>{{ __('Confirmation required before payroll can run:') }}</strong>
            <ul class="mb-0">
                @foreach ($unconfirmed as $u)
                    <li>{{ $u->config_type }} ({{ $u->currency ?? __('all currencies') }}) — {{ __('effective') }} {{ $u->effective_from->toDateString() }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Currency') }}</th><th>{{ __('Effective') }}</th><th>{{ __('Status') }}</th><th>{{ __('Confirmed') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($configs as $config)
                                <tr wire:key="config-{{ $config->id }}">
                                    <td>{{ $config->config_type }}</td>
                                    <td>{{ $config->currency ?? '—' }}</td>
                                    <td>{{ $config->effective_from->toDateString() }}</td>
                                    <td><span class="badge bg-secondary">{{ $config->status }}</span></td>
                                    <td>
                                        @if (! $config->requires_confirmation)
                                            <span class="text-body-secondary">{{ __('n/a') }}</span>
                                        @elseif ($config->confirmed_at)
                                            <span class="badge bg-success">{{ __('Confirmed') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('Unconfirmed') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($config->requires_confirmation && ! $config->confirmed_at)
                                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="confirm({{ $config->id }})">{{ __('Confirm') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No statutory configuration on file yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New configuration') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="configType">
                        <option value="paye_bands">{{ __('PAYE bands') }}</option>
                        <option value="aids_levy">{{ __('AIDS Levy') }}</option>
                        <option value="nssa_pension">{{ __('NSSA — POBS pension') }}</option>
                        <option value="nssa_apwcs">{{ __('NSSA — APWCS') }}</option>
                        <option value="zimdef">{{ __('ZIMDEF') }}</option>
                        <option value="nec_dues">{{ __('NEC dues') }}</option>
                        <option value="withholding">{{ __('Withholding (no ITF263)') }}</option>
                        <option value="credits">{{ __('Deductible credits') }}</option>
                    </select>
                    @if (in_array($configType, ['paye_bands', 'nssa_pension']))
                        <select class="form-select mb-2" wire:model="currency">
                            <option value="USD">USD</option>
                            <option value="ZWG">ZWG</option>
                        </select>
                    @endif
                    <input type="date" class="form-control mb-2" wire:model="effectiveFrom">
                    <textarea class="form-control mb-1" rows="4" wire:model="configurationJson" placeholder="{{ $shapeHints[$configType] ?? '{}' }}"></textarea>
                    <div class="form-text mb-2">{{ __('Expected shape:') }} <code>{{ $shapeHints[$configType] ?? '{}' }}</code></div>
                    @error('configurationJson') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="sourceReference" placeholder="{{ __('Source reference (e.g. Finance Act 2026)') }}">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" wire:model="requiresConfirmation" id="requiresConfirmation">
                        <label class="form-check-label" for="requiresConfirmation">{{ __('Requires confirmation before use') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Save configuration') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
