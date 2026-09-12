<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('notifications.templates', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('New notification template') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every variable used must be declared for the chosen key.') }}</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form wire:submit="save">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('key') is-invalid @enderror" id="key" wire:model.live="key">
                                        <option value="">{{ __('Select a key') }}</option>
                                        @foreach ($availableKeys as $availableKey)
                                            <option value="{{ $availableKey }}">{{ $availableKey }}</option>
                                        @endforeach
                                    </select>
                                    <label for="key">{{ __('Notification key') }}</label>
                                    @error('key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                @if (empty($availableKeys))
                                    <div class="form-text text-warning">{{ __('No notification keys are registered yet.') }}</div>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('channel') is-invalid @enderror" id="channel" wire:model="channel">
                                        <option value="sms">SMS</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="email">{{ __('Email') }}</option>
                                        <option value="push">{{ __('Push') }}</option>
                                        <option value="in_app">{{ __('In-app') }}</option>
                                    </select>
                                    <label for="channel">{{ __('Channel') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('locale') is-invalid @enderror" id="locale" wire:model="locale" placeholder=" ">
                                    <label for="locale">{{ __('Locale') }}</label>
                                    @error('locale') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            @if (in_array($channel, ['email', 'push']))
                                <div class="col-12">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" wire:model="subject" placeholder=" ">
                                        <label for="subject">{{ __('Subject / title') }}</label>
                                        @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            @endif

                            <div class="col-12">
                                <label class="form-label" for="body">{{ __('Body') }}</label>
                                <textarea class="form-control font-monospace @error('body') is-invalid @enderror" id="body" wire:model.live="body" rows="6"></textarea>
                                @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if ($channel === 'sms')
                                    <div class="form-text">
                                        {{ __(':chars characters — :segments SMS segment(s)', ['chars' => mb_strlen($body), 'segments' => $this->smsSegmentCount()]) }}
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('providerTemplateId') is-invalid @enderror" id="providerTemplateId" wire:model="providerTemplateId" placeholder=" ">
                                    <label for="providerTemplateId">{{ __('Provider template ID (optional, e.g. WhatsApp)') }}</label>
                                    @error('providerTemplateId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create template') }}</button>
                            <a href="{{ route('notifications.templates', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Available variables') }}</h6></div>
                <div class="card-body">
                    @if ($key === '')
                        <p class="text-body-secondary mb-0 small">{{ __('Select a key to see its declared variables.') }}</p>
                    @elseif (empty($availableVariables))
                        <p class="text-body-secondary mb-0 small">{{ __('This key declares no variables.') }}</p>
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
