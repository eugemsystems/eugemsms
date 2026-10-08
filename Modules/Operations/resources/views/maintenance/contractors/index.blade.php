<div>
    <h4 class="mb-1">{{ __('Contractor management') }}</h4>
    <p class="text-body-secondary small mb-4">{{ __('Flag a supplier as a contractor to make them available on a work order\'s action bar, and track their cost and SLA history here.') }}</p>

    <div class="card mb-4">
        <div class="card-header">{{ __('Flag a supplier') }}</div>
        <div class="card-body">
            <input type="text" class="form-control form-control-sm" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search suppliers by name or code…') }}">
            @if ($searchResults->isNotEmpty())
                <div class="list-group mt-2">
                    @foreach ($searchResults as $supplier)
                        <div class="list-group-item d-flex justify-content-between align-items-center" wire:key="search-{{ $supplier->id }}">
                            <span>{{ $supplier->name }} <span class="text-body-secondary small">{{ $supplier->code }}</span></span>
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="toggle({{ $supplier->id }})">{{ __('Flag as contractor') }}</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Contractors') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Work orders') }}</th><th>{{ __('Total cost') }}</th><th>{{ __('SLA met') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($contractors as $contractor)
                        @php($row = $history->get($contractor->id, ['work_orders' => 0, 'total_cost_minor' => 0, 'sla_met' => 0, 'sla_total' => 0]))
                        <tr wire:key="contractor-{{ $contractor->id }}">
                            <td>{{ $contractor->name }} <span class="text-body-secondary small">{{ $contractor->code }}</span></td>
                            <td>{{ $row['work_orders'] }}</td>
                            <td>{{ number_format($row['total_cost_minor'] / 100, 2) }} {{ $contractor->preferred_currency }}</td>
                            <td>{{ $row['sla_total'] > 0 ? number_format($row['sla_met'] / $row['sla_total'] * 100, 1).'%' : '—' }}</td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" wire:click="toggle({{ $contractor->id }})" wire:confirm="{{ __('Remove as a contractor?') }}">{{ __('Remove') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No suppliers are flagged as contractors yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
