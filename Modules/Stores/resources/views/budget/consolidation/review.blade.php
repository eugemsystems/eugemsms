<div>
    <h4 class="mb-1">{{ __('Budget consolidation') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Consolidates every submitted line, then a different user approves.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Budget') }}</th><th>{{ __('Status') }}</th><th>{{ __('Income') }}</th><th>{{ __('Expense') }}</th><th>{{ __('Surplus') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($budgets as $budget)
                        <tr wire:key="budget-{{ $budget->id }}">
                            <td>{{ $budget->name }} v{{ $budget->version }}</td>
                            <td>{{ $budget->status }}</td>
                            <td>{{ number_format($budget->total_income_minor / 100, 2) }}</td>
                            <td>{{ number_format($budget->total_expense_minor / 100, 2) }}</td>
                            <td>{{ number_format($budget->surplus_minor / 100, 2) }}</td>
                            <td class="d-flex gap-1">
                                @if ($budget->status === 'draft')
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="consolidate({{ $budget->id }})">{{ __('Consolidate') }}</button>
                                @endif
                                @if ($budget->status === 'under_review')
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $budget->id }})">{{ __('Approve') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No budgets.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
