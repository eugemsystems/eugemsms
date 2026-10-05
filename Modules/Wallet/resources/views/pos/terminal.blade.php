<div>
    <h4 class="mb-1">{{ __('Tuckshop POS') }} ⭐</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <select class="form-select" wire:model.live="spendPointId">
                                <option value="">{{ __('Spend point') }}</option>
                                @foreach ($spendPoints as $point)
                                    <option value="{{ $point->id }}">{{ $point->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select" wire:model="paymentMethod">
                                <option value="wallet">{{ __('Wallet') }}</option>
                                <option value="cash">{{ __('Cash') }}</option>
                                <option value="card">{{ __('Card') }}</option>
                            </select>
                        </div>
                        @if ($paymentMethod === 'wallet')
                            <div class="col-12">
                                <select class="form-select" wire:model="studentId">
                                    <option value="">{{ __('Learner') }}</option>
                                    @foreach ($students as $student)
                                        <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif ($paymentMethod === 'cash')
                            <div class="col-12">
                                <select class="form-select" wire:model="tillSessionId">
                                    <option value="">{{ __('Till session') }}</option>
                                    @foreach ($tillSessions as $session)
                                        <option value="{{ $session->id }}">{{ $session->session_number }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-7">
                            <select class="form-select" wire:model="productId">
                                <option value="">{{ __('Product') }}</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ number_format($product->price_minor / 100, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-3">
                            <input type="number" step="0.01" class="form-control" wire:model="quantity" placeholder="{{ __('Qty') }}">
                        </div>
                        <div class="col-2">
                            <button type="button" class="btn btn-outline-primary w-100" wire:click="addLine">{{ __('Add') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">{{ __('Cart') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($cart as $index => $line)
                                <tr wire:key="cart-{{ $index }}">
                                    <td>{{ $line['name'] }}</td>
                                    <td>{{ $line['quantity'] }}</td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeLine({{ $index }})">{{ __('Remove') }}</button></td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-body-secondary py-3">{{ __('Cart is empty.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" wire:model="offlineMode" id="offlineMode">
                        <label class="form-check-label" for="offlineMode">{{ __('Sale happened offline (sync now)') }}</label>
                    </div>
                    <button type="button" class="btn btn-success" wire:click="submitSale">{{ __('Complete sale') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Your recent sales') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Sale #') }}</th><th>{{ __('Total') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($recentSales as $sale)
                                <tr wire:key="sale-{{ $sale->id }}">
                                    <td>{{ $sale->sale_number }}</td>
                                    <td>{{ number_format($sale->total_minor / 100, 2) }} {{ $sale->currency }}</td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm" wire:click="voidSale({{ $sale->id }})" wire:confirm="{{ __('Void this sale?') }}">{{ __('Void') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No sales yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
