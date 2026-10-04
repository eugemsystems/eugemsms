<div>
    <h4 class="mb-1">{{ __('Budget commitments') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Open commitments by line and age — the control that stops three departments spending the same money.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Source') }}</th><th>{{ __('Budget line') }}</th><th>{{ __('Committed') }}</th><th>{{ __('Released') }}</th><th>{{ __('Outstanding') }}</th><th>{{ __('Age') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($commitments as $commitment)
                        <tr wire:key="commit-{{ $commitment->id }}">
                            <td>{{ $commitment->source_type }} #{{ $commitment->source_id }}</td>
                            <td>#{{ $commitment->budget_line_id }}</td>
                            <td>{{ number_format($commitment->committed_minor / 100, 2) }}</td>
                            <td>{{ number_format($commitment->released_minor / 100, 2) }}</td>
                            <td>{{ number_format($commitment->outstanding_minor / 100, 2) }}</td>
                            <td>{{ $commitment->committed_at->diffInDays(now()) }}d</td>
                            <td>{{ $commitment->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No open commitments.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
