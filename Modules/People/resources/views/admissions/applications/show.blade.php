<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $application->fullName() }}</h4>
            <p class="text-body-secondary mb-0">
                {{ $application->application_number }} ·
                <span class="badge text-bg-secondary">{{ str_replace('_', ' ', ucfirst($application->status)) }}</span>
                · {{ $application->intake?->name }} — {{ $application->requestedGradeLevel?->name }}
            </p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Application detail') }}</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('Date of birth') }}</dt><dd class="col-7">{{ $application->date_of_birth->format('d M Y') }}</dd>
                        <dt class="col-5">{{ __('Gender') }}</dt><dd class="col-7">{{ ucfirst($application->gender) }}</dd>
                        <dt class="col-5">{{ __('Enrolment type') }}</dt><dd class="col-7">{{ $application->requested_enrolment_type }}</dd>
                        <dt class="col-5">{{ __('Residency') }}</dt><dd class="col-7">{{ $application->requested_residency }}</dd>
                        <dt class="col-5">{{ __('Priority score') }}</dt><dd class="col-7">{{ $application->priority_score ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Sibling at school') }}</dt><dd class="col-7">{{ $application->has_sibling_at_school ? __('Yes') : __('No') }}</dd>
                        <dt class="col-5">{{ __('Submitted') }}</dt><dd class="col-7">{{ $application->submitted_at?->format('d M Y') ?? '—' }}</dd>
                        @if ($application->offer_made_at)
                            <dt class="col-5">{{ __('Offer made / expires') }}</dt>
                            <dd class="col-7">{{ $application->offer_made_at->format('d M Y') }} / {{ $application->offer_expires_at?->format('d M Y') }}</dd>
                        @endif
                        @if ($application->declined_reason)
                            <dt class="col-5">{{ __('Declined reason') }}</dt><dd class="col-7">{{ $application->declined_reason }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Guardians captured at application') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Roles') }}</th></tr></thead>
                        <tbody>
                            @forelse ($application->guardians as $guardian)
                                <tr>
                                    <td>{{ $guardian->organisation_name ?? trim("{$guardian->first_name} {$guardian->last_name}") }}</td>
                                    <td>{{ ucfirst($guardian->relationship) }}</td>
                                    <td>{{ $guardian->primary_phone ?? '—' }}</td>
                                    <td>
                                        @if ($guardian->is_primary_contact) <span class="badge text-bg-primary">{{ __('Primary') }}</span> @endif
                                        @if ($guardian->is_fee_responsible) <span class="badge text-bg-success">{{ __('Fee responsible') }}</span> @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No guardians captured.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            @if ($application->status === 'fee_pending')
                <div class="card mb-4">
                    <div class="card-header">{{ __('Receipt application fee') }}</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <select class="form-select form-select-sm" wire:model="feeTenderType">
                                    <option value="cash">{{ __('Cash') }}</option>
                                    <option value="eft">{{ __('EFT') }}</option>
                                    <option value="swipe">{{ __('Swipe') }}</option>
                                    <option value="mobile_money">{{ __('Mobile money') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select class="form-select form-select-sm @error('feeBankAccountId') is-invalid @enderror" wire:model="feeBankAccountId">
                                    <option value="">{{ __('Bank/till account') }}</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <select class="form-select form-select-sm @error('feeIncomeAccountId') is-invalid @enderror" wire:model="feeIncomeAccountId">
                                    <option value="">{{ __('Income account') }}</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary mt-3" wire:click="payFee">{{ __('Receipt fee') }}</button>
                    </div>
                </div>
            @endif

            @if (in_array($application->status, ['submitted', 'under_review', 'exam_completed', 'interview_completed', 'waitlisted']))
                <div class="card mb-4">
                    <div class="card-header">{{ __('Make an offer') }}</div>
                    <div class="card-body">
                        <div class="form-floating form-floating-outline">
                            <input type="number" class="form-control" wire:model="offerValidDays">
                            <label>{{ __('Offer valid for (days)') }}</label>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-sm btn-primary" wire:click="makeOffer">{{ __('Make offer') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="declineApplication" wire:confirm="{{ __('Decline this application?') }}">{{ __('Decline') }}</button>
                        </div>
                        <div class="form-floating form-floating-outline mt-2">
                            <input type="text" class="form-control @error('declineReason') is-invalid @enderror" wire:model="declineReason" placeholder=" ">
                            <label>{{ __('Decline reason (needed to decline)') }}</label>
                        </div>
                    </div>
                </div>
            @endif

            @if ($application->status === 'offered')
                <div class="card mb-4">
                    <div class="card-header">{{ __('Offer outstanding') }}</div>
                    <div class="card-body">
                        <p class="text-body-secondary small">{{ __('Expires :date', ['date' => $application->offer_expires_at?->format('d M Y')]) }}</p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-success" wire:click="acceptOffer">{{ __('Accept offer') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="expireOffer">{{ __('Expire now') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="declineApplication" wire:confirm="{{ __('Decline this application?') }}">{{ __('Decline') }}</button>
                        </div>
                        <div class="form-floating form-floating-outline mt-2">
                            <input type="text" class="form-control @error('declineReason') is-invalid @enderror" wire:model="declineReason" placeholder=" ">
                            <label>{{ __('Decline reason') }}</label>
                        </div>
                    </div>
                </div>
            @endif

            @if ($application->status === 'accepted')
                <div class="card mb-4">
                    <div class="card-header">{{ __('Receipt acceptance deposit') }}</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <select class="form-select form-select-sm" wire:model="depositTenderType">
                                    <option value="cash">{{ __('Cash') }}</option>
                                    <option value="eft">{{ __('EFT') }}</option>
                                    <option value="swipe">{{ __('Swipe') }}</option>
                                    <option value="mobile_money">{{ __('Mobile money') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select class="form-select form-select-sm @error('depositBankAccountId') is-invalid @enderror" wire:model="depositBankAccountId">
                                    <option value="">{{ __('Bank/till account') }}</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <select class="form-select form-select-sm @error('depositRefundableAccountId') is-invalid @enderror" wire:model="depositRefundableAccountId">
                                    <option value="">{{ __('Refundable deposits account') }}</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary mt-3" wire:click="payDeposit">{{ __('Receipt deposit') }}</button>
                    </div>
                </div>
            @endif

            @if ($application->status === 'deposit_paid')
                <div class="card mb-4">
                    <div class="card-header">{{ __('Ready to convert') }}</div>
                    <div class="card-body">
                        <p class="text-body-secondary small">{{ __('The deposit is on account. Converting creates the learner record and carries the deposit across as a fee credit.') }}</p>
                        <a href="{{ route('people.admissions.applications.convert', [$school, $application]) }}" class="btn btn-sm btn-primary" wire:navigate>{{ __('Convert to learner') }}</a>
                    </div>
                </div>
            @endif

            @if ($application->status === 'enrolled' && $application->student_id)
                <div class="card mb-4">
                    <div class="card-header">{{ __('Converted') }}</div>
                    <div class="card-body">
                        <a href="{{ route('people.students.show', [$school, $application->student_id]) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('View learner record') }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
