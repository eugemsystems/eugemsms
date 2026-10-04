<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Reconciliation exceptions') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Nothing here resolves itself — every exception is closed with a recorded note, or converted to a suspense receipt where that applies.') }}</p>
    </div>

    @forelse ($runs as $run)
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>{{ __('Run :date — :scope scope', ['date' => $run->run_date->format('d M Y'), 'scope' => ucfirst($run->scope)]) }}</span>
                <span class="badge {{ $run->reviewed_at ? 'text-bg-success' : 'text-bg-warning' }}">
                    {{ $run->reviewed_at ? __('Fully reviewed') : __('Needs review') }}
                </span>
            </div>
            <div class="list-group list-group-flush">
                @foreach ($run->exceptions ?? [] as $index => $exception)
                    <div class="list-group-item">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="badge text-bg-danger mb-1">{{ str_replace('_', ' ', $exception['class']) }}</span>
                                <div class="small">
                                    @switch($exception['class'])
                                        @case('GATEWAY_NO_RECEIPT')
                                            {{ __('Gateway reference :ref settled for :amount but we have no matching receipt.', ['ref' => $exception['gateway_reference'], 'amount' => number_format($exception['amount_minor'] / 100, 2)]) }}
                                            @break
                                        @case('RECEIPT_NO_GATEWAY')
                                            {{ __('We receipted gateway reference :ref for :amount, but the gateway shows nothing for it.', ['ref' => $exception['gateway_reference'], 'amount' => number_format($exception['amount_minor'] / 100, 2)]) }}
                                            @break
                                        @case('AMOUNT_MISMATCH')
                                            {{ __('Gateway reference :ref — gateway reports :gateway, our receipt shows :receipt.', ['ref' => $exception['gateway_reference'], 'gateway' => number_format($exception['gateway_amount_minor'] / 100, 2), 'receipt' => number_format($exception['receipt_amount_minor'] / 100, 2)]) }}
                                            @break
                                        @case('DUPLICATE_SETTLEMENT')
                                            {{ __('Gateway reference :ref was reported settled more than once in this run.', ['ref' => $exception['gateway_reference']]) }}
                                            @break
                                        @case('BANK_NO_RECEIPT')
                                            {{ __(':description — an unmatched bank credit of :amount with no receipt behind it.', ['description' => $exception['description'], 'amount' => number_format($exception['amount_minor'] / 100, 2)]) }}
                                            @break
                                        @default
                                            {{ json_encode($exception) }}
                                    @endswitch
                                </div>
                                @if (isset($exception['resolved_at']))
                                    <div class="text-success small mt-1">
                                        <i class="ri ri-check-line"></i> {{ __('Resolved: :note', ['note' => $exception['resolution_note']]) }}
                                    </div>
                                @endif
                            </div>
                            @if (! isset($exception['resolved_at']))
                                <div class="d-flex gap-2">
                                    @if ($exception['class'] === 'BANK_NO_RECEIPT')
                                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="convertToSuspense({{ $run->id }}, {{ $index }}, {{ $exception['bank_statement_line_id'] }})" wire:confirm="{{ __('Convert this unmatched credit to a suspense receipt and resolve this exception?') }}">
                                            {{ __('Convert to suspense') }}
                                        </button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="startResolving({{ $run->id }}, {{ $index }})">{{ __('Resolve') }}</button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-center text-body-secondary py-5">{{ __('No reconciliation runs have any exceptions.') }}</div>
    @endforelse

    @if ($resolvingRunId !== null)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="resolve">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Resolve exception') }}</h5>
                            <button type="button" class="btn-close" wire:click="cancelResolving" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline">
                                <textarea class="form-control @error('resolutionNote') is-invalid @enderror" wire:model="resolutionNote" style="height: 100px" placeholder=" "></textarea>
                                <label>{{ __('Resolution note') }}</label>
                                @error('resolutionNote') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="cancelResolving">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Resolve') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
