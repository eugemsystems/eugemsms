<div>
    <h4 class="mb-1">{{ __('Report a fault') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Anyone can report a fault — location, a description and a severity are all that\'s required.') }}</p>

    <div class="card" style="max-width: 40rem">
        <div class="card-body">
            <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location') }}">
            <select class="form-select mb-2" wire:model="maintenanceAssetId">
                <option value="">{{ __('Related asset (optional)') }}</option>
                @foreach ($assets as $asset)
                    <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                @endforeach
            </select>
            <select class="form-select mb-2" wire:model="category">
                @foreach (['plumbing', 'electrical', 'carpentry', 'glazing', 'roofing', 'painting', 'ict', 'grounds', 'vehicle', 'appliance', 'other'] as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
            <textarea class="form-control mb-2" wire:model="description" rows="3" placeholder="{{ __('Describe the fault') }}"></textarea>
            <select class="form-select mb-2" wire:model="severity">
                @foreach (['emergency', 'urgent', 'routine', 'cosmetic'] as $sev)
                    <option value="{{ $sev }}">{{ $sev }}</option>
                @endforeach
            </select>
            <div class="form-check mb-1">
                <input type="checkbox" class="form-check-input" id="affectsSafety" wire:model="affectsSafety">
                <label class="form-check-label" for="affectsSafety">⭐ {{ __('Affects safety — jumps the triage queue immediately') }}</label>
            </div>
            <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" id="affectsTeaching" wire:model="affectsTeaching">
                <label class="form-check-label" for="affectsTeaching">{{ __('Affects teaching') }}</label>
            </div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="report">{{ __('Submit report') }}</button>
        </div>
    </div>
</div>
