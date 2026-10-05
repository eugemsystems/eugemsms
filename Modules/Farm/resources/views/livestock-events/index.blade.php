<div>
    <h4 class="mb-1">{{ __('Livestock events') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Birth, death, dipping, vaccination, treatment, weighing — withdrawal periods captured directly on a treatment.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Livestock') }}</th><th>{{ __('Event') }}</th><th>{{ __('Date') }}</th><th>{{ __('Withdrawal ends') }}</th></tr></thead>
                        <tbody>
                            @forelse ($events as $event)
                                <tr wire:key="event-{{ $event->id }}">
                                    <td>{{ $event->livestock->tag_number ?? $event->livestock->species }}</td>
                                    <td>{{ $event->event_type }}</td>
                                    <td>{{ $event->event_date->toFormattedDateString() }}</td>
                                    <td>{{ $event->withdrawal_ends_on?->toFormattedDateString() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No events.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Record event') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="livestockId">
                        <option value="">{{ __('Livestock') }}</option>
                        @foreach ($livestock as $animal)
                            <option value="{{ $animal->id }}">{{ $animal->tag_number ?? $animal->species }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="eventType">
                        @foreach (['birth', 'death', 'dipping', 'vaccination', 'treatment', 'weighing', 'service', 'calving', 'sale', 'slaughter', 'feed'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="headCountAffected" placeholder="{{ __('Head count affected') }}">
                    <textarea class="form-control mb-2" wire:model="description" rows="2" placeholder="{{ __('Description (optional)') }}"></textarea>
                    <input type="text" class="form-control mb-2" wire:model="medication" placeholder="{{ __('Medication (optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="withdrawalPeriodDays" placeholder="{{ __('Withdrawal period (days, optional)') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="weightKg" placeholder="{{ __('Weight (kg, optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record event') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
