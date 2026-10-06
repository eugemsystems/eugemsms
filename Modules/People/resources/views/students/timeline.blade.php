<div>
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div><h4 class="mb-1">{{ __('Timeline') }}</h4><p class="text-body-secondary mb-0">{{ $student->fullName() }} — {{ $student->admission_number }} · <a href="{{ route('people.students.show', [$school, $student]) }}" wire:navigate>{{ __('Back to profile') }}</a></p></div>
        <select class="form-select form-select-sm w-auto" wire:model.live="category"><option value="">{{ __('All categories') }}</option>@foreach ($categories as $c) <option value="{{ $c }}">{{ ucfirst($c) }}</option> @endforeach</select>
    </div>
    <div class="card"><ul class="list-group list-group-flush">
        @forelse ($events as $event)
            <li class="list-group-item" wire:key="e-{{ $event->id }}">
                <div class="d-flex justify-content-between"><strong>{{ $event->title }}</strong><span class="small text-body-secondary">{{ $event->occurred_at->format('d M Y H:i') }}</span></div>
                <div class="small"><span class="badge text-bg-light border">{{ $event->event_category }}</span> @if ($event->severity && $event->severity !== 'info') <span class="badge {{ ['positive' => 'text-bg-success', 'warning' => 'text-bg-warning', 'serious' => 'text-bg-danger'][$event->severity] ?? 'text-bg-light' }}">{{ $event->severity }}</span> @endif {{ $event->summary }}</div>
            </li>
        @empty
            <li class="list-group-item text-body-secondary">{{ __('Nothing on the timeline yet.') }}</li>
        @endforelse
    </ul></div>
</div>
