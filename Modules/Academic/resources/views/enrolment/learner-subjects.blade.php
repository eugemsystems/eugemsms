<div>
    <h4 class="mb-1">{{ __('Subject enrolment') }}</h4>
    <p class="text-body-secondary mb-4">{{ $student->first_name }} {{ $student->last_name }} — {{ ucfirst(strtolower(str_replace('_', ' ', $student->enrolment_type))) }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Current subjects') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Since') }}</th><th>{{ __('Billing status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($activeEnrolments as $enrolment)
                                <tr wire:key="enrolment-{{ $enrolment->id }}">
                                    <td>{{ $enrolment->subject?->name }}</td>
                                    <td>{{ str_replace('_', ' ', $enrolment->enrolment_reason) }}</td>
                                    <td>{{ $enrolment->effective_from->format('d M Y') }}</td>
                                    <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $enrolment->billing_status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No active subjects this term.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Dated history') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Change') }}</th><th>{{ __('Effective') }}</th><th>{{ __('Proration') }}</th><th>{{ __('Billing') }}</th></tr></thead>
                        <tbody>
                            @forelse ($history as $change)
                                <tr wire:key="change-{{ $change->id }}">
                                    <td>{{ $change->subject?->name }}</td>
                                    <td>{{ ucfirst($change->change_type) }}</td>
                                    <td>{{ $change->effective_from->format('d M Y') }}</td>
                                    <td>{{ $change->proration_factor !== null ? number_format((float) $change->proration_factor * 100, 1).'%' : '—' }}</td>
                                    <td>{{ $change->billing_event_result ?? __('pending') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No changes recorded this term.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('Add subject') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('addSubjectId') is-invalid @enderror" wire:model.live="addSubjectId">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Subject') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="date" class="form-control @error('addEffectiveFrom') is-invalid @enderror" wire:model="addEffectiveFrom">
                                <label>{{ __('Effective from') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" wire:model="addReason" placeholder=" ">
                                <label>{{ __('Reason (if backdated)') }}</label>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3" wire:click="previewFeeImpact">{{ __('Preview fee impact') }}</button>

                    @if ($feePreview !== null)
                        <div class="alert alert-info mt-2 mb-0">
                            @if ($feePreview['amountMinor'] !== null)
                                {{ __('New termly total:') }} <strong>{{ number_format($feePreview['amountMinor'] / 100, 2) }} {{ $feePreview['currency'] }}</strong>
                            @else
                                {{ __('No fee structure resolves for this learner — no change expected.') }}
                            @endif
                        </div>
                    @endif

                    @if ($pendingWarningMessage !== null)
                        <div class="alert alert-warning mt-2">
                            {{ $pendingWarningMessage }}
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" wire:model="acknowledgeWarnings" id="ack-warnings">
                                <label class="form-check-label" for="ack-warnings">{{ __('I acknowledge this warning and want to proceed.') }}</label>
                            </div>
                        </div>
                    @endif

                    <button type="button" class="btn btn-primary mt-3" wire:click="addSubject">{{ __('Add subject') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Drop subject') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('dropSubjectId') is-invalid @enderror" wire:model="dropSubjectId">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($activeEnrolments as $enrolment)
                                        <option value="{{ $enrolment->subject_id }}">{{ $enrolment->subject?->name }}</option>
                                    @endforeach
                                </select>
                                <label>{{ __('Subject') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="date" class="form-control @error('dropEffectiveTo') is-invalid @enderror" wire:model="dropEffectiveTo">
                                <label>{{ __('Effective to') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('dropReason') is-invalid @enderror" wire:model="dropReason" placeholder=" ">
                                <label>{{ __('Reason') }}</label>
                                @error('dropReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-danger mt-3" wire:click="dropSubject" wire:confirm="{{ __('Drop this subject?') }}">{{ __('Drop subject') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
