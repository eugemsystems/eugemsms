<div>
    <h4 class="mb-1">{{ __('Mark projects') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Marking queue for submitted projects, criterion by criterion against the rubric.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-5">
            <select class="form-select" wire:model.live="briefId">
                <option value="">{{ __('Select brief') }}</option>
                @foreach ($briefs as $brief)
                    <option value="{{ $brief->id }}">{{ $brief->title }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Marking queue') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($queue as $project)
                        <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="queue-{{ $project->id }}">
                            {{ $project->student?->first_name }} {{ $project->student?->last_name }}
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="select({{ $project->id }})">{{ __('Mark') }}</button>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-body-secondary">{{ __('Nothing waiting to be marked.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-md-7">
            @if ($selectedProjectId !== null && $rubric)
                <div class="card">
                    <div class="card-header">{{ __('Mark against :rubric', ['rubric' => $rubric->name]) }}</div>
                    <div class="card-body">
                        @foreach ($rubric->criteria as $criterion)
                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-md-7">{{ $criterion->criterion }} <span class="text-body-secondary">({{ $criterion->weight_percent }}%)</span></div>
                                <div class="col-md-5">
                                    <input type="number" step="0.01" class="form-control form-control-sm" wire:model="marks.{{ $criterion->criterion }}" max="{{ $criterion->max_mark }}" placeholder="/ {{ $criterion->max_mark }}">
                                </div>
                            </div>
                        @endforeach
                        <textarea class="form-control mt-2" wire:model="markerComment" placeholder="{{ __('Marker comment (optional)') }}" style="height: 60px"></textarea>
                        <button type="button" class="btn btn-primary mt-3" wire:click="save">{{ __('Save marks') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
