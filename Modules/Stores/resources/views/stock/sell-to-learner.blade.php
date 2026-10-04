<div>
    <h4 class="mb-1">{{ __('Sell to learner') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Uniform, textbooks — raises a charge at sale price and posts cost of sales separately.') }}</p>

    <div class="card" style="max-width: 36rem">
        <div class="card-body">
            <select class="form-select mb-2" wire:model="storeId">
                <option value="">{{ __('Store') }}</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->code }}</option>
                @endforeach
            </select>
            <select class="form-select mb-2" wire:model="itemId">
                <option value="">{{ __('Saleable item') }}</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
            <select class="form-select mb-2" wire:model="studentId">
                <option value="">{{ __('Learner') }}</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                @endforeach
            </select>
            <input type="number" step="1" class="form-control mb-2" wire:model="quantity" placeholder="{{ __('Quantity') }}">
            <button type="button" class="btn btn-primary" wire:click="sell">{{ __('Sell') }}</button>
        </div>
    </div>
</div>
