<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('sessions.years', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('New academic year') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('For :school.', ['school' => $school->name]) }}</p>
        </div>
    </div>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form wire:submit="create">
                <div class="form-floating form-floating-outline mb-3">
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="year-name" wire:model="name" placeholder=" ">
                    <label for="year-name">{{ __('Name (e.g. 2027)') }}</label>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('starts_on') is-invalid @enderror" id="year-starts-on" wire:model="startsOn" placeholder=" ">
                            <label for="year-starts-on">{{ __('Starts on') }}</label>
                            @error('starts_on')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('ends_on') is-invalid @enderror" id="year-ends-on" wire:model="endsOn" placeholder=" ">
                            <label for="year-ends-on">{{ __('Ends on') }}</label>
                            @error('ends_on')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="generate-three-terms" wire:model="generateThreeTerms">
                    <label class="form-check-label" for="generate-three-terms">
                        {{ __('Split evenly into three terms') }}
                    </label>
                    <div class="form-text">{{ __('You can also add terms one at a time afterwards.') }}</div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('sessions.years', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create year') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
