<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Payment intents') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Live status, manual poll, and (with approval) force-settle without gateway confirmation.') }}</p>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$intents"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="true"
    >
        @forelse ($intents as $intent)
            <tr wire:key="intent-{{ $intent->id }}">
                @if ($this->columnVisible('reference'))
                    <td>
                        {{ $intent->reference }}
                        @if ($intent->gateway_reference)
                            <div class="text-body-secondary small">{{ $intent->gateway_reference }}</div>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('payer_name'))
                    <td>{{ $intent->payer_name }}</td>
                @endif
                @if ($this->columnVisible('amount_minor'))
                    <td>{{ $intent->currency }} {{ number_format($intent->amount_minor / 100, 2) }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ $intent->status === 'succeeded' ? 'text-bg-success' : (in_array($intent->status, ['failed', 'cancelled', 'expired']) ? 'text-bg-danger' : 'text-bg-secondary') }}">
                            {{ ucfirst($intent->status) }}
                        </span>
                        @if ($intent->failure_message)
                            <div class="text-danger small">{{ $intent->failure_message }}</div>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('initiated_at'))
                    <td>{{ $intent->initiated_at->format('d M Y H:i') }}</td>
                @endif
                <td class="text-end">
                    @if (! in_array($intent->status, ['succeeded', 'cancelled', 'refunded']))
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="poll({{ $intent->id }})">{{ __('Poll') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="openForceSettle({{ $intent->id }})">{{ __('Force settle') }}</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No payment intents match these filters.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($forceSettlingId !== null)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="forceSettle">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Force settle') }}</h5>
                            <button type="button" class="btn-close" wire:click="cancelForceSettle" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-body-secondary">{{ __('This settles the intent without the gateway confirming it — use only when you are certain the money has actually arrived.') }}</p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('forceGatewayReference') is-invalid @enderror" wire:model="forceGatewayReference" placeholder=" ">
                                        <label>{{ __('Gateway reference') }}</label>
                                        @error('forceGatewayReference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('forceAmount') is-invalid @enderror" wire:model="forceAmount" placeholder=" ">
                                        <label>{{ __('Amount settled') }}</label>
                                        @error('forceAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('forceFee') is-invalid @enderror" wire:model="forceFee" placeholder=" ">
                                        <label>{{ __('Gateway fee (optional)') }}</label>
                                        @error('forceFee') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="cancelForceSettle">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-warning" wire:loading.attr="disabled">{{ __('Force settle') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
