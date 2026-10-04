<div>
    <h4 class="mb-1">{{ __('Missing laundry items') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Must be resolved or charged before the owning cycle can reconcile.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Hostel') }}</th><th>{{ __('Out') }}</th><th>{{ __('Back') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($discrepancies as $item)
                        <tr wire:key="disc-{{ $item->id }}">
                            <td>{{ $item->student->first_name }} {{ $item->student->last_name }}</td>
                            <td>{{ $item->cycle->hostel->code }}</td>
                            <td>{{ $item->items_out }}</td>
                            <td>{{ $item->items_back }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-success" wire:click="resolveWithoutCharge({{ $item->id }})">{{ __('Found') }}</button>
                                <select class="form-select form-select-sm d-inline-block" style="width: 140px" wire:model="feeComponentId">
                                    <option value="">{{ __('Fee') }}</option>
                                    @foreach ($feeComponents as $component)
                                        <option value="{{ $component->id }}">{{ $component->name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" class="form-control form-control-sm d-inline-block" style="width: 100px" wire:model="chargeAmountMinor" placeholder="{{ __('Amount') }}">
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="resolveWithCharge({{ $item->id }})">{{ __('Charge') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No open discrepancies.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
