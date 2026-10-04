<div>
    <h4 class="mb-1">{{ __('Stock on hand') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Quantity, value, and reorder flags by store — a live check, never a cached column.') }}</p>

    <div class="card">
        <div class="card-header">
            <select class="form-select form-select-sm" style="max-width: 20rem" wire:model.live="storeId">
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->code }} — {{ $store->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('On hand') }}</th><th>{{ __('Avg. cost') }}</th><th>{{ __('Value') }}</th><th>{{ __('Reorder') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($balances as $itemId => $balance)
                        <tr wire:key="bal-{{ $itemId }}">
                            <td>{{ $items[$itemId]->name ?? "#{$itemId}" }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $balance->quantity_on_hand, 4), '0'), '.') }} {{ $items[$itemId]->base_unit ?? '' }}</td>
                            <td>{{ $balance->average_unit_cost_minor !== null ? number_format($balance->average_unit_cost_minor / 100, 2) : '—' }}</td>
                            <td>{{ number_format($balance->value_minor / 100, 2) }} {{ $balance->currency }}</td>
                            <td>
                                @if ($reorderItemIds->contains($itemId))
                                    <span class="badge text-bg-warning">⚠ {{ __('reorder') }}</span>
                                @endif
                            </td>
                            <td><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="rebuild({{ $itemId }})">{{ __('Rebuild') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No stock in this store.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
