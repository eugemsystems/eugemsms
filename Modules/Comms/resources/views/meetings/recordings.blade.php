<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Meeting recordings') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Recordings expire and are removed per the retention period. Only the link is purged; the meeting record stays.') }}</p>
        </div>
        @if ($canPurge)
            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="purgeExpired" wire:confirm="{{ __('Purge every expired recording link now?') }}">{{ __('Purge expired') }}</button>
        @endif
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Meeting') }}</th><th>{{ __('Recording') }}</th><th>{{ __('Expires') }}</th></tr></thead>
                <tbody>
                    @forelse ($meetings as $meeting)
                        <tr wire:key="rec-{{ $meeting->id }}">
                            <td>{{ str_replace('_', ' ', $meeting->meeting_type) }} — {{ $meeting->starts_at->format('D j M Y, H:i') }}</td>
                            <td>
                                @if ($meeting->recording_url)
                                    <a href="{{ $meeting->recording_url }}" target="_blank" rel="noopener noreferrer">{{ __('Open recording') }}</a>
                                @else
                                    <span class="text-body-secondary">{{ $meeting->recording_enabled ? __('not available yet / purged') : '—' }}</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $meeting->recording_expires_on?->toDateString() ?? '—' }}
                                @if ($meeting->recording_url && $meeting->recording_expires_on?->isPast()) <span class="badge bg-label-danger">{{ __('expired') }}</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No recorded meetings.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
