<div>
    <h4 class="mb-1">{{ __('Access grants') }} — {{ $case->case_reference }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Who can see this case, why, and until when. Granting is itself audited.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('User') }}</th><th>{{ __('Level') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($grants as $grant)
                                <tr wire:key="grant-{{ $grant->id }}">
                                    <td>{{ $grant->user_id }}</td>
                                    <td>{{ $grant->access_level }}</td>
                                    <td class="small">{{ $grant->reason }}</td>
                                    <td class="small">{{ $grant->expires_at?->toDateString() ?? __('No expiry') }}</td>
                                    <td>
                                        @if ($grant->isActive())
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="revoke({{ $grant->id }})">{{ __('Revoke') }}</button>
                                        @else
                                            <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No grants issued.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Grant access') }}</div>
                <div class="card-body">
                    <input type="number" class="form-control mb-2" wire:model="grantUserId" placeholder="{{ __('User id') }}">
                    <select class="form-select mb-2" wire:model="accessLevel">
                        <option value="read">{{ __('Read') }}</option>
                        <option value="contribute">{{ __('Contribute') }}</option>
                        <option value="full">{{ __('Full') }}</option>
                    </select>
                    <textarea class="form-control mb-2" wire:model="reason" placeholder="{{ __('Why this person needs it — required') }}"></textarea>
                    <input type="number" class="form-control mb-2" wire:model="expiryDays" placeholder="{{ __('Expires in days (default from setting)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="grant">{{ __('Grant') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
