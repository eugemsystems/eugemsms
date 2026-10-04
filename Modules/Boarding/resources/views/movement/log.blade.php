<div>
    <h4 class="mb-1">{{ __('Movement log') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Append-only. Crossing a boundary checkpoint without an active exeat is flagged unauthorised and alerts security.') }}</p>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Learner') }}</th><th>{{ __('Checkpoint') }}</th><th>{{ __('Direction') }}</th><th>{{ __('Method') }}</th><th>{{ __('Authorised') }}</th></tr></thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr wire:key="mv-{{ $entry->id }}">
                                    <td>{{ $entry->occurred_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $entry->student->first_name }} {{ $entry->student->last_name }}</td>
                                    <td>{{ $entry->checkpoint->name }}</td>
                                    <td>{{ ucfirst($entry->direction) }}</td>
                                    <td>{{ $entry->method }}</td>
                                    <td>
                                        @if ($entry->is_authorised)
                                            <span class="badge text-bg-success">{{ __('Yes') }}</span>
                                        @else
                                            <span class="badge text-bg-danger">{{ __('Unauthorised') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No movement recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">{{ __('Record manual scan') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model.live.debounce.400ms="studentSearch" placeholder="{{ __('Search learner') }}">
                    @if ($searchResults->isNotEmpty())
                        <div class="list-group mb-2">
                            @foreach ($searchResults as $student)
                                <button type="button" class="list-group-item list-group-item-action {{ $studentId === $student->id ? 'active' : '' }}" wire:click="$set('studentId', {{ $student->id }})">{{ $student->first_name }} {{ $student->last_name }}</button>
                            @endforeach
                        </div>
                    @endif
                    <select class="form-select mb-2" wire:model="checkpointId">
                        <option value="">{{ __('Checkpoint') }}</option>
                        @foreach ($checkpoints as $checkpoint)
                            <option value="{{ $checkpoint->id }}">{{ $checkpoint->name }} @if ($checkpoint->is_boundary) ({{ __('boundary') }}) @endif</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="direction">
                        <option value="out">{{ __('Out') }}</option>
                        <option value="in">{{ __('In') }}</option>
                    </select>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="hasActiveExeat" id="hasActiveExeat">
                        <label class="form-check-label" for="hasActiveExeat">{{ __('Has an active exeat') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
