<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Roll over a term') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('For :school.', ['school' => $school->name]) }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('sessions.rollover.history', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-history-line me-1"></i>{{ __('History') }}
            </a>
            <a href="{{ route('sessions.years', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to years') }}
            </a>
        </div>
    </div>

    @if ($activeRollover)
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">
                    {{ $activeRollover->fromTerm->name }} &rarr; {{ $activeRollover->toTerm->name }}
                </h6>
                <span @class([
                    'badge text-capitalize',
                    'text-bg-danger' => $activeRollover->status->value === 'failed',
                    'text-bg-warning' => $activeRollover->status->value !== 'failed',
                ])>{{ str_replace('_', ' ', $activeRollover->status->value) }}</span>
            </div>
            <div class="card-body">
                @if ($activeRollover->validation_report)
                    <h6>{{ __('Validation report') }}</h6>
                    <pre class="bg-light p-2 small">{{ json_encode($activeRollover->validation_report, JSON_PRETTY_PRINT) }}</pre>
                @endif

                <div class="d-flex gap-2 mt-3">
                    @if ($activeRollover->status->value === 'pending')
                        <button type="button" class="btn btn-primary" wire:click="execute({{ $activeRollover->id }})" wire:loading.attr="disabled" wire:confirm="{{ __('Run this rollover now?') }}">
                            <i class="ri ri-play-line me-1"></i>{{ __('Execute rollover') }}
                        </button>
                    @endif
                    @if ($activeRollover->status->value === 'failed')
                        <button type="button" class="btn btn-outline-danger" wire:click="$set('showRollbackModal', true)">
                            {{ __('Roll back') }}
                        </button>
                    @endif
                    @if (in_array($activeRollover->status->value, ['validating', 'running'], true))
                        <span class="text-body-secondary fst-italic">{{ __('In progress — refresh in a moment.') }}</span>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="card" style="max-width: 40rem;">
            <div class="card-body">
                <form wire:submit="initiate">
                    <div class="form-floating form-floating-outline mb-3">
                        <select class="form-select @error('fromTermId') is-invalid @enderror" id="from-term" wire:model="fromTermId">
                            <option value="">{{ __('Select a term…') }}</option>
                            @foreach ($terms as $term)
                                <option value="{{ $term->id }}">{{ $term->academicYear->name }} — {{ $term->name }}</option>
                            @endforeach
                        </select>
                        <label for="from-term">{{ __('Closing term (from)') }}</label>
                        @error('fromTermId')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-floating form-floating-outline mb-4">
                        <select class="form-select @error('toTermId') is-invalid @enderror" id="to-term" wire:model="toTermId">
                            <option value="">{{ __('Select a term…') }}</option>
                            @foreach ($terms as $term)
                                <option value="{{ $term->id }}">{{ $term->academicYear->name }} — {{ $term->name }}</option>
                            @endforeach
                        </select>
                        <label for="to-term">{{ __('Opening term (to)') }}</label>
                        @error('toTermId')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        {{ __('Start validation') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if ($showRollbackModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="rollback({{ $activeRollover->id }})">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Roll back this rollover') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showRollbackModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline">
                                <textarea class="form-control @error('reason') is-invalid @enderror" id="rollback-reason" wire:model="rollbackReason" style="height: 7rem;" placeholder=" "></textarea>
                                <label for="rollback-reason">{{ __('Reason') }}</label>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showRollbackModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">{{ __('Roll back') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
