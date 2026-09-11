<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'settings'])

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <p class="text-body-secondary mb-0">{{ __('Every registered setting, grouped by module, with the value currently in effect for :school.', ['school' => $school->name]) }}</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('settings.history', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-history-line me-1"></i>{{ __('Change history') }}
            </a>
            <a href="{{ route('custom-fields.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-input-method-line me-1"></i>{{ __('Custom fields') }}
            </a>
            <a href="{{ route('settings.profiles', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-archive-line me-1"></i>{{ __('Profiles') }}
            </a>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <ul class="nav nav-tabs flex-nowrap overflow-auto pb-0">
            @foreach ($modules as $moduleCode)
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link text-nowrap {{ $activeModule === $moduleCode ? 'active' : '' }}"
                        wire:click="setActiveModule('{{ $moduleCode }}')"
                    >
                        {{ $moduleCode }}
                        <span class="badge text-bg-light ms-1">{{ $moduleCounts[$moduleCode] ?? 0 }}</span>
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="input-group input-group-merge input-group-sm flex-shrink-0" style="width: 16rem;">
            <span class="input-group-text"><i class="ri ri-search-line"></i></span>
            <input
                type="search"
                class="form-control"
                placeholder="{{ __('Search this module…') }}"
                wire:model.live.debounce.400ms="search"
                aria-label="{{ __('Search settings') }}"
            >
        </div>
    </div>

    @forelse ($groups as $groupKey => $groupDefinitions)
        @if ($groups->count() > 1)
            <h6 class="text-uppercase text-body-secondary small fw-semibold mt-4 mb-2">{{ \Illuminate\Support\Str::headline($groupKey) }}</h6>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                @foreach ($groupDefinitions as $definition)
                    @php $allowed = $this->schoolScopeAllowedFor($definition); @endphp
                    <div class="row py-3 {{ ! $loop->last ? 'border-bottom' : '' }}" wire:key="setting-row-{{ $definition->id }}">
                        <div class="col-md-5 mb-2 mb-md-0">
                            <label class="form-label fw-medium mb-0" for="setting-{{ $definition->id }}">{{ $definition->label }}</label>
                            @if ($definition->description)
                                <div class="text-body-secondary small">{{ $definition->description }}</div>
                            @endif
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <code class="small text-body-secondary">{{ $definition->key }}</code>
                                @if ($allowed && ! $definition->is_encrypted)
                                    @if ($this->hasOverrideFor($definition))
                                        <span class="badge text-bg-info">{{ __('Set here') }}</span>
                                    @else
                                        <span class="badge text-bg-light">{{ __('Inherited') }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="col-md-7">
                            @if (! $allowed)
                                <div class="text-body-secondary small fst-italic">
                                    {{ __('This setting can only be overridden at a narrower scope than school (e.g. per-term or per-user) — that isn\'t supported by this screen yet.') }}
                                </div>
                            @elseif ($definition->is_encrypted)
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fst-italic text-body-secondary">{{ __('(encrypted)') }}</span>
                                    <button
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-secondary"
                                        wire:click="resetToInherited({{ $definition->id }})"
                                        title="{{ __('Reset to inherited') }}"
                                        aria-label="{{ __('Reset to inherited') }}"
                                    >
                                        <i class="icon-base ri ri-arrow-go-back-line icon-22px"></i>
                                    </button>
                                </div>
                            @elseif ($definition->data_type === 'bool')
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check form-switch mb-0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            id="setting-{{ $definition->id }}"
                                            @checked((bool) ($resolved[$definition->id] ?? false))
                                            wire:click="saveBool({{ $definition->id }})"
                                        >
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-secondary"
                                        wire:click="resetToInherited({{ $definition->id }})"
                                        title="{{ __('Reset to inherited') }}"
                                        aria-label="{{ __('Reset to inherited') }}"
                                    >
                                        <i class="icon-base ri ri-arrow-go-back-line icon-22px"></i>
                                    </button>
                                </div>
                            @else
                                <div class="d-flex align-items-start gap-2">
                                    <div class="flex-grow-1" style="max-width: 24rem;">
                                        @if ($definition->ui_control === 'select' && $definition->options)
                                            <select class="form-select form-select-sm" id="setting-{{ $definition->id }}" wire:model="values.{{ $definition->id }}">
                                                <option value="">{{ __('— use inherited default —') }}</option>
                                                @foreach ($definition->options as $option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($definition->ui_control === 'textarea')
                                            <textarea class="form-control form-control-sm" id="setting-{{ $definition->id }}" wire:model="values.{{ $definition->id }}" rows="2"></textarea>
                                        @else
                                            <input
                                                type="{{ in_array($definition->ui_control, ['number', 'date', 'time', 'colour'], true) ? ($definition->ui_control === 'colour' ? 'color' : $definition->ui_control) : 'text' }}"
                                                class="form-control form-control-sm"
                                                id="setting-{{ $definition->id }}"
                                                wire:model="values.{{ $definition->id }}"
                                            >
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-primary"
                                        wire:click="saveField({{ $definition->id }})"
                                        title="{{ __('Save') }}"
                                        aria-label="{{ __('Save') }}"
                                    >
                                        <i class="icon-base ri ri-save-line icon-22px"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-secondary"
                                        wire:click="resetToInherited({{ $definition->id }})"
                                        title="{{ __('Reset to inherited') }}"
                                        aria-label="{{ __('Reset to inherited') }}"
                                    >
                                        <i class="icon-base ri ri-arrow-go-back-line icon-22px"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center text-body-secondary py-4">
                {{ __('No settings match your search in this module.') }}
            </div>
        </div>
    @endforelse
</div>
