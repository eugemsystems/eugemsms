<div>
    <h4 class="mb-1">{{ __('Maintenance reports') }}</h4>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a href="javascript:void(0)" class="nav-link {{ $tab === 'sla' ? 'active' : '' }}" wire:click="$set('tab', 'sla')">{{ __('SLA') }}</a></li>
        <li class="nav-item"><a href="javascript:void(0)" class="nav-link {{ $tab === 'cost' ? 'active' : '' }}" wire:click="$set('tab', 'cost')">{{ __('Cost analysis') }}</a></li>
    </ul>

    @if ($tab === 'sla')
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Team') }}</th><th>{{ __('Completed') }}</th><th>{{ __('Met SLA') }}</th><th>{{ __('% met') }}</th></tr></thead>
                    <tbody>
                        @forelse ($slaByTeam as $team => $row)
                            <tr>
                                <td>{{ $team }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>{{ $row['met'] }}</td>
                                <td>{{ $row['total'] > 0 ? number_format($row['met'] / $row['total'] * 100, 1) : '—' }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No completed work orders with an SLA target yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Cost by asset') }}</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($costByAsset as $asset => $cost)
                            <li class="list-group-item d-flex justify-content-between"><span>{{ $asset }}</span><span>{{ number_format($cost / 100, 2) }}</span></li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No data.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Cost by cost centre') }}</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($costByCostCentre as $cc => $cost)
                            <li class="list-group-item d-flex justify-content-between"><span>{{ $cc }}</span><span>{{ number_format($cost / 100, 2) }}</span></li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No data.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>
