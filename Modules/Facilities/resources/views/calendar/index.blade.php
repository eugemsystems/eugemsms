<div>
    <h4 class="mb-1">{{ __('Resource calendar') }}</h4>

    <div class="row g-2 mb-3 align-items-center" style="max-width:30rem">
        <div class="col-4"><button type="button" class="btn btn-outline-secondary btn-sm w-100" wire:click="previousWeek">&laquo; {{ __('Previous') }}</button></div>
        <div class="col-4 text-center">{{ \Illuminate\Support\Carbon::parse($weekStart)->format('d M Y') }}</div>
        <div class="col-4"><button type="button" class="btn btn-outline-secondary btn-sm w-100" wire:click="nextWeek">{{ __('Next') }} &raquo;</button></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Resource') }}</th><th>{{ __('Starts') }}</th><th>{{ __('Ends') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr wire:key="booking-{{ $booking->id }}">
                            <td>{{ $booking->resource->name }}</td>
                            <td>{{ $booking->starts_at->format('D d M H:i') }}</td>
                            <td>{{ $booking->ends_at->format('D d M H:i') }}</td>
                            <td>{{ $booking->purpose }}</td>
                            <td>{{ $booking->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No bookings this week.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
