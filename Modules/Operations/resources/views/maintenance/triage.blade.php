<div>
    <h4 class="mb-1">{{ __('Fault triage') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Safety-affecting reports always sort to the top, regardless of stated severity.') }}</p>

    @forelse ($reports as $report)
        <div class="card mb-3" wire:key="report-{{ $report->id }}">
            <div class="card-header d-flex justify-content-between">
                <span>
                    {{ $report->report_number }} — {{ $report->location }}
                    @if ($report->affects_safety) <span class="badge text-bg-danger ms-1">⭐ {{ __('safety') }}</span> @endif
                    <span class="badge text-bg-secondary ms-1">{{ $report->severity }}</span>
                </span>
                <span class="text-body-secondary small">{{ $report->reported_at->diffForHumans() }}</span>
            </div>
            <div class="card-body">
                <p>{{ $report->description }}</p>
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" wire:model="title.{{ $report->id }}" placeholder="{{ __('Work order title') }}">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" wire:model="workType.{{ $report->id }}">
                            @foreach (['corrective', 'preventive', 'improvement', 'inspection', 'emergency'] as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" wire:model="priority.{{ $report->id }}">
                            @foreach (['emergency', 'high', 'normal', 'low'] as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" wire:model="assignedTeam.{{ $report->id }}">
                            <option value="in_house">{{ __('In-house') }}</option>
                            <option value="contractor">{{ __('Contractor') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" wire:model="costCentreId.{{ $report->id }}">
                            <option value="">{{ __('Cost centre') }}</option>
                            @foreach ($costCentres as $cc)
                                <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-primary" wire:click="convert({{ $report->id }})">{{ __('Convert to work order') }}</button>
                </div>
                <hr>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <input type="number" class="form-control form-control-sm" wire:model="duplicateOf.{{ $report->id }}" placeholder="{{ __('Duplicate of report id') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="markDuplicate({{ $report->id }})">{{ __('Mark duplicate') }}</button>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" wire:model="rejectionReason.{{ $report->id }}" placeholder="{{ __('Rejection reason') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $report->id }})">{{ __('Reject') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <p class="text-body-secondary">{{ __('No reports awaiting triage.') }}</p>
    @endforelse
</div>
