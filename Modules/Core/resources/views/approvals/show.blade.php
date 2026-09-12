<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('approvals.queue', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ $request->title }}</h4>
            <p class="text-body-secondary mb-0">{{ \Illuminate\Support\Str::headline($request->approvable_type) }} · {{ __('Requested') }} {{ $request->requested_at->diffForHumans() }}</p>
        </div>
        <span class="badge ms-auto text-bg-{{ match ($request->status) { 'approved' => 'success', 'rejected' => 'danger', 'returned' => 'warning', 'cancelled' => 'secondary', default => 'info' } }} fs-6">
            {{ \Illuminate\Support\Str::headline($request->status) }}
        </span>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">{{ __('Details') }}</h6></div>
                <div class="card-body">
                    @if ($request->summary)
                        <p>{{ $request->summary }}</p>
                    @endif
                    @if ($request->amount_minor !== null)
                        <p class="mb-2"><strong>{{ __('Amount') }}:</strong> {{ number_format($request->amount_minor / 100, 2) }} {{ $request->amount_currency }}</p>
                    @endif
                    <dl class="row mb-0 small">
                        @foreach ($approvable->approvalPayload() as $key => $value)
                            <dt class="col-sm-4">{{ \Illuminate\Support\Str::headline((string) $key) }}</dt>
                            <dd class="col-sm-8">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                        @endforeach
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('History') }}</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse ($history as $entry)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="badge text-bg-{{ match ($entry->action) { 'approved' => 'success', 'rejected' => 'danger', 'returned' => 'warning', 'cancelled' => 'secondary', default => 'info' } }}">
                                        {{ \Illuminate\Support\Str::headline($entry->action) }}
                                    </span>
                                    <strong class="ms-2">{{ $entry->actor?->name }}</strong>
                                    @if ($entry->isDelegated())
                                        <span class="text-body-secondary small">{{ __('on behalf of :name', ['name' => $entry->onBehalfOf?->name]) }}</span>
                                    @endif
                                </div>
                                <span class="text-body-secondary small">{{ $entry->acted_at->format('d M Y H:i') }}</span>
                            </div>
                            @if ($entry->comment)
                                <p class="mb-0 mt-1 small">{{ $entry->comment }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No actions recorded yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            @if ($canAct)
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">{{ __('Your decision') }}</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="comment">
                                {{ __('Comment') }}
                                @if ($currentStep?->requires_comment)
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <textarea class="form-control @error('comment') is-invalid @enderror" id="comment" wire:model="comment" rows="3"></textarea>
                            @error('comment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <button type="button" class="btn btn-success" wire:click="approve" wire:loading.attr="disabled">
                                <i class="ri ri-check-line me-1"></i>{{ __('Approve') }}
                            </button>
                            @if (! $currentStep || $currentStep->can_reject)
                                <button type="button" class="btn btn-outline-danger" wire:click="reject" wire:loading.attr="disabled" wire:confirm="{{ __('Reject this request?') }}">
                                    <i class="ri ri-close-line me-1"></i>{{ __('Reject') }}
                                </button>
                            @endif
                            @if (! $currentStep || $currentStep->can_return)
                                <button type="button" class="btn btn-outline-warning" wire:click="returnToRequester" wire:loading.attr="disabled">
                                    <i class="ri ri-reply-line me-1"></i>{{ __('Return to requester') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if ($canCancel)
                <div class="card">
                    <div class="card-body">
                        <button type="button" class="btn btn-outline-secondary w-100" wire:click="cancel" wire:confirm="{{ __('Cancel this request?') }}">
                            {{ __('Cancel request') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
