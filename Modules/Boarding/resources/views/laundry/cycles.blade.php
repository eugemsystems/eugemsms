<div>
    <h4 class="mb-1">{{ __('Laundry cycles') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Reconciliation refuses while any learner\'s discrepancy is still open.') }}</p>

    <div class="card mb-4">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-4">
                <select class="form-select" wire:model="hostelId">
                    <option value="">{{ __('Hostel') }}</option>
                    @foreach ($hostels as $hostel)
                        <option value="{{ $hostel->id }}">{{ $hostel->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="date" class="form-control" wire:model="cycleDate"></div>
            <div class="col-md-2"><button type="button" class="btn btn-primary w-100" wire:click="createCycle">{{ __('Create cycle') }}</button></div>
        </div>
    </div>

    @if ($cycle)
        <div class="card mb-4">
            <div class="card-header">{{ __('Cycle') }} #{{ $cycle->id }} — {{ ucfirst($cycle->status) }}</div>
            <div class="card-body">
                @if ($cycle->status === 'scheduled')
                    <p class="text-body-secondary small">{{ __('Items out per learner, then collect.') }}</p>
                    @foreach ($roster as $allocation)
                        <div class="row g-2 mb-1 align-items-center">
                            <div class="col-6">{{ $allocation->student->first_name }} {{ $allocation->student->last_name }}</div>
                            <div class="col-3"><input type="number" class="form-control form-control-sm" wire:model="itemsOut.{{ $allocation->student_id }}" placeholder="{{ __('Items out') }}"></div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="collect">{{ __('Collect') }}</button>
                @elseif ($cycle->status === 'collected')
                    <p class="text-body-secondary small">{{ __('Items back per learner, then return.') }}</p>
                    @foreach ($cycle->items as $item)
                        <div class="row g-2 mb-1 align-items-center">
                            <div class="col-6">{{ $item->student->first_name }} {{ $item->student->last_name }} ({{ __('out') }}: {{ $item->items_out }})</div>
                            <div class="col-3"><input type="number" class="form-control form-control-sm" wire:model="itemsBack.{{ $item->student_id }}" placeholder="{{ __('Items back') }}"></div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="recordReturn">{{ __('Record return') }}</button>
                @elseif ($cycle->status === 'returned')
                    <p class="mb-2">{{ __('Collected') }}: {{ $cycle->items_collected }} · {{ __('Returned') }}: {{ $cycle->items_returned }} · <span class="text-danger">{{ __('Missing') }}: {{ $cycle->items_missing }}</span></p>
                    <button type="button" class="btn btn-sm btn-success" wire:click="reconcile">{{ __('Reconcile') }}</button>
                @else
                    <span class="badge text-bg-success">{{ __('Reconciled') }}</span>
                @endif
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">{{ __('Recent cycles') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Hostel') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentCycles as $recent)
                        <tr><td>{{ $recent->hostel->code }}</td><td>{{ $recent->cycle_date->toDateString() }}</td><td>{{ ucfirst($recent->status) }}</td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('activeCycleId', {{ $recent->id }})">{{ __('Open') }}</button></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No cycles yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
