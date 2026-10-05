<div>
    <h4 class="mb-1">{{ __('Utilisation') }}</h4>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Resource') }}</th><th>{{ __('Bookings') }}</th><th>{{ __('Booked hours') }}</th><th>{{ __('Hire revenue') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['resourceCode'] }} — {{ $row['resourceName'] }}</td>
                            <td>{{ $row['bookingCount'] }}</td>
                            <td>{{ number_format($row['bookedHours'], 1) }}</td>
                            <td>{{ number_format($row['hireRevenueMinor'] / 100, 2) }} {{ $row['currency'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No resources.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
