<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('New assignment') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Created as a draft. Choose what happens to a late submission — exactly one of three behaviours.') }}</p>
    </div>
    <div class="card" style="max-width: 760px"><div class="card-body">
        <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
        @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
        <textarea class="form-control mb-2" rows="5" wire:model="instructions" placeholder="{{ __('Instructions') }}"></textarea>
        @error('instructions') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
        <div class="row g-2 mb-2"><div class="col-md-6"><label class="form-label small mb-1">{{ __('Opens') }}</label><input type="datetime-local" class="form-control form-control-sm" wire:model="opensAt"></div><div class="col-md-6"><label class="form-label small mb-1">{{ __('Due') }}</label><input type="datetime-local" class="form-control form-control-sm" wire:model="dueAt">@error('dueAt') <div class="text-danger small">{{ $message }}</div> @enderror</div></div>
        <div class="row g-2 mb-2">
            <div class="col-md-4"><label class="form-label small mb-1">{{ __('If late') }}</label><select class="form-select form-select-sm" wire:model.live="latePolicy"><option value="block">{{ __('Block the submission') }}</option><option value="accept_penalised">{{ __('Accept with a penalty') }}</option><option value="accept_flagged">{{ __('Accept and flag') }}</option></select></div>
            @if ($latePolicy === 'accept_penalised') <div class="col-md-4"><label class="form-label small mb-1">{{ __('Penalty % per day') }}</label><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" wire:model="penalty">@error('penalty') <div class="text-danger small">{{ $message }}</div> @enderror</div> @endif
            <div class="col-md-4"><label class="form-label small mb-1">{{ __('Submission') }}</label><select class="form-select form-select-sm" wire:model="submissionType"><option value="file">{{ __('File') }}</option><option value="text">{{ __('Text') }}</option><option value="link">{{ __('Link') }}</option><option value="both">{{ __('Any') }}</option></select></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-4"><label class="form-label small mb-1">{{ __('Maximum mark') }}</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="maxMark">@error('maxMark') <div class="text-danger small">{{ $message }}</div> @enderror</div>
            <div class="col-md-8"><label class="form-label small mb-1">{{ __('Feeds the gradebook as…') }}</label><select class="form-select form-select-sm" wire:model="assessmentTypeId"><option value="">{{ __('Not linked to the gradebook') }}</option>@foreach ($assessmentTypes as $type) <option value="{{ $type->id }}">{{ $type->name }}</option> @endforeach</select></div>
        </div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="rs" wire:model="allowsResubmission"><label class="form-check-label small" for="rs">{{ __('Allow resubmission (each attempt is kept)') }}</label></div>
        <button type="button" class="btn btn-primary" wire:click="create">{{ __('Create draft') }}</button>
    </div></div>
</div>
