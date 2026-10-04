<div>
    <h4 class="mb-1">{{ __('Clinic stock') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Controlled stock requires a second, different witness on receipt (BR-BRD-06-013). Expired stock cannot be administered.') }}</p>

    <div class="card mb-4">
        <div class="card-header">{{ __('Stock catalogue') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('On hand') }}</th><th>{{ __('Expiry') }}</th><th></th><th></th></tr></thead>
                <tbody>
                    @forelse ($stock as $item)
                        <tr wire:key="stock-{{ $item->id }}">
                            <td>{{ $item->name }} @if ($item->is_controlled) <span class="badge text-bg-warning">⚠ {{ __('controlled') }}</span> @endif</td>
                            <td>{{ $item->quantity_on_hand }} {{ $item->unit }}</td>
                            <td class="{{ $item->isExpired() ? 'text-danger' : '' }}">{{ $item->expiry_date?->toDateString() ?? '—' }} {{ $item->isExpired() ? __('EXPIRED') : '' }}</td>
                            <td>
                                @if ($receivingStockId === $item->id)
                                    <div class="d-flex gap-2">
                                        <input type="number" step="0.01" class="form-control form-control-sm" wire:model="quantity" style="width:80px">
                                        @if ($item->is_controlled)
                                            <input type="number" class="form-control form-control-sm" wire:model="witnessedByUserId" placeholder="{{ __('Witness user id') }}">
                                        @endif
                                        <input type="text" class="form-control form-control-sm" wire:model="batchNumber" placeholder="{{ __('Batch') }}">
                                        <input type="date" class="form-control form-control-sm" wire:model="expiryDate">
                                        <button type="button" class="btn btn-sm btn-success" wire:click="receive({{ $item->id }})">{{ __('Confirm') }}</button>
                                    </div>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="$set('receivingStockId', {{ $item->id }})">{{ __('Receive') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No stock items.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Controlled register — append-only') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Action') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Balance') }}</th><th>{{ __('Witness') }}</th><th>{{ __('When') }}</th></tr></thead>
                <tbody>
                    @forelse ($controlledLog as $entry)
                        <tr wire:key="log-{{ $entry->id }}">
                            <td>{{ $entry->action }}</td>
                            <td>{{ $entry->quantity }}</td>
                            <td>{{ $entry->balance_after }}</td>
                            <td>{{ $entry->witnessed_by }}</td>
                            <td>{{ $entry->occurred_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No controlled-stock activity.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
