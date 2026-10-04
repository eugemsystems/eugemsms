<div>
    <h4 class="mb-1">{{ __('Issue sanction') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('No sanction is applied by the system alone — a named person issues every one (BR-BRD-07-004).') }}</p>

    <div class="card" style="max-width: 720px">
        <div class="card-body">
            <select class="form-select mb-2" wire:model.live="studentId">
                <option value="">{{ __('Student') }}</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }} @if (in_array($student->residency, ['BOARDER', 'WEEKLY_BOARDER'], true)) ({{ __('boarder') }}) @endif</option>
                @endforeach
            </select>

            <select class="form-select mb-2" wire:model="sanctionTypeId">
                <option value="">{{ __('Sanction type') }}</option>
                @foreach ($sanctionTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }} ({{ __('severity') }} {{ $type->severity_level }}) @if ($type->requires_committee) — {{ __('requires committee') }} @endif</option>
                @endforeach
            </select>

            @if ($studentId && $records->isNotEmpty())
                <div class="mb-2">
                    <div class="small fw-bold mb-1">{{ __('Link behaviour records') }}</div>
                    @foreach ($records as $record)
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" value="{{ $record->id }}" wire:model="behaviourRecordIds">
                            <label class="form-check-label small">{{ $record->occurred_at->toDateString() }} — {{ $record->description }} @if ($record->status === 'under_review') <span class="text-danger">({{ __('paused — safeguarding review') }})</span> @endif</label>
                        </div>
                    @endforeach
                </div>
            @endif

            <textarea class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason') }}"></textarea>
            <div class="row g-2 mb-2">
                <div class="col-4"><input type="date" class="form-control" wire:model="startsOn"></div>
                <div class="col-4"><input type="date" class="form-control" wire:model="endsOn" placeholder="{{ __('Ends on') }}"></div>
                <div class="col-4"><input type="number" class="form-control" wire:model="durationDays" placeholder="{{ __('Duration (days)') }}"></div>
            </div>

            @if ($studentId)
                <select class="form-select mb-2" wire:model="committeeRecordId">
                    <option value="">{{ __('Disciplinary committee record (if required)') }}</option>
                    @foreach ($committees as $committee)
                        <option value="{{ $committee->id }}">{{ $committee->convened_on->toDateString() }} — {{ $committee->decision }}</option>
                    @endforeach
                </select>
            @endif

            <input type="text" class="form-control mb-2" wire:model="boardingArrangements" placeholder="{{ __('Boarder supervision/transport arrangements (required if removing a boarder from campus)') }}">

            <button type="button" class="btn btn-primary" wire:click="issue">{{ __('Issue sanction') }}</button>
        </div>
    </div>
</div>
