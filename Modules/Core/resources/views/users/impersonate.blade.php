<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Impersonation console') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('For platform support use only. Every session is time-boxed, requires a reason and a support ticket, and is fully audited.') }}</p>
    </div>

    @if ($this->isCurrentlyImpersonating())
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
            <i class="ri ri-spy-line fs-4"></i>
            <div>{{ __('You are currently impersonating another user. End that session before starting a new one.') }}</div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('Start a new session') }}</h5>
                    <form wire:submit="start">
                        <div class="mb-3">
                            <label for="userSearch" class="form-label">{{ __('Find a user') }}</label>
                            <input
                                type="search"
                                id="userSearch"
                                class="form-control"
                                wire:model.live.debounce.400ms="userSearch"
                                placeholder="{{ __('Search by name, email, phone, or username…') }}"
                            >
                        </div>

                        <div class="mb-3">
                            <label for="targetUserId" class="form-label">{{ __('Target user') }}</label>
                            <select id="targetUserId" class="form-select @error('targetUserId') is-invalid @enderror" wire:model="targetUserId">
                                <option value="">{{ __('Select a user…') }}</option>
                                @foreach ($this->candidateUsers as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }} — {{ $candidate->email ?? $candidate->phone ?? $candidate->username }}</option>
                                @endforeach
                            </select>
                            @error('targetUserId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">{{ __('Reason') }}</label>
                            <textarea id="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" wire:model="reason" placeholder="{{ __('Why is this session necessary?') }}"></textarea>
                            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="ticketReference" class="form-label">{{ __('Support ticket reference') }}</label>
                            <input type="text" id="ticketReference" class="form-control @error('ticketReference') is-invalid @enderror" wire:model="ticketReference" placeholder="{{ __('e.g. SUP-4821') }}">
                            @error('ticketReference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        @if (app()->environment('production'))
                            <div class="mb-3">
                                <label for="consentReference" class="form-label">{{ __('Customer consent reference') }}</label>
                                <input type="text" id="consentReference" class="form-control @error('consentReference') is-invalid @enderror" wire:model="consentReference" placeholder="{{ __('Required in production') }}">
                                @error('consentReference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">{{ __('Production impersonation requires a recorded customer consent reference (BR-CORE-05-017).') }}</div>
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" @disabled($this->isCurrentlyImpersonating())>
                            <i class="ri ri-spy-line me-1"></i>{{ __('Start impersonating') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Your past sessions') }}</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('User') }}</th>
                                <th>{{ __('Reason') }}</th>
                                <th>{{ __('Ticket') }}</th>
                                <th>{{ __('Started') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->pastSessions as $session)
                                <tr wire:key="impersonation-session-{{ $session->id }}">
                                    <td>{{ $session->impersonated->name }}</td>
                                    <td class="text-truncate" style="max-width: 16rem;" title="{{ $session->reason }}">{{ $session->reason }}</td>
                                    <td>{{ $session->ticket_reference }}</td>
                                    <td>{{ $session->started_at->format('d M Y H:i') }}</td>
                                    <td>
                                        @if ($session->isActive())
                                            <span class="badge text-bg-warning">{{ __('Active') }}</span>
                                        @elseif ($session->ended_at !== null)
                                            <span class="badge text-bg-secondary">{{ __('Ended') }}</span>
                                        @else
                                            <span class="badge text-bg-danger">{{ __('Expired') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($session->isActive())
                                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="end({{ $session->id }})" wire:confirm="{{ __('End this impersonation session?') }}" title="{{ __('End session') }}" aria-label="{{ __('End session') }}">
                                                <i class="icon-base ri ri-stop-circle-line icon-22px"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No impersonation sessions yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
