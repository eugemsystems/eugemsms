<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('API clients') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Third-party integrations, each scoped to an explicit list of abilities. Keys are stored hashed and shown once.') }}</p>
    </div>
    @if ($revealedKey)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <div class="flex-grow-1"><strong>{{ __('Copy this now — it is shown only once.') }}</strong> {{ $revealedFor ?? '' }}<br><code class="user-select-all">{{ $revealedKey }}</code></div>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="dismissKey">{{ __('I have copied it') }}</button>
        </div>
    @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Abilities') }}</th><th class="text-end">{{ __('Rate/min') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($clients as $client)
                                <tr wire:key="c-{{ $client->id }}">
                                    <td>{{ $client->name }}<div class="small text-body-secondary">{{ $client->contact_email }}</div></td>
                                    <td class="small">{{ implode(', ', $client->scoped_abilities) }}</td>
                                    <td class="text-end">{{ $client->rate_limit_per_minute }}</td>
                                    <td class="small">{{ $client->last_used_at?->diffForHumans() ?? '—' }}</td>
                                    <td>@if ($client->is_active)<span class="badge text-bg-success">{{ __('Active') }}</span>@else<span class="badge text-bg-secondary">{{ __('Revoked') }}</span>@endif</td>
                                    <td class="text-end text-nowrap">
                                        @if ($client->is_active)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="rotate({{ $client->id }})" wire:confirm="{{ __('Rotate this key? The current key stops working immediately.') }}">{{ __('Rotate key') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="revoke({{ $client->id }})" wire:confirm="{{ __('Revoke this client? This cannot be undone.') }}">{{ __('Revoke') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No API clients yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Issue a client') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control form-control-sm mb-2" wire:model="name" placeholder="{{ __('Name, e.g. Zapier connector') }}">
                    @error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="email" class="form-control form-control-sm mb-2" wire:model="contactEmail" placeholder="{{ __('Contact email (optional)') }}">
                    @error('contactEmail') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="small mb-1">{{ __('Abilities') }}</div>
                    @foreach ($allowedAbilities as $ability)
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="ab-{{ $ability }}" value="{{ $ability }}" wire:model="abilities"><label class="form-check-label small" for="ab-{{ $ability }}">{{ $ability }}</label></div>
                    @endforeach
                    <label class="form-label small mt-2 mb-1">{{ __('Rate limit per minute') }}</label>
                    <input type="number" min="1" max="10000" class="form-control form-control-sm mb-2" wire:model="rateLimit">
                    <textarea class="form-control form-control-sm mb-2" rows="2" wire:model="ipAllowlist" placeholder="{{ __('Allowed IPs / CIDR ranges, optional') }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="issue">{{ __('Issue key') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
