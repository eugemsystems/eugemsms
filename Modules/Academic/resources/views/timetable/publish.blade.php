<div>
    <h4 class="mb-1">{{ __('Publish timetable') }}</h4>
    <p class="text-body-secondary mb-4">{{ $timetable->name }} — {{ ucfirst($timetable->status) }}</p>

    <div class="card mb-3">
        <div class="card-body">
            @if ($clashCount > 0)
                <div class="alert alert-danger mb-3">{{ __(':count hard clash(es) exist — publication is refused until they are resolved (AC-ACA-03-003).', ['count' => $clashCount]) }}</div>
            @else
                <div class="alert alert-success mb-3">{{ __('No hard clashes detected — ready to publish.') }}</div>
            @endif

            <button type="button" class="btn btn-primary" wire:click="publish" wire:loading.attr="disabled" @disabled($clashCount > 0)>{{ __('Publish') }}</button>
        </div>
    </div>

    @if ($timetable->status === 'published')
        <div class="card">
            <div class="card-header">{{ __('Generate attendance sessions') }}</div>
            <div class="card-body">
                <p class="text-body-secondary">{{ __('Generates sessions for the next N days only. Past sessions carrying marks are never touched.') }}</p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="number" class="form-control" wire:model="sessionDays" min="1">
                            <label>{{ __('Days ahead') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-primary w-100" wire:click="generateSessions" wire:loading.attr="disabled">{{ __('Generate sessions') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
