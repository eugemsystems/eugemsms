<div>
    <h4 class="mb-1">{{ __('Record a return') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Reverses at the original issue\'s own lot cost — never at today\'s cost.') }}</p>

    <div class="card" style="max-width: 36rem">
        <div class="card-body">
            <select class="form-select mb-2" wire:model.live="requisitionId">
                <option value="">{{ __('Issued requisition') }}</option>
                @foreach ($issued as $req)
                    <option value="{{ $req->id }}">{{ $req->requisition_number }}</option>
                @endforeach
            </select>
            <select class="form-select mb-2" wire:model="itemId">
                <option value="">{{ __('Item') }}</option>
                @foreach ($lines as $line)
                    <option value="{{ $line->item_id }}">{{ $line->item->name }} ({{ __('issued') }}: {{ $line->quantity_issued }})</option>
                @endforeach
            </select>
            <input type="number" step="0.0001" class="form-control mb-2" wire:model="returnQuantity" placeholder="{{ __('Return quantity') }}">
            <button type="button" class="btn btn-primary" wire:click="recordReturn">{{ __('Record return') }}</button>
        </div>
    </div>
</div>
