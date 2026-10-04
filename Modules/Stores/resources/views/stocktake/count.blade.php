<div>
    <h4 class="mb-1">{{ __('Stock take — count') }}</h4>
    <p class="text-body-secondary mb-4">⭐ {{ __('Blind by default — the system quantity is never shown here, not even greyed out.') }}</p>

    <div class="card mb-4" style="max-width: 36rem">
        <div class="card-header">{{ __('Start a stock take') }}</div>
        <div class="card-body">
            <select class="form-select mb-2" wire:model="storeId">
                <option value="">{{ __('Store') }}</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->code }} — {{ $store->name }}</option>
                @endforeach
            </select>
            <select class="form-select mb-2" wire:model="takeType">
                <option value="full">{{ __('Full') }}</option>
                <option value="cycle">{{ __('Cycle') }}</option>
                <option value="spot">{{ __('Spot') }}</option>
            </select>
            <button type="button" class="btn btn-primary" wire:click="createTake">{{ __('Start count') }}</button>
        </div>
    </div>

    @if ($activeTakeId !== null)
        <div class="card">
            <div class="card-header">{{ __('Count sheet') }} — #{{ $activeTakeId }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Item') }}</th><th style="width: 10rem">{{ __('Counted quantity') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($sheet as $line)
                            <tr wire:key="sheet-{{ $line->lineId }}">
                                <td>{{ $line->itemName }} ({{ $line->baseUnit }})</td>
                                <td><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="counts.{{ $line->lineId }}"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-primary" wire:click="submitCount({{ $line->lineId }})">{{ __('Submit') }}</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($openTakes->isNotEmpty())
        <p class="text-body-secondary">{{ __('Open takes:') }}
            @foreach ($openTakes as $take)
                <button type="button" class="btn btn-sm btn-outline-secondary ms-1" wire:click="$set('activeTakeId', {{ $take->id }})">{{ $take->take_number }}</button>
            @endforeach
        </p>
    @endif
</div>
