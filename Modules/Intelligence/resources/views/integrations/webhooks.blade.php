<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Webhook subscriptions') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Signed outbound events to a public https endpoint. A subscription that keeps failing switches itself off.') }}</p>
    </div>
    @if ($revealedSecret)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <div class="flex-grow-1"><strong>{{ __('Copy this now — it is shown only once.') }}</strong> <br><code class="user-select-all">{{ $revealedSecret }}</code></div>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="dismissSecret">{{ __('I have copied it') }}</button>
        </div>
    @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Target') }}</th><th>{{ __('Events') }}</th><th class="text-end">{{ __('Failures') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($subscriptions as $subscription)
                                <tr wire:key="s-{{ $subscription->id }}">
                                    <td>{{ $clientNames[$subscription->client_id] ?? '—' }}</td>
                                    <td class="small text-break">{{ $subscription->target_url }}</td>
                                    <td class="small">{{ implode(', ', $subscription->event_names) }}</td>
                                    <td class="text-end">{{ $subscription->consecutive_failures }}</td>
                                    <td>@if ($subscription->is_active)<span class="badge text-bg-success">{{ __('Active') }}</span>@else<span class="badge text-bg-secondary">{{ __('Disabled') }}</span>@endif</td>
                                    <td class="text-end">
                                        @if ($subscription->is_active)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="setActive({{ $subscription->id }}, false)">{{ __('Disable') }}</button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="setActive({{ $subscription->id }}, true)">{{ __('Enable') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No subscriptions yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('New subscription') }}</div>
                <div class="card-body">
                    <select class="form-select form-select-sm mb-2" wire:model="clientId">
                        <option value="">{{ __('API client…') }}</option>
                        @foreach ($clients as $client) <option value="{{ $client->id }}">{{ $client->name }}</option> @endforeach
                    </select>
                    @error('clientId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <textarea class="form-control form-control-sm mb-2" rows="2" wire:model="events" placeholder="{{ __('Events, e.g. InvoiceIssued, ReceiptVoided') }}"></textarea>
                    @error('events') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="url" class="form-control form-control-sm mb-2" wire:model="targetUrl" placeholder="https://example.com/hook">
                    @error('targetUrl') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Subscribe') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
