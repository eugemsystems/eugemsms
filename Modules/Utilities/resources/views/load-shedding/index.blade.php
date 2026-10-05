<div>
    <h4 class="mb-1">{{ __('Load shedding') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Window') }}</th><th>{{ __('Source') }}</th></tr></thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr wire:key="loadshed-{{ $entry->id }}">
                                    <td>{{ $entry->schedule_date->toDateString() }}</td>
                                    <td>{{ $entry->stage ?? '—' }}</td>
                                    <td>{{ $entry->starts_at }}–{{ $entry->ends_at }}</td>
                                    <td>{{ $entry->source }}</td>
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
                <div class="card-header">{{ __('Record entry') }}</div>
                <div class="card-body">
                    <input type="date" class="form-control mb-2" wire:model="scheduleDate">
                    <input type="text" class="form-control mb-2" wire:model="stage" placeholder="{{ __('Stage (optional)') }}">
                    <input type="time" class="form-control mb-2" wire:model="startsAt">
                    <input type="time" class="form-control mb-2" wire:model="endsAt">
                    <select class="form-select mb-2" wire:model="source">
                        <option value="published">{{ __('Published schedule') }}</option>
                        <option value="observed">{{ __('Observed actual outage') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="impactNote" placeholder="{{ __('Impact note (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
