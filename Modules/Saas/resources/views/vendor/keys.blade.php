<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Licence keys') }}</h4><p class="text-body-secondary small mb-0">{{ __('On-premise licence keys, bound to the subscription’s own tenant. An offline installation keeps running for its grace days.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Key') }}</th><th>{{ __('Tenant') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Offline grace') }}</th><th>{{ __('Last validated') }}</th></tr></thead>
                <tbody>
                    @forelse ($keys as $key)
                        <tr wire:key="k-{{ $key->id }}"><td><code>{{ $key->key_value }}</code></td><td>{{ $key->tenant?->name }}</td><td>{{ __(ucfirst($key->status)) }}</td><td class="text-end">{{ $key->offline_grace_days }}d</td><td class="small">{{ $key->last_validated_at?->diffForHumans() ?? '—' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No licence keys.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Issue a key') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="subscriptionId"><option value="">{{ __('Subscription…') }}</option>@foreach ($subscriptions as $subscription) <option value="{{ $subscription->id }}">{{ $subscription->tenant?->name }} · {{ $subscription->status }}</option> @endforeach</select>
            @error('subscriptionId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <label class="form-label small mb-1">{{ __('Offline grace (days)') }}</label>
            <input type="number" min="0" max="90" class="form-control form-control-sm mb-2" wire:model="offlineGraceDays">
            @error('offlineGraceDays') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="issue">{{ __('Issue') }}</button>
        </div></div></div>
    </div>
</div>
