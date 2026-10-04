<div>
    <h4 class="mb-1">{{ __('Behaviour board') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Recent records, both polarities. A record under safeguarding review never shows its detail here.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Student') }}</th><th>{{ __('Category') }}</th><th>{{ __('Points') }}</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="board-{{ $record->id }}">
                            <td>{{ $record->occurred_at->toDateString() }}</td>
                            <td>{{ $record->student?->first_name }} {{ $record->student?->last_name }}</td>
                            <td>
                                @if ($record->is_confidential)
                                    <span class="text-body-secondary">{{ __('Under review') }}</span>
                                @else
                                    {{ $record->category?->name }}
                                @endif
                            </td>
                            <td class="{{ $record->polarity === 'positive' ? 'text-success' : 'text-danger' }}">
                                @unless ($record->is_confidential)
                                    {{ $record->polarity === 'positive' ? '+' : '' }}{{ $record->points }}
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No records.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
