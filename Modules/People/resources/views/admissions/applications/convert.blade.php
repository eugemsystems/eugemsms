<div>
    <h4 class="mb-1">{{ __('Convert to learner') }}</h4>
    <p class="text-body-secondary mb-4">{{ $application->fullName() }} — {{ $application->application_number }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Guardian match preview') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Captured guardian') }}</th><th>{{ __('Outcome on conversion') }}</th></tr></thead>
                        <tbody>
                            @forelse ($guardianMatches as $match)
                                <tr>
                                    <td>{{ $match['name'] }}</td>
                                    <td>
                                        @if ($match['matchedGuardianId'])
                                            <span class="badge text-bg-info">{{ __('Will link to an existing guardian') }}</span>
                                        @else
                                            <span class="badge text-bg-success">{{ __('Will create a new guardian') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-3">{{ __('No guardians were captured on this application.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Deposit credit preview') }}</div>
                <div class="card-body">
                    @if ($application->deposit_receipt_id)
                        <p class="mb-0">{{ __('The acceptance deposit on receipt will convert to a credit on the new learner\'s fee account, reducing their first invoice by the same amount.') }}</p>
                    @else
                        <p class="text-body-secondary mb-0">{{ __('No acceptance deposit was recorded — nothing will be credited.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Preflight') }}</div>
                <div class="card-body">
                    <p class="mb-2">
                        {{ __('Intake capacity:') }}
                        @if ($hasCapacity)
                            <span class="badge text-bg-success">{{ __('Has capacity') }}</span>
                        @else
                            <span class="badge text-bg-danger">{{ __('Full') }}</span>
                        @endif
                    </p>

                    @if (! $hasCapacity)
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="overrideCapacity" wire:model.live="overrideCapacity">
                            <label class="form-check-label" for="overrideCapacity">{{ __('Override capacity (BR-PPL-02-008)') }}</label>
                        </div>
                        @if ($overrideCapacity)
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('overrideReason') is-invalid @enderror" wire:model="overrideReason" placeholder=" ">
                                <label>{{ __('Override reason') }}</label>
                                @error('overrideReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif
                    @endif

                    <button type="button" class="btn btn-primary w-100" wire:click="convert" wire:loading.attr="disabled" wire:confirm="{{ __('Convert this application into a learner record? This cannot be undone.') }}">
                        {{ __('Confirm conversion') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
