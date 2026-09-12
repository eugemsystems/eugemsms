<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Import centre') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Bring existing data into :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('imports.history', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-history-line me-1"></i>{{ __('History') }}
        </a>
    </div>

    <div class="row g-3">
        @forelse ($definitions as $definition)
            @php $unmet = $this->unmetDependencies($definition); @endphp
            <div class="col-md-6 col-lg-4" wire:key="definition-{{ $definition->key }}">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h6 class="mb-1">{{ $definition->label }}</h6>
                        <p class="text-body-secondary small mb-2">{{ $definition->description ?? __('No description provided.') }}</p>
                        <span class="badge text-bg-secondary align-self-start mb-3">{{ $definition->module_code }}</span>

                        @if ($unmet !== [])
                            <p class="small text-warning mb-3">
                                <i class="ri ri-error-warning-line me-1"></i>
                                {{ __('Needs first: :deps', ['deps' => collect($unmet)->map(fn ($key) => $definitionLabels[$key] ?? \Illuminate\Support\Str::headline($key))->implode(', ')]) }}
                            </p>
                        @endif

                        <div class="mt-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="downloadTemplate('{{ $definition->key }}')">
                                <i class="ri ri-download-2-line me-1"></i>{{ __('Template') }}
                            </button>
                            @if ($this->canStart($definition))
                                <a href="{{ route('imports.mapper', [$school, $definition->key]) }}" class="btn btn-sm btn-primary" wire:navigate>
                                    <i class="ri ri-upload-2-line me-1"></i>{{ __('Start import') }}
                                </a>
                            @else
                                <button type="button" class="btn btn-sm btn-primary" disabled title="{{ __('Unmet dependency or missing permission') }}">
                                    <i class="ri ri-upload-2-line me-1"></i>{{ __('Start import') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-body-secondary py-5">
                        {{ __('No imports are registered yet — each entity module (learners, guardians, opening balances, ...) registers its own importer as it ships.') }}
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
