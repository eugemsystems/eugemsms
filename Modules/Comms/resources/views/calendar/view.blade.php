<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Calendar') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Everything the school has scheduled, filtered to what you are allowed to see.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <input type="month" class="form-control form-control-sm w-auto" wire:model.live="month">
            @if ($canManage)
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="rebuild" wire:confirm="{{ __('Rebuild the calendar from every registered source now?') }}">{{ __('Rebuild from sources') }}</button>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="{{ $canManage ? 'col-lg-8' : 'col-12' }}">
            <div class="card">
                <div class="card-body">
                    @forelse ($eventsByDay as $day => $dayEvents)
                        <div class="mb-3" wire:key="day-{{ $day }}">
                            <div class="fw-semibold">{{ \Illuminate\Support\Carbon::parse($day)->isoFormat('dddd D MMMM') }}</div>
                            <ul class="list-unstyled mb-0 small">
                                @foreach ($dayEvents as $event)
                                    <li class="py-1 border-bottom" wire:key="ev-{{ $event->id }}">
                                        <span class="text-body-secondary">{{ $event->is_all_day ? __('All day') : $event->starts_at->format('H:i') }}</span>
                                        <strong>{{ $event->title }}</strong>
                                        @if ($event->location) <span class="text-body-secondary">— {{ $event->location }}</span> @endif
                                        <span class="badge bg-label-secondary">{{ str_replace('_', ' ', $event->audience_scope) }}</span>
                                        @if ($event->source_type !== 'manual') <span class="badge bg-label-info">{{ str_replace('_', ' ', $event->source_type) }}</span> @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-body-secondary text-center mb-0 py-3">{{ __('Nothing scheduled this month.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($canManage)
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('Add an event') }}</div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                        @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <label class="form-label small mb-0">{{ __('Starts') }}</label>
                        <input type="datetime-local" class="form-control mb-2" wire:model="startsAt">
                        @error('startsAt') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <label class="form-label small mb-0">{{ __('Ends (optional)') }}</label>
                        <input type="datetime-local" class="form-control mb-2" wire:model="endsAt">
                        @error('endsAt') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="ev-allday" wire:model="isAllDay"><label class="form-check-label small" for="ev-allday">{{ __('All day') }}</label></div>
                        <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location (optional)') }}">
                        <textarea class="form-control mb-2" rows="2" wire:model="description" placeholder="{{ __('Description (optional)') }}"></textarea>
                        <select class="form-select mb-2" wire:model.live="audienceScope">
                            @foreach ($scopeOptions as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                        </select>
                        @if ($scopeTargets !== [])
                            <select class="form-select mb-2" wire:model="audienceScopeId">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($scopeTargets as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                            </select>
                        @endif
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="ev-public" wire:model="isPublic"><label class="form-check-label small" for="ev-public">{{ __('Public (appears on the iCal feed)') }}</label></div>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="createEvent">{{ __('Add event') }}</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
