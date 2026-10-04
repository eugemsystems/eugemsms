<div>
    <h4 class="mb-1">{{ __('Stock transfers') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('In transit stock belongs to neither store\'s available balance until received.') }}</p>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Dispatch a transfer') }}</div>
                <div class="card-body">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <select class="form-select" wire:model="fromStoreId">
                                <option value="">{{ __('From store') }}</option>
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}">{{ $store->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select" wire:model="toStoreId">
                                <option value="">{{ __('To store') }}</option>
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}">{{ $store->code }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="text" class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason') }}">
                    @foreach ($lines as $index => $line)
                        <div class="d-flex gap-2 mb-2" wire:key="transfer-line-{{ $index }}">
                            <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.item_id">
                                <option value="">{{ __('Item') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.0001" class="form-control form-control-sm" style="max-width: 7rem" wire:model="lines.{{ $index }}.quantity" placeholder="{{ __('Qty') }}">
                            @if (count($lines) > 1)
                                <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeLine({{ $index }})"><i class="ri ri-delete-bin-line"></i></button>
                            @endif
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" wire:click="addLine">{{ __('Add line') }}</button>
                    <div><button type="button" class="btn btn-primary" wire:click="dispatchTransfer">{{ __('Dispatch') }}</button></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('In transit — receive') }}</div>
                <div class="card-body">
                    @forelse ($inTransit as $transfer)
                        <div class="border rounded p-2 mb-2" wire:key="transit-{{ $transfer->id }}">
                            <div class="fw-semibold">{{ $transfer->transfer_number }}: {{ $transfer->fromStore->code }} → {{ $transfer->toStore->code }}</div>
                            @foreach ($transfer->lines as $line)
                                <div class="d-flex align-items-center gap-2 my-1">
                                    <span style="min-width: 10rem">{{ $line->item->name }} ({{ __('dispatched') }} {{ $line->quantity_dispatched }})</span>
                                    <input type="number" step="0.0001" class="form-control form-control-sm" style="max-width: 7rem" wire:model="received.{{ $transfer->id }}.{{ $line->id }}" placeholder="{{ $line->quantity_dispatched }}">
                                </div>
                            @endforeach
                            <button type="button" class="btn btn-sm btn-success mt-1" wire:click="receive({{ $transfer->id }})">{{ __('Receive') }}</button>
                        </div>
                    @empty
                        <p class="text-body-secondary mb-0">{{ __('Nothing in transit.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Recently completed') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Transfer') }}</th><th>{{ __('Route') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach ($completed as $transfer)
                        <tr wire:key="completed-{{ $transfer->id }}">
                            <td>{{ $transfer->transfer_number }}</td>
                            <td>{{ $transfer->fromStore->code }} → {{ $transfer->toStore->code }}</td>
                            <td><span class="badge text-bg-{{ $transfer->status === 'discrepancy' ? 'danger' : 'success' }}">{{ $transfer->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
