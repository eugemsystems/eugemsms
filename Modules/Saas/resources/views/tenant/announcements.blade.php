<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Announcements') }}</h4><p class="text-body-secondary small mb-0">{{ __('Notices from the software vendor for your organisation, and any live incidents.') }}</p></div>
    @foreach ($incidents as $incident)
        <div class="alert alert-warning" wire:key="ai-{{ $incident->id }}"><strong>{{ $incident->title }}</strong> — {{ __(ucfirst($incident->status)) }} · {{ implode(', ', $incident->affected_components) }}</div>
    @endforeach
    @forelse ($announcements as $a)
        <div class="card mb-3" wire:key="an-{{ $a->id }}"><div class="card-body">
            <div class="d-flex gap-2 align-items-center"><strong>{{ $a->title }}</strong><span class="badge text-bg-{{ ['incident' => 'danger', 'maintenance' => 'warning', 'info' => 'info'][$a->severity] ?? 'secondary' }}">{{ __(ucfirst($a->severity)) }}</span><span class="small text-body-secondary ms-auto">{{ $a->starts_at?->toDayDateTimeString() }}</span></div>
            <div class="mt-2" style="white-space: pre-line">{{ $a->body }}</div>
        </div></div>
    @empty
        <div class="text-body-secondary">{{ __('No announcements right now.') }}</div>
    @endforelse
</div>
