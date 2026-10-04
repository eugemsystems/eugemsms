<div>
    <h4 class="mb-1">{{ __('Item ledger') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Every movement, running balance, drill to the posted journal.') }}</p>

    <div class="card">
        <div class="card-header d-flex gap-2">
            <select class="form-select form-select-sm" style="max-width: 16rem" wire:model.live="storeId">
                <option value="">{{ __('All stores') }}</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->code }}</option>
                @endforeach
            </select>
            <select class="form-select form-select-sm" style="max-width: 18rem" wire:model.live="itemId">
                <option value="">{{ __('All items') }}</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Dir') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Unit cost') }}</th><th>{{ __('Total') }}</th><th>{{ __('Balance after') }}</th><th>{{ __('Journal') }}</th></tr></thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr wire:key="mv-{{ $movement->id }}">
                            <td>{{ $movement->occurred_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $movement->movement_type }}</td>
                            <td><span class="badge text-bg-{{ $movement->direction === 'in' ? 'success' : 'secondary' }}">{{ $movement->direction }}</span></td>
                            <td>{{ rtrim(rtrim(number_format((float) $movement->quantity, 4), '0'), '.') }}</td>
                            <td>{{ number_format($movement->unit_cost_minor / 100, 2) }}</td>
                            <td>{{ number_format($movement->total_cost_minor / 100, 2) }} {{ $movement->currency }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $movement->balance_after, 4), '0'), '.') }}</td>
                            <td>
                                @if ($movement->journal_id)
                                    <a href="{{ route('finance.journals.show', ['school' => $school, 'journal' => $movement->journal_id]) }}" wire:navigate>#{{ $movement->journal_id }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-3">{{ __('No movements.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $movements->links() }}</div>
    </div>
</div>
