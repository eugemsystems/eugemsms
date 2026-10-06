<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Alumni events') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Events live on the school calendar; this adds who they are for.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Event') }}</th><th>{{ __('Type') }}</th><th>{{ __('Date') }}</th><th>{{ __('For') }}</th><th>{{ __('Ticket') }}</th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr wire:key="ev-{{ $event->id }}"><td>{{ $calendar[$event->calendar_event_id]?->title }}<div class="small text-body-secondary">{{ $calendar[$event->calendar_event_id]?->location }}</div></td><td>{{ str_replace('_', ' ', $event->event_type) }}</td><td class="small">{{ $calendar[$event->calendar_event_id]?->starts_at?->toDayDateTimeString() }}</td><td>{{ $event->target_graduation_years ? implode(', ', $event->target_graduation_years) : __('All alumni') }}</td><td>{{ $event->requires_ticket ? __('Yes') : __('No') }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No alumni events.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('New event') }}</div><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="title" placeholder="{{ __('Title') }}">
            @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm mb-2" wire:model="eventType"><option value="reunion">{{ __('Reunion') }}</option><option value="founders_day">{{ __('Founders’ day') }}</option><option value="sports_gala">{{ __('Sports gala') }}</option><option value="fundraising_dinner">{{ __('Fundraising dinner') }}</option></select>
            <input type="datetime-local" class="form-control form-control-sm mb-2" wire:model="startsAt">
            @error('startsAt') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="text" class="form-control form-control-sm mb-2" wire:model="location" placeholder="{{ __('Location') }}">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="targetYears" placeholder="{{ __('Graduation years, e.g. 2015, 2016 (blank = all)') }}">
            @error('targetYears') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="rt" wire:model="requiresTicket"><label class="form-check-label small" for="rt">{{ __('Requires a ticket') }}</label></div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
        </div></div></div>
    </div>
</div>
