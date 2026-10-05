<div>
    <h4 class="mb-1">{{ __('Occurrence book') }}</h4>
    <p class="text-body-secondary small">{{ __('Append-only. A correction is a new entry naming the one it corrects — never an edit.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('#') }}</th><th>{{ __('Occurred') }}</th><th>{{ __('Category') }}</th><th>{{ __('Description') }}</th></tr></thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr wire:key="occ-{{ $entry->id }}">
                                    <td>{{ $entry->entry_number }}</td>
                                    <td>{{ $entry->occurred_at->format('d M H:i') }}</td>
                                    <td>
                                        <span class="{{ in_array($entry->category, ['intrusion', 'fire', 'medical'], true) ? 'badge bg-danger' : '' }}">{{ $entry->category }}</span>
                                    </td>
                                    <td>{{ $entry->description }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No entries.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New entry') }}</div>
                <div class="card-body">
                    <input type="datetime-local" class="form-control mb-2" wire:model="occurredAt">
                    <select class="form-select mb-2" wire:model="category">
                        @foreach (['observation', 'incident', 'handover', 'visitor', 'vehicle', 'intrusion', 'fire', 'medical', 'other'] as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                    <textarea class="form-control mb-2" wire:model="description" placeholder="{{ __('Description') }}"></textarea>
                    <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="personsInvolved" placeholder="{{ __('Persons involved (optional)') }}">
                    <textarea class="form-control mb-2" wire:model="actionTaken" placeholder="{{ __('Action taken (optional)') }}"></textarea>
                    <input type="number" class="form-control mb-2" wire:model="correctsEntryId" placeholder="{{ __('Corrects entry # (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record entry') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
