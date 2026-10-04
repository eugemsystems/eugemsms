<div>
    <h4 class="mb-1">{{ __('Goods receipt') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('One journal posts and FIN-09 stock lots are created in the same transaction.') }}</p>

    <div class="card" style="max-width: 50rem">
        <div class="card-body">
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <select class="form-select" wire:model.live="purchaseOrderId">
                        <option value="">{{ __('Purchase order') }}</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}">{{ $order->po_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <select class="form-select" wire:model="grnAccrualAccountId">
                        <option value="">{{ __('GRN accrual account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6"><input type="date" class="form-control" wire:model="receivedOn"></div>
                <div class="col-md-6"><input type="text" class="form-control" wire:model="deliveryNoteRef" placeholder="{{ __('Delivery note reference') }}"></div>
            </div>

            @if ($purchaseOrderId !== null)
                @php($order = $orders->firstWhere('id', $purchaseOrderId))
                <table class="table table-sm">
                    <thead><tr><th>{{ __('Line') }}</th><th>{{ __('Delivered') }}</th><th>{{ __('Accepted') }}</th><th>{{ __('Rejected') }}</th><th>{{ __('Rejection reason') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Expiry') }}</th></tr></thead>
                    <tbody>
                        @foreach ($order?->lines ?? [] as $line)
                            <tr wire:key="grn-line-{{ $line->id }}">
                                <td>{{ $line->description }}</td>
                                <td><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="lines.{{ $line->id }}.quantity_delivered"></td>
                                <td><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="lines.{{ $line->id }}.quantity_accepted"></td>
                                <td><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="lines.{{ $line->id }}.quantity_rejected"></td>
                                <td><input type="text" class="form-control form-control-sm" wire:model="lines.{{ $line->id }}.rejection_reason"></td>
                                <td><input type="text" class="form-control form-control-sm" wire:model="lines.{{ $line->id }}.batch_number"></td>
                                <td><input type="date" class="form-control form-control-sm" wire:model="lines.{{ $line->id }}.expiry_date"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <button type="button" class="btn btn-primary" wire:click="receive">{{ __('Record receipt') }}</button>
        </div>
    </div>
</div>
