<div>
    <h4 class="mb-1">{{ __('Incident') }} — {{ $incident->student->first_name }} {{ $incident->student->last_name }}</h4>
    <p class="text-body-secondary mb-4">
        {{ __('First missed') }}: {{ $incident->first_missed_at->format('Y-m-d H:i') }}
        · {{ __('Hostel') }}: {{ $incident->rollCall?->hostel?->code ?? __('overdue exeat') }}
        · <span class="badge text-bg-{{ $incident->status === 'resolved' ? 'success' : ($incident->status === 'located' ? 'info' : 'danger') }}">{{ ucfirst($incident->status) }}</span>
    </p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Timeline') }}</div>
                <div class="list-group list-group-flush">
                    @forelse ($timeline as $action)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong>{{ str_replace('_', ' ', ucfirst($action->action_type)) }}</strong>
                                <span class="text-body-secondary small">{{ __('Step :n', ['n' => $action->step_number]) }} · {{ $action->occurred_at->format('Y-m-d H:i') }}</span>
                            </div>
                            @if ($action->action_taken)
                                <p class="mb-0 small">"{{ $action->action_taken }}"</p>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('No actions recorded yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-md-5">
            @if (! in_array($incident->status, ['located', 'resolved'], true))
                <div class="card mb-3">
                    <div class="card-header">{{ __('Locate learner') }}</div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-2" wire:model="locationFound" placeholder="{{ __('Where were they found?') }}">
                        <select class="form-select mb-2" wire:model="outcome">
                            <option value="safe">{{ __('Safe') }}</option>
                            <option value="absconded">{{ __('Absconded') }}</option>
                            <option value="medical">{{ __('Medical') }}</option>
                            <option value="unauthorised_absence">{{ __('Unauthorised absence') }}</option>
                            <option value="admin_error">{{ __('Administrative error (false alarm)') }}</option>
                        </select>
                        <textarea class="form-control mb-2" wire:model="outcomeNote" placeholder="{{ __('Outcome note (optional)') }}"></textarea>
                        <button type="button" class="btn btn-success btn-sm" wire:click="locate">{{ __('Record located') }}</button>
                    </div>
                </div>
            @else
                <div class="card mb-3">
                    <div class="card-header">{{ __('Outcome') }}</div>
                    <div class="card-body">
                        <p class="mb-1"><strong>{{ __('Found') }}:</strong> {{ $incident->location_found }}</p>
                        <p class="mb-1"><strong>{{ __('Outcome') }}:</strong> {{ str_replace('_', ' ', $incident->outcome) }}</p>
                        @if ($incident->status === 'located')
                            <button type="button" class="btn btn-sm btn-primary" wire:click="close">{{ __('Close incident') }}</button>
                        @else
                            <span class="badge text-bg-success">{{ __('Closed — remains permanently in the record') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
