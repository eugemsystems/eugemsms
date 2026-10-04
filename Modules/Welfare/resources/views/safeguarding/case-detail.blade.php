<div>
    <h4 class="mb-1">{{ __('Case') }} {{ $case->case_reference }}</h4>
    <p class="text-body-secondary mb-1">{{ __('Access basis') }}: <span class="badge text-bg-info">{{ $accessBasis }}</span></p>
    <p class="text-body-secondary mb-4">{{ __('Every view of this case is logged to the hardened safeguarding audit stream.') }}</p>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><strong>{{ __('Student') }}:</strong> {{ $case->student?->first_name }} {{ $case->student?->last_name }}</div>
                <div class="col-md-3"><strong>{{ __('Category') }}:</strong> {{ str_replace('_', ' ', $case->category) }}</div>
                <div class="col-md-3"><strong>{{ __('Risk level') }}:</strong> <span class="badge text-bg-{{ in_array($case->risk_level, ['high', 'critical'], true) ? 'danger' : 'secondary' }}">{{ $case->risk_level }}</span></div>
                <div class="col-md-3"><strong>{{ __('Status') }}:</strong> {{ $case->status }}</div>
            </div>
            <div class="mt-2"><strong>{{ __('Summary') }}:</strong> {{ $case->summary }}</div>
            <div class="mt-1 small">
                <strong>{{ __('Guardians informed') }}:</strong>
                @if ($case->guardians_informed === false)
                    {{ __('No') }} — {{ $case->guardians_not_informed_reason }}
                @else
                    {{ $case->guardians_informed ? __('Yes') : __('—') }}
                @endif
            </div>

            @if ($case->status !== 'closed' && $accessBasis === 'lead')
                <div class="mt-3 d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" wire:model="closureSummary" placeholder="{{ __('Closure summary') }}">
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="closeCase">{{ __('Close case') }}</button>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">{{ __('Chronology — append-only') }}</div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto">
                    @forelse ($entries as $entry)
                        <div class="border-bottom pb-2 mb-2" wire:key="entry-{{ $entry->id }}">
                            <div class="small text-body-secondary">{{ $entry->entry_at->format('Y-m-d H:i') }} · {{ $entry->entry_type }} {{ $entry->is_learner_account ? '— ' . __('learner\'s own words') : '' }}</div>
                            <div>{{ $entry->content }}</div>
                        </div>
                    @empty
                        <p class="text-body-secondary">{{ __('No entries yet.') }}</p>
                    @endforelse
                </div>
                @if (in_array($accessBasis, ['lead', 'contribute', 'full'], true))
                    <div class="card-body border-top">
                        <select class="form-select form-select-sm mb-2" wire:model="entryType">
                            <option value="observation">{{ __('Observation') }}</option>
                            <option value="conversation">{{ __('Conversation') }}</option>
                            <option value="action_taken">{{ __('Action taken') }}</option>
                            <option value="referral">{{ __('Referral') }}</option>
                            <option value="agency_contact">{{ __('Agency contact') }}</option>
                            <option value="review">{{ __('Review') }}</option>
                            <option value="guardian_contact">{{ __('Guardian contact') }}</option>
                            <option value="decision">{{ __('Decision') }}</option>
                        </select>
                        <textarea class="form-control form-control-sm mb-2" wire:model="entryContent" placeholder="{{ __('Entry content') }}"></textarea>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="isLearnerAccount" wire:model="isLearnerAccount">
                            <label class="form-check-label small" for="isLearnerAccount">{{ __('This is the learner\'s own account') }}</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="addEntry">{{ __('Add entry') }}</button>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">{{ __('Risk assessments') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Level') }}</th><th>{{ __('Review due') }}</th></tr></thead>
                        <tbody>
                            @forelse ($riskAssessments as $assessment)
                                <tr><td>{{ $assessment->assessed_at->toDateString() }}</td><td>{{ $assessment->risk_level }}</td><td>{{ $assessment->review_due_on->toDateString() }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-2">{{ __('None yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if (in_array($accessBasis, ['lead', 'contribute', 'full'], true))
                    <div class="card-body border-top">
                        <select class="form-select form-select-sm mb-2" wire:model="riskLevel">
                            <option value="low">{{ __('Low') }}</option>
                            <option value="medium">{{ __('Medium') }}</option>
                            <option value="high">{{ __('High') }}</option>
                            <option value="critical">{{ __('Critical') }}</option>
                        </select>
                        <input type="text" class="form-control form-control-sm mb-2" wire:model="riskFactorsRaw" placeholder="{{ __('Risk factors, comma-separated') }}">
                        <input type="text" class="form-control form-control-sm mb-2" wire:model="protectiveFactorsRaw" placeholder="{{ __('Protective factors, comma-separated (optional)') }}">
                        <textarea class="form-control form-control-sm mb-2" wire:model="rationale" placeholder="{{ __('Rationale') }}"></textarea>
                        <textarea class="form-control form-control-sm mb-2" wire:model="mitigationPlan" placeholder="{{ __('Mitigation plan') }}"></textarea>
                        <input type="date" class="form-control form-control-sm mb-2" wire:model="reviewDueOn">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="recordRiskAssessment">{{ __('Record') }}</button>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header">{{ __('Agency referrals') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Agency') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($referrals as $referral)
                                <tr><td>{{ $referral->agency_name }}</td><td>{{ $referral->status }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-2">{{ __('None yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($accessBasis === 'lead')
                    <div class="card-body border-top">
                        <select class="form-select form-select-sm mb-2" wire:model="agencyType">
                            <option value="social_services">{{ __('Social services') }}</option>
                            <option value="police">{{ __('Police') }}</option>
                            <option value="child_protection">{{ __('Child protection') }}</option>
                            <option value="health">{{ __('Health') }}</option>
                            <option value="ngo">{{ __('NGO') }}</option>
                            <option value="legal">{{ __('Legal') }}</option>
                            <option value="court">{{ __('Court') }}</option>
                        </select>
                        <input type="text" class="form-control form-control-sm mb-2" wire:model="agencyName" placeholder="{{ __('Agency name') }}">
                        <textarea class="form-control form-control-sm mb-2" wire:model="referralReason" placeholder="{{ __('Reason') }}"></textarea>
                        <select class="form-select form-select-sm mb-2" wire:model="consentBasis">
                            <option value="guardian_consent">{{ __('Guardian consent') }}</option>
                            <option value="vital_interest">{{ __('Vital interest') }}</option>
                            <option value="legal_obligation">{{ __('Legal obligation') }}</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="makeReferral">{{ __('Make referral') }}</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
