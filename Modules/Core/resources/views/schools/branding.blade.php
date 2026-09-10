<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'branding'])

    <form wire:submit="save" class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('Assets') }}</h5>

                    <div class="row g-4">
                        <div class="col-md-4 text-center">
                            <label class="form-label d-block">{{ __('Logo') }}</label>
                            <div class="border rounded p-3 mb-2 d-flex align-items-center justify-content-center" style="height:8rem;">
                                @if ($logo)
                                    <img src="{{ $logo->temporaryUrl() }}" class="mh-100 mw-100" alt="">
                                @elseif ($logoUrl)
                                    <img src="{{ $logoUrl }}" class="mh-100 mw-100" alt="">
                                @else
                                    <i class="ri ri-image-line fs-1 text-body-tertiary"></i>
                                @endif
                            </div>
                            <input type="file" class="form-control form-control-sm @error('logo') is-invalid @enderror" wire:model="logo">
                            @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4 text-center">
                            <label class="form-label d-block">{{ __('Crest') }}</label>
                            <div class="border rounded p-3 mb-2 d-flex align-items-center justify-content-center" style="height:8rem;">
                                @if ($crest)
                                    <img src="{{ $crest->temporaryUrl() }}" class="mh-100 mw-100" alt="">
                                @elseif ($crestUrl)
                                    <img src="{{ $crestUrl }}" class="mh-100 mw-100" alt="">
                                @else
                                    <i class="ri ri-shield-line fs-1 text-body-tertiary"></i>
                                @endif
                            </div>
                            <input type="file" class="form-control form-control-sm @error('crest') is-invalid @enderror" wire:model="crest">
                            @error('crest') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4 text-center">
                            <label class="form-label d-block">{{ __('Letterhead') }}</label>
                            <div class="border rounded p-3 mb-2 d-flex align-items-center justify-content-center" style="height:8rem;">
                                @if ($letterhead)
                                    <img src="{{ $letterhead->temporaryUrl() }}" class="mh-100 mw-100" alt="">
                                @elseif ($letterheadUrl)
                                    <img src="{{ $letterheadUrl }}" class="mh-100 mw-100" alt="">
                                @else
                                    <i class="ri ri-file-paper-2-line fs-1 text-body-tertiary"></i>
                                @endif
                            </div>
                            <input type="file" class="form-control form-control-sm @error('letterhead') is-invalid @enderror" wire:model="letterhead">
                            @error('letterhead') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">{{ __('Colours') }}</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="primaryColour">{{ __('Primary colour') }}</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" wire:model="primaryColour">
                                <input type="text" class="form-control @error('primaryColour') is-invalid @enderror" wire:model="primaryColour">
                            </div>
                            @error('primaryColour') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="secondaryColour">{{ __('Secondary colour') }}</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" wire:model="secondaryColour" value="{{ $secondaryColour ?? '#8592a3' }}">
                                <input type="text" class="form-control @error('secondaryColour') is-invalid @enderror" wire:model="secondaryColour" placeholder="#8592a3">
                            </div>
                            @error('secondaryColour') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save changes') }}</button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-body-secondary mb-3">{{ __('Live preview') }}</h6>
                    <div class="p-3 rounded" style="background-color: {{ $primaryColour }}; color: #fff;">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" style="height:2rem;" alt="">
                            @elseif ($logoUrl)
                                <img src="{{ $logoUrl }}" style="height:2rem;" alt="">
                            @endif
                            <strong>{{ $school->name }}</strong>
                        </div>
                        <div class="small" style="opacity:.85;">{{ $school->motto }}</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
