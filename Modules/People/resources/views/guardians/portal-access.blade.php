<div>
    <h4 class="mb-1">{{ __('Parent app access') }}</h4>
    <p class="text-body-secondary small">{{ __('Giving access creates an account for the guardian\'s phone number. They sign in to the app with a code sent to that phone — no password is set.') }}</p>

    <div class="d-flex gap-2 mb-3 align-items-center" style="max-width:36rem">
        <select class="form-select form-select-sm w-auto" wire:model.live="filter">
            <option value="without">{{ __('Without access') }}</option>
            <option value="with">{{ __('With access') }}</option>
        </select>
        <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or phone') }}">
    </div>

    <div class="card"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>{{ __('Guardian') }}</th><th>{{ __('Phone') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($guardians as $guardian)
                <tr wire:key="g-{{ $guardian->id }}">
                    <td>{{ $guardian->last_name }}, {{ $guardian->first_name }}</td>
                    <td>{{ $guardian->primary_phone ?? '—' }}</td>
                    <td class="text-end">
                        @if ($guardian->user_id === null)
                            <button type="button" class="btn btn-sm btn-primary" wire:click="grant({{ $guardian->id }})">{{ __('Give access') }}</button>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="revoke({{ $guardian->id }})" wire:confirm="{{ __('Withdraw access and sign them out of every device?') }}">{{ __('Withdraw') }}</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No guardians to show.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $guardians->links() }}</div>
</div>
