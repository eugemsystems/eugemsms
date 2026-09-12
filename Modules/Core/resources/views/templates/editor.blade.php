<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('templates.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ $editingTemplateId !== null ? __('New template version') : __('New document template') }}</h4>
            <p class="text-body-secondary mb-0">
                @if ($editingTemplateId !== null)
                    {{ __('Saving creates a new version — the current one is kept, never overwritten.') }}
                @else
                    {{ __('Templates run in a sandbox: only registered variables and filters are allowed.') }}
                @endif
            </p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form wire:submit="save">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('templateType') is-invalid @enderror" id="templateType" wire:model.live="templateType" placeholder=" " @disabled($editingTemplateId !== null)>
                                    <label for="templateType">{{ __('Template type') }}</label>
                                    @error('templateType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model="name" placeholder=" ">
                                    <label for="name">{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('pageSize') is-invalid @enderror" id="pageSize" wire:model="pageSize" @disabled($editingTemplateId !== null)>
                                        <option value="A4">A4</option>
                                        <option value="A5">A5</option>
                                        <option value="Letter">{{ __('Letter') }}</option>
                                    </select>
                                    <label for="pageSize">{{ __('Page size') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('orientation') is-invalid @enderror" id="orientation" wire:model="orientation" @disabled($editingTemplateId !== null)>
                                        <option value="portrait">{{ __('Portrait') }}</option>
                                        <option value="landscape">{{ __('Landscape') }}</option>
                                    </select>
                                    <label for="orientation">{{ __('Orientation') }}</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="content">{{ __('Content') }}</label>
                                <textarea class="form-control font-monospace @error('content') is-invalid @enderror" id="content" wire:model="content" rows="10" placeholder="{{ __('Static HTML, or a registered variable in the palette on the right.') }}"></textarea>
                                @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="headerContent">{{ __('Header (optional)') }}</label>
                                <textarea class="form-control font-monospace @error('headerContent') is-invalid @enderror" id="headerContent" wire:model="headerContent" rows="3"></textarea>
                                @error('headerContent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="footerContent">{{ __('Footer (optional)') }}</label>
                                <textarea class="form-control font-monospace @error('footerContent') is-invalid @enderror" id="footerContent" wire:model="footerContent" rows="3"></textarea>
                                @error('footerContent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="styles">{{ __('Styles (optional CSS)') }}</label>
                                <textarea class="form-control font-monospace @error('styles') is-invalid @enderror" id="styles" wire:model="styles" rows="3"></textarea>
                                @error('styles') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if ($editingTemplateId === null)
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="isDefault" wire:model="isDefault">
                                        <label class="form-check-label" for="isDefault">{{ __('Default template for this type') }}</label>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                {{ $editingTemplateId !== null ? __('Save as new version') : __('Create template') }}
                            </button>
                            <a href="{{ route('templates.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">{{ __('Available variables') }}</h6>
                </div>
                <div class="card-body">
                    @if ($templateType === '')
                        <p class="text-body-secondary mb-0 small">{{ __('Enter a template type to see its registered variables.') }}</p>
                    @elseif (empty($availableVariables))
                        <p class="text-body-secondary mb-0 small">{{ __('No variables are registered for this template type yet — only static content will validate.') }}</p>
                    @else
                        <ul class="list-unstyled mb-0 small font-monospace">
                            @foreach ($availableVariables as $variable)
                                @php $wrapped = '{{ '.$variable.' }}'; @endphp
                                <li class="mb-1">{{ $wrapped }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
