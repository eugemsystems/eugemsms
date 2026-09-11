<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __(':type period — Term :number', ['type' => ucfirst($type->value), 'number' => $term->number]) }}</h4>
            <p class="text-body-secondary mb-0">{{ $term->name }}</p>
        </div>
        <a href="{{ route('sessions.terms.show', [$school, $term]) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to term') }}
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body d-flex align-items-center gap-3">
            <span class="text-body-secondary">{{ __('Current state:') }}</span>
            <span class="badge fs-6 text-bg-primary text-capitalize">{{ str_replace('_', ' ', $currentState->value) }}</span>
        </div>
    </div>

    @if ($checklist !== null && ! $checklist->passesBlocking())
        <div class="alert alert-warning">
            <div class="fw-medium mb-1">{{ __('This period cannot be locked yet:') }}</div>
            <ul class="mb-0">
                @foreach ($checklist->failures() as $failure)
                    <li>{{ $failure->label }}@if ($failure->message) — {{ $failure->message }} @endif</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">{{ __('Actions') }}</h6>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
            @forelse ($transitions as $target)
                @php
                    $blockedByChecklist = $target->value === 'locked' && $checklist !== null && ! $checklist->passesBlocking();
                @endphp
                <button
                    type="button"
                    class="btn btn-primary"
                    wire:click="transitionTo('{{ $target->value }}')"
                    wire:loading.attr="disabled"
                    @disabled($blockedByChecklist)
                >
                    {{ __('Move to :state', ['state' => str_replace('_', ' ', $target->value)]) }}
                </button>
            @empty
                <span class="text-body-secondary">{{ __('No further transitions available from here.') }}</span>
            @endforelse
        </div>
    </div>

    @if ($currentState->value === 'locked')
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">{{ __('Reopen') }}</h6>
            </div>
            <div class="card-body">
                @if ($pendingRequest)
                    <p>
                        {{ __('Reopen requested by :name on :date.', ['name' => $pendingRequest->requester->name, 'date' => $pendingRequest->requested_at->format('d M Y H:i')]) }}
                    </p>
                    <p class="text-body-secondary">{{ $pendingRequest->reason }}</p>

                    @if ($pendingRequest->requested_by === auth()->id())
                        <p class="text-body-secondary fst-italic mb-0">{{ __('Waiting for a different user to approve this request.') }}</p>
                    @else
                        <button type="button" class="btn btn-warning" wire:click="approveReopen({{ $pendingRequest->id }})" wire:loading.attr="disabled">
                            {{ __('Approve reopen') }}
                        </button>
                    @endif
                @else
                    <p class="text-body-secondary">{{ __('A locked period can only be reopened after a different user approves the request.') }}</p>
                    <button type="button" class="btn btn-outline-warning" wire:click="$set('showRequestModal', true)">
                        {{ __('Request reopen') }}
                    </button>
                @endif
            </div>
        </div>
    @endif

    @if ($showReasonModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="confirmReasonedTransition">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Reopen this soft-closed period') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showReasonModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline">
                                <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" wire:model="reason" style="height: 7rem;" placeholder=" "></textarea>
                                <label for="reason">{{ __('Reason (minimum 20 characters)') }}</label>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showReasonModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Confirm') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showRequestModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="requestReopen">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Request reopen') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showRequestModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline">
                                <textarea class="form-control @error('reason') is-invalid @enderror" id="request-reason" wire:model="requestReason" style="height: 7rem;" placeholder=" "></textarea>
                                <label for="request-reason">{{ __('Reason (minimum 20 characters)') }}</label>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showRequestModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Submit request') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
