<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.notices.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Post notice') }} ⚠</h4>
            <p class="text-body-secondary small mb-0">{{ __('Aim a notice at the whole school, staff, one section or one grade level. Urgent and important notices track who has read them.') }}</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <textarea class="form-control mb-2" rows="6" wire:model="body" placeholder="{{ __('Notice text') }}"></textarea>
                    @error('body') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <select class="form-select" wire:model="priority">
                                <option value="normal">{{ __('Normal') }}</option>
                                <option value="important">{{ __('Important') }}</option>
                                <option value="urgent">{{ __('Urgent') }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select" wire:model.live="audienceScope">
                                @foreach ($scopeOptions as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    @if ($scopeTargets !== [])
                        <select class="form-select mb-2" wire:model="audienceScopeId">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($scopeTargets as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                        </select>
                    @endif
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small mb-0">{{ __('Publish at (blank = now)') }}</label><input type="datetime-local" class="form-control" wire:model="publishAt"></div>
                        <div class="col-6"><label class="form-label small mb-0">{{ __('Expires (optional)') }}</label><input type="datetime-local" class="form-control" wire:model="expiresAt"></div>
                    </div>
                    @error('expiresAt') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="notice-pin" wire:model="isPinned"><label class="form-check-label small" for="notice-pin">{{ __('Pin to the top of the board') }}</label></div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="post">{{ __('Post notice') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
