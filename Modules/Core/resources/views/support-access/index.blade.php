<div>
    <h4 class="mb-1">{{ __('Support access') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Let our support team look at the system as one of your users, for one support ticket. They can only view — nothing can be changed in the session — and you can withdraw access at any time.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            @forelse ($grants as $grant)
                <div class="card mb-3" wire:key="grant-{{ $grant->id }}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ $grant->ticket_reference }}
                            @if ($grant->revoked_at) <span class="badge text-bg-secondary">{{ __('withdrawn') }}</span>
                            @elseif ($grant->expires_at->isPast()) <span class="badge text-bg-secondary">{{ __('expired') }}</span>
                            @else <span class="badge text-bg-success">{{ __('active until :time', ['time' => $grant->expires_at->format('Y-m-d H:i')]) }}</span> @endif
                        </span>
                        @if ($grant->isActive())
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="revoke({{ $grant->id }})" wire:confirm="{{ __('Withdraw this access and end any open session?') }}">{{ __('Withdraw') }}</button>
                        @endif
                    </div>
                    <div class="card-body small">
                        <div>{{ $grant->reason }}</div>
                        <div class="text-body-secondary">{{ __('Granted by :name on :date', ['name' => $grant->grantedBy->name, 'date' => $grant->created_at->format('Y-m-d H:i')]) }}</div>
                        @foreach ($sessions->get($grant->id) ?? [] as $session)
                            <div class="border-top mt-2 pt-2">{{ __(':who viewed as :user from :start', ['who' => $session->impersonator->name, 'user' => $session->impersonated->name, 'start' => $session->started_at->format('Y-m-d H:i')]) }}@if ($session->ended_at) — {{ __('ended :end', ['end' => $session->ended_at->format('H:i')]) }}@endif</div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-body-secondary">{{ __('No support access has been granted.') }}</div>
            @endforelse
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Grant access') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="ticketReference" placeholder="{{ __('Support ticket reference') }}">
                    @error('ticketReference') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <textarea class="form-control mb-2" rows="3" wire:model="reason" placeholder="{{ __('What should support look at?') }}"></textarea>
                    @error('reason') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <label class="form-label small mb-0">{{ __('Hours of access (1–:max)', ['max' => $maxHours]) }}</label>
                    <input type="number" min="1" max="{{ $maxHours }}" class="form-control mb-2" wire:model="durationHours">
                    @error('durationHours') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @error('tenant') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="grant">{{ __('Grant access') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
