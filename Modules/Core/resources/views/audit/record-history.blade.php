<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('audit.explorer', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Record history') }}</h4>
            @if ($subjectType !== '' && $subjectId !== null)
                <p class="text-body-secondary mb-0">{{ class_basename($subjectType) }} #{{ $subjectId }}</p>
            @else
                <p class="text-body-secondary mb-0">{{ __('Pass a subject type and ID to see a record\'s timeline.') }}</p>
            @endif
        </div>
    </div>

    <div class="card">
        <ul class="list-group list-group-flush">
            @forelse ($entries as $entry)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <div>
                            <span class="badge text-bg-{{ match ($entry->event) { 'created' => 'success', 'deleted' => 'danger', 'restored' => 'info', default => 'secondary' } }}">
                                {{ \Illuminate\Support\Str::headline((string) $entry->event) }}
                            </span>
                            <strong class="ms-2">{{ $entry->causer?->name ?? __('System') }}</strong>
                        </div>
                        <span class="text-body-secondary small">{{ $entry->created_at->format('d M Y H:i') }}</span>
                    </div>
                    <p class="mb-0 mt-1 small">{{ $entry->description }}</p>
                    @if (! empty($entry->properties['attributes'] ?? []))
                        <pre class="small mt-2 mb-0 bg-body-tertiary p-2 rounded">{{ json_encode($entry->properties['attributes'], JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </li>
            @empty
                <li class="list-group-item text-body-secondary">{{ __('No history for this record.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
