<div>
    <h4 class="mb-1">{{ __('Fiscal audit log') }}</h4>
    <p class="text-body-secondary small">{{ __('Append-only. When ZIMRA disputes what was sent, the payload settles it.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Event') }}</th><th>{{ __('Reference') }}</th><th>{{ __('HTTP status') }}</th><th>{{ __('When') }}</th></tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr wire:key="audit-{{ $entry->id }}">
                            <td>{{ $entry->event_type }}</td>
                            <td>{{ $entry->reference }}</td>
                            <td>{{ $entry->http_status }}</td>
                            <td>{{ $entry->occurred_at->toDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No audit entries yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
