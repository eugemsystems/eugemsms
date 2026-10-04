<div>
    <h4 class="mb-1">{{ __('Budget variance') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Actuals derive exclusively from the general ledger — never typed in by hand.') }}</p>

    <div class="card">
        <div class="card-header d-flex gap-2 align-items-center">
            <select class="form-select form-select-sm" style="max-width: 20rem" wire:model.live="budgetId">
                <option value="">{{ __('All budgets') }}</option>
                @foreach ($budgets as $budget)
                    <option value="{{ $budget->id }}">{{ $budget->name }} v{{ $budget->version }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="checkVariance">{{ __('Check variance alerts') }}</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Cost centre') }}</th><th>{{ __('Annual') }}</th><th>{{ __('Committed') }}</th><th>{{ __('Actual') }}</th><th>{{ __('Available') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr wire:key="vline-{{ $line->id }}" class="{{ $line->available_minor < 0 ? 'table-danger' : '' }}">
                            <td>{{ $accounts[$line->account_id]->code ?? $line->account_id }}</td>
                            <td>{{ $costCentres[$line->cost_centre_id]->code ?? $line->cost_centre_id }}</td>
                            <td>{{ number_format($line->annual_amount_minor / 100, 2) }}</td>
                            <td>{{ number_format($line->committed_minor / 100, 2) }}</td>
                            <td>{{ number_format($line->actual_minor / 100, 2) }}</td>
                            <td>{{ number_format($line->available_minor / 100, 2) }}</td>
                            <td><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="recalculate({{ $line->id }})">{{ __('Recalculate') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No lines.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
