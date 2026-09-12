<div>
    @if (session('impersonator_id') !== null)
        <div class="serp-historical-banner d-flex align-items-center justify-content-between gap-2">
            <span>
                <i class="ri ri-spy-line me-1"></i>
                {{ __('You are impersonating :name.', ['name' => $impersonatedName ?? __('another user')]) }}
            </span>
            <button type="button" class="btn btn-sm btn-outline-dark" wire:click="stop" wire:loading.attr="disabled">
                {{ __('Stop impersonating') }}
            </button>
        </div>
    @endif
</div>
