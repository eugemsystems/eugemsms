<div>
    <h4 class="mb-1">{{ __('Purchase orders') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Budget impact is shown before approval — approving commits it immediately.') }}</p>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('PO') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr wire:key="po-{{ $order->id }}">
                                    <td>{{ $order->po_number }}</td>
                                    <td>{{ $order->supplier->name }}</td>
                                    <td>{{ number_format($order->total_minor / 100, 2) }} {{ $order->currency }}</td>
                                    <td>{{ $order->status }}</td>
                                    <td class="d-flex gap-1">
                                        @if ($order->status === 'pending_approval')
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $order->id }})">{{ __('Approve') }}</button>
                                        @endif
                                        @if (! in_array($order->status, ['received', 'invoiced', 'closed', 'cancelled'], true))
                                            <input type="text" class="form-control form-control-sm" style="max-width: 9rem" wire:model="cancelReasons.{{ $order->id }}" placeholder="{{ __('Reason') }}">
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="cancel({{ $order->id }})">{{ __('Cancel') }}</button>
                                        @endif
                                        @if (in_array($order->status, ['partially_received', 'received', 'invoiced'], true))
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="closeShort({{ $order->id }})">{{ __('Close short') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No orders.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New order') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="supplierId">
                        <option value="">{{ __('Supplier') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model.live="budgetLineId">
                        <option value="">{{ __('Budget line (optional)') }}</option>
                        @foreach ($budgetLines as $line)
                            <option value="{{ $line->id }}">#{{ $line->id }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="orderDate">

                    @foreach ($lines as $index => $line)
                        <div class="border rounded p-2 mb-2" wire:key="po-line-{{ $index }}">
                            <select class="form-select form-select-sm mb-1" wire:model="lines.{{ $index }}.item_id">
                                <option value="">{{ __('Item (optional)') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" class="form-control form-control-sm mb-1" wire:model="lines.{{ $index }}.description" placeholder="{{ __('Description') }}">
                            <div class="row g-1 mb-1">
                                <div class="col-3"><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="lines.{{ $index }}.quantity_ordered" placeholder="{{ __('Qty') }}"></div>
                                <div class="col-3"><input type="text" class="form-control form-control-sm" wire:model="lines.{{ $index }}.unit" placeholder="{{ __('Unit') }}"></div>
                                <div class="col-3"><input type="number" class="form-control form-control-sm" wire:model.live="lines.{{ $index }}.unit_price_minor" placeholder="{{ __('Price (minor)') }}"></div>
                                <div class="col-3"><input type="number" step="0.01" class="form-control form-control-sm" wire:model.live="lines.{{ $index }}.tax_rate_percent" placeholder="{{ __('Tax %') }}"></div>
                            </div>
                            <div class="row g-1 mb-1">
                                <div class="col-6">
                                    <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.store_id">
                                        <option value="">{{ __('Receive into store (if stocked)') }}</option>
                                        @foreach ($stores as $store)
                                            <option value="{{ $store->id }}">{{ $store->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.expense_account_id">
                                        <option value="">{{ __('Expense account (if not stocked)') }}</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" wire:model="lines.{{ $index }}.is_capital" id="cap-{{ $index }}">
                                <label class="form-check-label" for="cap-{{ $index }}">{{ __('Capital item → FIN-10') }}</label>
                            </div>
                            @if (count($lines) > 1)
                                <button type="button" class="btn btn-sm btn-outline-danger mt-1" wire:click="removeLine({{ $index }})">{{ __('Remove') }}</button>
                            @endif
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" wire:click="addLine">{{ __('Add line') }}</button>

                    <div class="alert alert-light border mb-2">
                        {{ __('Estimated total') }}: {{ number_format($estimatedTotalMinor / 100, 2) }}
                        @if ($budgetAvailableMinor !== null)
                            — {{ __('budget available') }}: <span class="{{ $budgetAvailableMinor < $estimatedTotalMinor ? 'text-danger fw-semibold' : 'text-success' }}">{{ number_format($budgetAvailableMinor / 100, 2) }}</span>
                        @endif
                    </div>

                    <button type="button" class="btn btn-primary" wire:click="create">{{ __('Create order') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
