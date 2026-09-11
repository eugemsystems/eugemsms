<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1"><code>{{ $definition->key }}</code></h4>
            <p class="text-body-secondary mb-0">{{ $definition->label }}</p>
        </div>
        <a href="{{ route('settings.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to settings') }}
        </a>
    </div>

    @if ($definition->description)
        <p class="text-body-secondary">{{ $definition->description }}</p>
    @endif

    <div class="card mb-4" style="max-width: 40rem;">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5 text-body-secondary fw-normal">{{ __('Effective value') }}</dt>
                <dd class="col-7">
                    @if ($definition->is_encrypted)
                        <span class="fst-italic">{{ __('(encrypted — masked)') }}</span>
                    @elseif (is_bool($effectiveValue))
                        {{ $effectiveValue ? __('Yes') : __('No') }}
                    @elseif (is_array($effectiveValue))
                        <pre class="mb-0 small">{{ json_encode($effectiveValue, JSON_PRETTY_PRINT) }}</pre>
                    @else
                        {{ $effectiveValue === null || $effectiveValue === '' ? '—' : $effectiveValue }}
                    @endif
                </dd>
                <dt class="col-5 text-body-secondary fw-normal">{{ __('Override at this school') }}</dt>
                <dd class="col-7 mb-0">
                    @if ($hasOverride)
                        <span class="badge text-bg-info">{{ __('Set here') }}</span>
                    @else
                        <span class="badge text-bg-light">{{ __('Inherited') }}</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>

    @unless ($schoolScopeAllowed)
        <div class="alert alert-warning" style="max-width: 40rem;">
            {{ __('This setting can only be overridden at a narrower scope than school (e.g. per-term or per-user) — that isn\'t supported by this screen yet.') }}
        </div>
    @else
        <div class="card" style="max-width: 40rem;">
            <div class="card-body">
                <form wire:submit="save">
                    @if ($definition->data_type === 'bool')
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="setting-value" wire:model="boolValue">
                            <label class="form-check-label" for="setting-value">{{ __('Enabled for this school') }}</label>
                        </div>
                    @elseif ($definition->ui_control === 'select' && $definition->options)
                        <div class="form-floating form-floating-outline mb-4">
                            <select class="form-select @error('value') is-invalid @enderror" id="setting-value" wire:model="value">
                                <option value="">{{ __('— use inherited default —') }}</option>
                                @foreach ($definition->options as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            <label for="setting-value">{{ __('Value') }}</label>
                            @error('value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @elseif ($definition->ui_control === 'textarea')
                        <div class="form-floating form-floating-outline mb-4">
                            <textarea class="form-control @error('value') is-invalid @enderror" id="setting-value" wire:model="value" style="height: 8rem;" placeholder=" "></textarea>
                            <label for="setting-value">{{ __('Value') }}</label>
                            @error('value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @else
                        <div class="form-floating form-floating-outline mb-4">
                            <input
                                type="{{ in_array($definition->ui_control, ['number', 'date', 'time', 'colour'], true) ? ($definition->ui_control === 'colour' ? 'color' : $definition->ui_control) : 'text' }}"
                                class="form-control @error('value') is-invalid @enderror"
                                id="setting-value"
                                wire:model="value"
                                placeholder=" "
                            >
                            <label for="setting-value">{{ __('Value') }}</label>
                            @error('value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" wire:click="resetToInherited" @disabled(! $hasOverride)>
                            {{ __('Reset to inherited') }}
                        </button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            {{ __('Save for this school') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endunless
</div>
