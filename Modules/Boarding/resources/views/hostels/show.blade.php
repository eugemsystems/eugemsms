<div>
    <h4 class="mb-1">{{ $hostel->code }} — {{ $hostel->name }}</h4>
    <p class="text-body-secondary mb-4">
        <span class="badge text-bg-{{ $hostel->gender === 'male' ? 'primary' : 'danger' }}">{{ ucfirst($hostel->gender) }}</span>
        {{ __('Capacity') }}: {{ $hostel->capacity }} · {{ __('Occupied') }}: {{ $occupied }} · {{ __('Free') }}: {{ max(0, $hostel->capacity - $occupied) }}
    </p>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('Staff') }}</div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('Housemaster') }}:</strong> {{ $hostel->housemaster?->first_name }} {{ $hostel->housemaster?->last_name }}</p>
                    <p class="mb-1"><strong>{{ __('Matron') }}:</strong> {{ $hostel->matron?->first_name }} {{ $hostel->matron?->last_name }}</p>
                    <p class="mb-0"><strong>{{ __('Building') }}:</strong> {{ $hostel->building ?? '—' }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Recent inspections') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Score') }}</th></tr></thead>
                        <tbody>
                            @forelse ($inspections as $inspection)
                                <tr><td>{{ $inspection->inspection_date->toDateString() }}</td><td>{{ ucfirst($inspection->inspection_type) }}</td><td>{{ $inspection->total_score }} / {{ $inspection->max_score }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No inspections recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Recent damages') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Liability') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($damages as $damage)
                                <tr><td>{{ str_replace('_', ' ', $damage->damage_type) }}</td><td>{{ str_replace('_', ' ', $damage->liability) }}</td><td><span class="badge text-bg-{{ $damage->charge_status === 'disputed' ? 'danger' : 'secondary' }}">{{ str_replace('_', ' ', $damage->charge_status) }}</span></td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No damages reported.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
