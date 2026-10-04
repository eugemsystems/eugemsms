<div>
    <h4 class="mb-1">{{ __('Script tracking') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Chain of custody — a count mismatch sets the batch to discrepancy immediately and is never silently resolved.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <select class="form-select" wire:model.live="paperId">
                        <option value="">{{ __('Select paper') }}</option>
                        @foreach ($papers as $paper)
                            <option value="{{ $paper->id }}">{{ $paper->subject?->name }} — {{ $paper->paper_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @forelse ($batches as $batch)
                <div class="card mb-3" wire:key="batch-{{ $batch->id }}">
                    <div class="card-header d-flex justify-content-between">
                        <span>{{ $batch->batch_reference }}</span>
                        <span class="badge text-bg-{{ $batch->status === 'discrepancy' ? 'danger' : 'secondary' }}">{{ ucfirst($batch->status) }}</span>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">{{ __('Holder') }}: {{ $batch->currentHolder?->first_name }} {{ $batch->currentHolder?->last_name }} — {{ $batch->script_count }}/{{ $batch->expected_count }} {{ __('scripts') }}</p>

                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-5">
                                <select class="form-select form-select-sm" wire:model="handoverTo.{{ $batch->id }}">
                                    <option value="">{{ __('Hand over to') }}</option>
                                    @foreach ($staff as $member)
                                        <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control form-control-sm" wire:model="handoverCounts.{{ $batch->id }}" placeholder="{{ __('Count') }}">
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-sm btn-primary w-100" wire:click="handover({{ $batch->id }})">{{ __('Handover') }}</button>
                            </div>
                        </div>

                        <ul class="list-group list-group-flush small">
                            @foreach ($batch->custodyLog as $entry)
                                <li class="list-group-item">{{ $entry->action }} — {{ $entry->script_count }} {{ __('scripts') }} @if($entry->discrepancy_note) <span class="text-danger">({{ $entry->discrepancy_note }})</span> @endif</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @empty
                <div class="card"><div class="card-body text-center text-body-secondary">{{ __('No script batches yet for this paper.') }}</div></div>
            @endforelse
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Collect batch') }}</div>
                <div class="card-body">
                    <form wire:submit="collect">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="collectVenueId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($venues as $venue)
                                            <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Venue') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="scriptCount">
                                    <label>{{ __('Script count') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="expectedCount">
                                    <label>{{ __('Expected count') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="collectedByStaffId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($staff as $member)
                                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Collected by') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Collect') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
