<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Publish report cards') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Publishing tells guardians a report is ready to view in the portal. Review first: this cannot be undone except by amending a mark.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-5"><div class="card"><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model.live="classId"><option value="">{{ __('A class…') }}</option>@foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }}</option> @endforeach</select>
            <div class="text-center small text-body-secondary mb-2">{{ __('or') }}</div>
            <select class="form-select form-select-sm mb-3" wire:model.live="gradeLevelId"><option value="">{{ __('A whole grade level…') }}</option>@foreach ($gradeLevels as $level) <option value="{{ $level->id }}">{{ $level->name }}</option> @endforeach</select>
            @if ($counts !== []) <ul class="list-unstyled small mb-3">@foreach ($counts as $status => $total) <li>{{ $total }} × {{ $status }}</li> @endforeach</ul> @endif
            @error('classId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-danger btn-sm" wire:click="publish" wire:confirm="{{ __('Publish these report cards to guardians?') }}">{{ __('Publish') }}</button>
        </div></div></div>
        @if ($lastResult)
            <div class="col-lg-7"><div class="card"><div class="card-header">{{ __('Result') }}</div><ul class="list-group list-group-flush">
                <li class="list-group-item">{{ $lastResult['published'] }} {{ __('published') }} ({{ $lastResult['released'] }} {{ __('released from withheld') }})</li>
                <li class="list-group-item {{ $lastResult['withheld'] ? 'text-danger' : '' }}">{{ $lastResult['withheld'] }} {{ __('still withheld — fee balance') }}</li>
                <li class="list-group-item {{ $lastResult['notGenerated'] ? 'text-warning' : '' }}">{{ $lastResult['notGenerated'] }} {{ __('not generated yet') }}</li>
            </ul></div></div>
        @endif
    </div>
</div>
