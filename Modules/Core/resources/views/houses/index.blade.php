<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'houses'])

    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary" wire:click="$set('showCreateModal', true)">
            <i class="ri ri-add-line me-1"></i>{{ __('New house') }}
        </button>
    </div>

    <div class="row g-3">
        @forelse ($houses as $house)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle flex-shrink-0" style="width:2.5rem;height:2.5rem;background-color:{{ $house->colour ?? '#8592a3' }};"></span>
                        <div>
                            <div class="fw-medium">{{ $house->name }}</div>
                            <div class="small text-body-secondary">{{ $house->code }}</div>
                            @if ($house->motto)
                                <div class="small text-body-tertiary fst-italic">"{{ $house->motto }}"</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-body-secondary py-4">{{ __('No houses yet.') }}</div>
                </div>
            </div>
        @endforelse
    </div>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New house') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('code') is-invalid @enderror" id="house-code" wire:model="code" placeholder=" ">
                                <label for="house-code">{{ __('Code') }}</label>
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="house-name" wire:model="name" placeholder=" ">
                                <label for="house-name">{{ __('Name') }}</label>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="house-colour">{{ __('Colour') }}</label>
                                <input type="color" class="form-control form-control-color" id="house-colour" wire:model="colour">
                            </div>
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" id="house-motto" wire:model="motto" placeholder=" ">
                                <label for="house-motto">{{ __('Motto') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
