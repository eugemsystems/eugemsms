<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Notice board') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Read receipts are tracked for important and urgent notices only.') }}</p>
        </div>
        @if ($canPost)
            <a href="{{ route('comms.notices.compose', $school) }}" class="btn btn-primary btn-sm" wire:navigate>{{ __('Post notice') }}</a>
        @endif
    </div>

    <select class="form-select form-select-sm mb-3 w-auto" wire:model.live="statusFilter">
        <option value="">{{ __('All statuses') }}</option>
        <option value="published">{{ __('Published') }}</option>
        <option value="scheduled">{{ __('Scheduled') }}</option>
        <option value="draft">{{ __('Draft') }}</option>
        <option value="expired">{{ __('Expired') }}</option>
    </select>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Notice') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Audience') }}</th><th>{{ __('Publishes') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Read') }}</th></tr></thead>
                <tbody>
                    @forelse ($notices as $notice)
                        <tr wire:key="notice-{{ $notice->id }}">
                            <td>@if ($notice->is_pinned) <i class="ri ri-pushpin-fill text-primary"></i> @endif {{ $notice->title }}</td>
                            <td><span class="badge {{ $notice->priority === 'urgent' ? 'bg-label-danger' : ($notice->priority === 'important' ? 'bg-label-warning' : 'bg-label-secondary') }}">{{ $notice->priority }}</span></td>
                            <td class="small">{{ str_replace('_', ' ', $notice->audience_scope) }}</td>
                            <td class="small">{{ $notice->publish_at?->toDateTimeString() }}</td>
                            <td><span class="badge {{ $notice->status === 'published' ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $notice->status }}</span></td>
                            <td class="text-end">{{ $notice->tracksReadReceipts() ? $notice->reads_count : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No notices yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
