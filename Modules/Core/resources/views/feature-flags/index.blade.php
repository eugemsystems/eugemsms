<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Feature flags') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Platform-wide — global default, plus per-user/school/tenant overrides.') }}</p>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Key') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Rollout %') }}</th>
                        <th>{{ __('Global default') }}</th>
                        <th>{{ __('Overrides') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($flags as $flag)
                        <tr wire:key="flag-{{ $flag->id }}">
                            <td><code>{{ $flag->key }}</code></td>
                            <td>
                                {{ $flag->name }}
                                @if ($flag->description)
                                    <div class="text-body-secondary small">{{ $flag->description }}</div>
                                @endif
                            </td>
                            <td>{{ $flag->rollout_percentage }}%</td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        @checked($flag->is_globally_enabled)
                                        wire:click="toggleGlobal('{{ $flag->key }}', {{ $flag->is_globally_enabled ? 'false' : 'true' }})"
                                    >
                                </div>
                            </td>
                            <td>
                                @forelse ($flag->overrides as $override)
                                    <div class="small" wire:key="override-{{ $override->id }}">
                                        <span class="badge {{ $override->is_enabled ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $override->scope_type }}:{{ $override->scope_id }}
                                        </span>
                                    </div>
                                @empty
                                    <span class="text-body-secondary">—</span>
                                @endforelse
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-primary" wire:click="openOverrideModal({{ $flag->id }})" title="{{ __('Add override') }}" aria-label="{{ __('Add override') }}">
                                        <i class="icon-base ri ri-add-line icon-22px"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-4">
                                {{ __('No feature flags registered yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showOverrideModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="saveOverride">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Add override') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showOverrideModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <select class="form-select" id="override-scope-type" wire:model="overrideScopeType">
                                    <option value="user">{{ __('User') }}</option>
                                    <option value="school">{{ __('School') }}</option>
                                    <option value="tenant">{{ __('Tenant') }}</option>
                                </select>
                                <label for="override-scope-type">{{ __('Scope') }}</label>
                            </div>
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="number" class="form-control @error('overrideScopeId') is-invalid @enderror" id="override-scope-id" wire:model="overrideScopeId" placeholder=" ">
                                <label for="override-scope-id">{{ __('Scope id') }}</label>
                                @error('overrideScopeId')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="override-enabled" wire:model="overrideEnabled">
                                <label class="form-check-label" for="override-enabled">{{ __('Enabled for this scope') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showOverrideModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save override') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
