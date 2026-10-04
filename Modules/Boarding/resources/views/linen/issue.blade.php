<div>
    <h4 class="mb-1">{{ __('Issue & return') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Damage/loss charges only ever reach FIN-02 after approval here.') }}</p>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('Learner') }}</div>
                <div class="card-body">
                    <select class="form-select" wire:model.live="studentId">
                        <option value="">{{ __('Select learner') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Issue item') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="issuableItemId">
                        <option value="">{{ __('Item') }}</option>
                        @foreach ($issuableItems as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="conditionAtIssue">
                        <option value="new">{{ __('New') }}</option>
                        <option value="good">{{ __('Good') }}</option>
                        <option value="fair">{{ __('Fair') }}</option>
                        <option value="poor">{{ __('Poor') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="quantity" min="1" placeholder="{{ __('Quantity') }}">
                    <input type="text" class="form-control mb-2" wire:model="tagReference" placeholder="{{ __('Tag reference (if required)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="issue">{{ __('Issue') }}</button>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Issued items') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($issuedItems as $issued)
                                <tr wire:key="issued-{{ $issued->id }}">
                                    <td>{{ $issued->issuableItem->name }}</td>
                                    <td>{{ ucfirst($issued->condition_at_issue) }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($issued->status) }}</span></td>
                                    <td class="text-end text-nowrap">
                                        @if ($issued->status === 'issued')
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="markReturned({{ $issued->id }}, 'good')">{{ __('Return (good)') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" wire:click="markReturned({{ $issued->id }}, 'poor')">{{ __('Return (poor)') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reportLost({{ $issued->id }})">{{ __('Lost') }}</button>
                                        @elseif (in_array($issued->status, ['damaged', 'lost'], true))
                                            <select class="form-select form-select-sm d-inline-block" style="width: 160px" wire:model="approveFeeComponentId">
                                                <option value="">{{ __('Fee component') }}</option>
                                                @foreach ($feeComponents as $component)
                                                    <option value="{{ $component->id }}">{{ $component->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($issued->status === 'damaged')
                                                <button type="button" class="btn btn-sm btn-success" wire:click="approveDamageCharge({{ $issued->id }})">{{ __('Approve') }}</button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-success" wire:click="approveLostCharge({{ $issued->id }})">{{ __('Approve') }}</button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No items for this learner.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
