<div>
    <h4 class="mb-1">{{ __('SMS sender IDs') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('A pending, rejected or expired sender ID is never used. Sends route to a fallback sender ID or another gateway instead.') }}</p>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Sender ID') }}</th><th>{{ __('Network') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($senderIds as $sender)
                                <tr wire:key="sid-{{ $sender->id }}">
                                    <td>{{ $sender->sender_id }}</td>
                                    <td>{{ $sender->network ?? __('all') }}</td>
                                    <td>
                                        <span class="badge {{ $sender->status === 'approved' ? 'bg-label-success' : ($sender->status === 'pending' ? 'bg-label-warning' : 'bg-label-danger') }}">{{ $sender->status }}</span>
                                        @if ($sender->rejection_reason) <div class="small text-danger">{{ $sender->rejection_reason }}</div> @endif
                                    </td>
                                    <td class="small">{{ $sender->expires_on?->toDateString() ?? '—' }}</td>
                                    <td class="text-end">
                                        @if ($sender->status === 'pending')
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="recordStatus({{ $sender->id }}, 'approved')">{{ __('Approved') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="recordStatus({{ $sender->id }}, 'rejected', 'Rejected by network')">{{ __('Rejected') }}</button>
                                        @elseif ($sender->status === 'approved')
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="recordStatus({{ $sender->id }}, 'expired')">{{ __('Mark expired') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No sender IDs registered yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Register sender ID') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="gatewayId">
                        <option value="">{{ __('SMS gateway…') }}</option>
                        @foreach ($smsGateways as $gateway)
                            <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                        @endforeach
                    </select>
                    @error('gatewayId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="senderId" maxlength="11" placeholder="{{ __('Sender ID (max 11)') }}">
                    @error('senderId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="network" placeholder="{{ __('Network, e.g. Econet (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="registrationReference" placeholder="{{ __('Registration reference (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="register">{{ __('Register') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
