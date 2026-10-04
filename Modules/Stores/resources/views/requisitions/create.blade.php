<div>
    <h4 class="mb-1">{{ __('New store requisition') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Expense lands on this cost centre when issued, not the store\'s own.') }}</p>

    <div class="card" style="max-width: 50rem">
        <div class="card-body">
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <select class="form-select" wire:model="storeId">
                        <option value="">{{ __('Store') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->code }} — {{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" wire:model="costCentreId">
                        <option value="">{{ __('Requesting cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }} — {{ $cc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" wire:model="requestingDepartmentId">
                        <option value="">{{ __('Department (optional)') }}</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <input type="text" class="form-control mb-2" wire:model="purpose" placeholder="{{ __('Purpose') }}">
            <input type="date" class="form-control mb-3" style="max-width: 16rem" wire:model="requiredBy" placeholder="{{ __('Required by (optional)') }}">

            <table class="table table-sm">
                <thead><tr><th>{{ __('Item') }}</th><th style="width: 8rem">{{ __('Quantity') }}</th><th style="width: 8rem">{{ __('Unit') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($lines as $index => $line)
                        <tr wire:key="req-line-{{ $index }}">
                            <td>
                                <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.item_id">
                                    <option value="">{{ __('Select item') }}</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="lines.{{ $index }}.quantity"></td>
                            <td><input type="text" class="form-control form-control-sm" wire:model="lines.{{ $index }}.unit"></td>
                            <td>
                                @if (count($lines) > 1)
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeLine({{ $index }})"><i class="ri ri-delete-bin-line"></i></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" wire:click="addLine">{{ __('Add line') }}</button>
            <div>
                <button type="button" class="btn btn-primary" wire:click="submit">{{ __('Submit requisition') }}</button>
            </div>
        </div>
    </div>
</div>
