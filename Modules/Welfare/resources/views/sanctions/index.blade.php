<div>
    <h4 class="mb-1">{{ __('Sanctions') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('An overturned sanction is marked overturned, never deleted (BR-BRD-07-009).') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Type') }}</th><th>{{ __('Starts') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($sanctions as $sanction)
                        <tr wire:key="sanction-{{ $sanction->id }}">
                            <td>{{ $sanction->student?->first_name }} {{ $sanction->student?->last_name }}</td>
                            <td>{{ $sanction->sanctionType?->name }}</td>
                            <td>{{ $sanction->starts_on->toDateString() }}</td>
                            <td><span class="badge text-bg-{{ $sanction->status === 'overturned' ? 'warning' : ($sanction->status === 'active' ? 'success' : 'secondary') }}">{{ str_replace('_', ' ', $sanction->status) }}</span></td>
                            <td>
                                @if ($sanction->status === 'pending_approval')
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="approve({{ $sanction->id }})">{{ __('Approve') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No sanctions.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
