<div>
    <h4 class="mb-1">{{ __('Serving terminal') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('This is a safeguarding control, not a convenience. Shown at every meal, not only the first.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-auto">
            <input type="date" class="form-control form-control-sm" wire:model.live="serviceDate">
        </div>
        <div class="col-auto">
            <select class="form-select form-select-sm" wire:model.live="meal">
                <option value="breakfast">{{ __('Breakfast') }}</option>
                <option value="lunch">{{ __('Lunch') }}</option>
                <option value="dinner">{{ __('Dinner') }}</option>
            </select>
        </div>
        @if ($captureEnabled)
            <div class="col-auto d-flex align-items-center small text-body-secondary">
                {{ $service ? __(':count served so far', ['count' => $servedCount]) : __('No service planned for this date and meal yet.') }}
            </div>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <input type="text" class="form-control form-control-lg" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search learner by name or admission number') }}">
            @if ($results->isNotEmpty())
                <div class="list-group mt-2">
                    @foreach ($results as $student)
                        <button type="button" class="list-group-item list-group-item-action" wire:click="scan({{ $student->id }})">{{ $student->first_name }} {{ $student->last_name }} ({{ $student->admission_number }})</button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($scannedStudent)
        <div class="card">
            <div class="card-body text-center">
                <div class="rounded-circle bg-secondary-subtle d-inline-flex align-items-center justify-content-center mb-3" style="width: 96px; height: 96px; font-size: 2rem;">
                    {{ mb_substr($scannedStudent->first_name, 0, 1) }}{{ mb_substr($scannedStudent->last_name, 0, 1) }}
                </div>
                <h3>{{ $scannedStudent->first_name }} {{ $scannedStudent->last_name }}</h3>

                @if ($alerts->isEmpty())
                    <p class="text-success fs-4 mt-3">{{ __('No dietary alerts.') }}</p>
                @else
                    @foreach ($alerts as $alert)
                        <div class="alert {{ $alert->severity === 'life_threatening' ? 'alert-danger' : 'alert-warning' }} text-start" style="font-size: 1.4rem;">
                            <strong>{{ strtoupper(str_replace('_', ' ', $alert->severity)) }}</strong> — {{ ucfirst($alert->requirementType) }}: {{ $alert->description }}
                            @if ($alert->requiresEpipen) <div class="fw-bold">{{ __('REQUIRES EPIPEN') }}</div> @endif
                            @unless ($alert->isVerified) <div class="small">{{ __('(not yet nurse-verified)') }}</div> @endunless
                        </div>
                    @endforeach
                @endif

                @if ($captureEnabled)
                    @if ($alreadyServed)
                        <p class="text-body-secondary mt-3">{{ __('Already recorded as served for this meal.') }}</p>
                    @else
                        <div class="mt-3 d-flex gap-2 justify-content-center">
                            @if ($alerts->isNotEmpty())
                                <button type="button" class="btn btn-success" wire:click="confirmServed(true)">{{ __('Served — special meal given') }}</button>
                                <button type="button" class="btn btn-outline-danger" wire:click="confirmServed(false)">{{ __('Served — standard meal only') }}</button>
                            @else
                                <button type="button" class="btn btn-success" wire:click="confirmServed(false)">{{ __('Confirm served') }}</button>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
