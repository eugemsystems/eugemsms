<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.invoices.show', ['school' => $school, 'invoice' => $invoice]) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Void :number', ['number' => $invoice->invoice_number]) }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Refused if any payment has been allocated — settle or reallocate first.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="mb-3">
                    <label class="form-label" for="reason">{{ __('Reason') }}</label>
                    <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" wire:model="reason" rows="3"></textarea>
                    @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:confirm="{{ __('Void this invoice? This posts a reversal journal.') }}">
                        {{ __('Void invoice') }}
                    </button>
                    <a href="{{ route('finance.invoices.show', ['school' => $school, 'invoice' => $invoice]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
